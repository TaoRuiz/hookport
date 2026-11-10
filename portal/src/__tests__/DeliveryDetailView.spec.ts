import { afterEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount, RouterLinkStub } from '@vue/test-utils'
import { createVuetify } from 'vuetify'
import DeliveryDetailView from '@/views/DeliveryDetailView.vue'

const deliveryUuid = '01890000-0000-7000-8000-000000000011'
const eventUuid = '01890000-0000-7000-8000-000000000001'
const retryingDelivery = {
  uuid: deliveryUuid,
  eventUuid,
  target: { id: 1, name: 'Receiver' },
  status: 'retrying',
  attemptsCount: 2,
  maxAttempts: 5,
  nextAttemptAt: '2026-11-10T10:06:00+00:00',
  attempts: [
    {
      attemptNo: 1,
      sentAt: '2026-11-10T10:00:00+00:00',
      durationMs: 120,
      responseCode: 500,
      error: null,
      responseExcerpt: 'Internal error',
    },
    {
      attemptNo: 2,
      sentAt: '2026-11-10T10:01:00+00:00',
      durationMs: 5000,
      responseCode: null,
      error: 'Timeout',
      responseExcerpt: null,
    },
  ],
}

function stubFetchResponse(response: Response): void {
  vi.stubGlobal('fetch', vi.fn<typeof fetch>().mockResolvedValueOnce(response))
}

async function mountDeliveryDetailView() {
  const wrapper = mount(DeliveryDetailView, {
    props: { uuid: deliveryUuid },
    global: { plugins: [createVuetify()], stubs: { RouterLink: RouterLinkStub } },
  })
  await flushPromises()

  return wrapper
}

describe('DeliveryDetailView', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('shows the delivery with a link to its event', async () => {
    stubFetchResponse(new Response(JSON.stringify(retryingDelivery)))

    const wrapper = await mountDeliveryDetailView()

    expect(fetch).toHaveBeenCalledWith(`/api/deliveries/${deliveryUuid}`)
    expect(wrapper.text()).toContain('Receiver')
    expect(wrapper.text()).toContain('Retrying')
    expect(wrapper.text()).toContain('2 / 5')
    expect(wrapper.findComponent(RouterLinkStub).props('to')).toEqual({
      name: 'event-detail',
      params: { uuid: eventUuid },
    })
  })

  it('lists the attempts with their outcome', async () => {
    stubFetchResponse(new Response(JSON.stringify(retryingDelivery)))

    const wrapper = await mountDeliveryDetailView()

    const rows = wrapper.findAll('[data-test="attempts"] tbody tr')
    expect(rows).toHaveLength(2)
    expect(rows[0]!.text()).toContain('120 ms')
    expect(rows[0]!.text()).toContain('500')
    expect(rows[0]!.text()).toContain('Internal error')
    expect(rows[1]!.text()).toContain('Timeout')
  })

  it('says when no attempt was made yet', async () => {
    stubFetchResponse(
      new Response(
        JSON.stringify({ ...retryingDelivery, status: 'pending', attemptsCount: 0, attempts: [] }),
      ),
    )

    const wrapper = await mountDeliveryDetailView()

    expect(wrapper.text()).toContain('No attempt yet.')
  })

  it('shows an error when the delivery cannot be loaded', async () => {
    stubFetchResponse(new Response(null, { status: 404 }))

    const wrapper = await mountDeliveryDetailView()

    expect(wrapper.text()).toContain('Unable to load this delivery')
  })
})
