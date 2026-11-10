import { afterEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createVuetify } from 'vuetify'
import EventDetailView from '@/views/EventDetailView.vue'

const eventUuid = '01890000-0000-7000-8000-000000000001'
const routedEvent = {
  uuid: eventUuid,
  type: 'record.created',
  source: { id: 1, slug: 'emitter-app' },
  externalId: 'evt_1',
  receivedAt: '2026-11-10T10:00:00+00:00',
  state: 'needs_attention',
  payload: { field: 'value' },
  deliveries: [
    {
      uuid: '01890000-0000-7000-8000-000000000011',
      target: { id: 1, name: 'Receiver' },
      status: 'failed',
      attemptsCount: 5,
      maxAttempts: 5,
      nextAttemptAt: null,
    },
    {
      uuid: '01890000-0000-7000-8000-000000000012',
      target: { id: 2, name: 'Other receiver' },
      status: 'retrying',
      attemptsCount: 2,
      maxAttempts: 5,
      nextAttemptAt: '2026-11-10T10:05:00+00:00',
    },
  ],
}

function stubFetchResponse(response: Response): void {
  vi.stubGlobal('fetch', vi.fn<typeof fetch>().mockResolvedValueOnce(response))
}

async function mountEventDetailView() {
  const wrapper = mount(EventDetailView, {
    props: { uuid: eventUuid },
    global: { plugins: [createVuetify()] },
  })
  await flushPromises()

  return wrapper
}

describe('EventDetailView', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('shows the event, its payload and global state', async () => {
    stubFetchResponse(new Response(JSON.stringify(routedEvent)))

    const wrapper = await mountEventDetailView()

    expect(fetch).toHaveBeenCalledWith(`/api/events/${eventUuid}`)
    expect(wrapper.text()).toContain('record.created')
    expect(wrapper.text()).toContain('emitter-app')
    expect(wrapper.text()).toContain('evt_1')
    expect(wrapper.text()).toContain('Needs attention')
    expect(wrapper.find('pre').text()).toContain('"field": "value"')
  })

  it('lists the deliveries with their status and attempts', async () => {
    stubFetchResponse(new Response(JSON.stringify(routedEvent)))

    const wrapper = await mountEventDetailView()

    const rows = wrapper.findAll('[data-test="deliveries"] tbody tr')
    expect(rows).toHaveLength(2)
    expect(rows[0]!.text()).toContain('Receiver')
    expect(rows[0]!.text()).toContain('Failed')
    expect(rows[0]!.text()).toContain('5 / 5')
    expect(rows[1]!.text()).toContain('Retrying')
    expect(rows[1]!.text()).toContain('2 / 5')
  })

  it('says when no target matched the event', async () => {
    stubFetchResponse(
      new Response(JSON.stringify({ ...routedEvent, state: 'unrouted', deliveries: [] })),
    )

    const wrapper = await mountEventDetailView()

    expect(wrapper.text()).toContain('No target matched this event.')
  })

  it('shows an error when the event cannot be loaded', async () => {
    stubFetchResponse(new Response(null, { status: 404 }))

    const wrapper = await mountEventDetailView()

    expect(wrapper.text()).toContain('Unable to load this event')
  })
})
