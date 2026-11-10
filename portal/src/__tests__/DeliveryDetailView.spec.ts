import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount, RouterLinkStub, type VueWrapper } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createVuetify } from 'vuetify'
import { useAuthenticatedUserStore } from '@/stores/authenticatedUser'
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

const failedDelivery = { ...retryingDelivery, status: 'failed', nextAttemptAt: null }

let mountedWrapper: VueWrapper | null = null

function signInWithGrantedRoles(grantedRoles: string[]): void {
  useAuthenticatedUserStore().authenticatedUser = { login: 'someone', roles: [], grantedRoles }
}

function stubFetchResponse(response: Response): void {
  stubFetchResponses(response)
}

function stubFetchResponses(...responses: Response[]): void {
  const fetchMock = vi.fn<typeof fetch>()
  responses.forEach((response) => fetchMock.mockResolvedValueOnce(response))
  vi.stubGlobal('fetch', fetchMock)
}

function findReplayButton(wrapper: VueWrapper) {
  return wrapper.findAll('button').find((button) => button.text() === 'Replay')
}

async function waitForNextRefresh(): Promise<void> {
  await vi.advanceTimersByTimeAsync(5000)
  await flushPromises()
}

async function mountDeliveryDetailView() {
  mountedWrapper = mount(DeliveryDetailView, {
    props: { uuid: deliveryUuid },
    global: { plugins: [createVuetify()], stubs: { RouterLink: RouterLinkStub } },
  })
  await flushPromises()

  return mountedWrapper
}

describe('DeliveryDetailView', () => {
  beforeEach(() => {
    vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] })
    setActivePinia(createPinia())
    signInWithGrantedRoles(['ROLE_DEMO', 'ROLE_EVENT_REPLAY'])
  })

  afterEach(() => {
    mountedWrapper?.unmount()
    vi.unstubAllGlobals()
    vi.useRealTimers()
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

  it('replays a failed delivery then reloads it', async () => {
    stubFetchResponses(
      new Response(JSON.stringify(failedDelivery)),
      new Response(null, { status: 202 }),
      new Response(JSON.stringify({ ...failedDelivery, status: 'pending' })),
    )
    const wrapper = await mountDeliveryDetailView()

    await findReplayButton(wrapper)!.trigger('click')
    await flushPromises()

    expect(fetch).toHaveBeenCalledWith(`/api/deliveries/${deliveryUuid}/replay`, {
      method: 'POST',
    })
    expect(wrapper.text()).toContain('Pending')
    expect(findReplayButton(wrapper)).toBeUndefined()
  })

  it('shows an error when the replay fails', async () => {
    stubFetchResponses(
      new Response(JSON.stringify(failedDelivery)),
      new Response(null, { status: 409 }),
    )
    const wrapper = await mountDeliveryDetailView()

    await findReplayButton(wrapper)!.trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('Unable to replay this delivery')
  })

  it('offers no replay for a delivery that has not failed', async () => {
    stubFetchResponse(new Response(JSON.stringify(retryingDelivery)))

    const wrapper = await mountDeliveryDetailView()

    expect(findReplayButton(wrapper)).toBeUndefined()
  })

  it('hides the replay button from a user who cannot replay', async () => {
    signInWithGrantedRoles(['ROLE_USER'])
    stubFetchResponse(new Response(JSON.stringify(failedDelivery)))

    const wrapper = await mountDeliveryDetailView()

    expect(findReplayButton(wrapper)).toBeUndefined()
  })

  it('refreshes the delivery every five seconds', async () => {
    stubFetchResponses(
      new Response(JSON.stringify(retryingDelivery)),
      new Response(JSON.stringify(failedDelivery)),
    )
    const wrapper = await mountDeliveryDetailView()

    await waitForNextRefresh()

    expect(fetch).toHaveBeenCalledTimes(2)
    expect(wrapper.text()).toContain('Failed')
  })

  it('keeps the shown delivery when a refresh fails', async () => {
    stubFetchResponses(
      new Response(JSON.stringify(retryingDelivery)),
      new Response(null, { status: 500 }),
    )
    const wrapper = await mountDeliveryDetailView()

    await waitForNextRefresh()

    expect(wrapper.findAll('[data-test="attempts"] tbody tr')).toHaveLength(2)
    expect(wrapper.text()).not.toContain('Unable to load this delivery')
  })

  it('stops refreshing once the page is left', async () => {
    stubFetchResponses(new Response(JSON.stringify(retryingDelivery)))
    const wrapper = await mountDeliveryDetailView()

    wrapper.unmount()
    mountedWrapper = null
    await waitForNextRefresh()

    expect(fetch).toHaveBeenCalledTimes(1)
  })
})
