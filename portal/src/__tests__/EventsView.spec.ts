import { afterEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import { createVuetify } from 'vuetify'
import EventsView from '@/views/EventsView.vue'

const deliveredEvent = {
  uuid: '01890000-0000-7000-8000-000000000001',
  type: 'record.created',
  source: { id: 1, slug: 'emitter-app' },
  externalId: 'evt_1',
  receivedAt: '2026-11-10T10:00:00+00:00',
  state: 'delivered',
}
const eventNeedingAttention = {
  ...deliveredEvent,
  uuid: '01890000-0000-7000-8000-000000000002',
  externalId: 'evt_2',
  state: 'needs_attention',
}

let mountedWrapper: VueWrapper | null = null

function stubFetchResponse(response: Response): void {
  vi.stubGlobal('fetch', vi.fn<typeof fetch>().mockResolvedValueOnce(response))
}

async function mountEventsView() {
  mountedWrapper = mount(EventsView, { global: { plugins: [createVuetify()] } })
  await flushPromises()

  return mountedWrapper
}

describe('EventsView', () => {
  afterEach(() => {
    mountedWrapper?.unmount()
    vi.unstubAllGlobals()
  })

  it('lists the events with their source, type and global state', async () => {
    stubFetchResponse(new Response(JSON.stringify([eventNeedingAttention, deliveredEvent])))

    const wrapper = await mountEventsView()

    expect(fetch).toHaveBeenCalledWith('/api/events')
    const rows = wrapper.findAll('tbody tr')
    expect(rows).toHaveLength(2)
    expect(rows[0]!.text()).toContain('evt_2')
    expect(rows[0]!.text()).toContain('Needs attention')
    expect(rows[1]!.text()).toContain('record.created')
    expect(rows[1]!.text()).toContain('emitter-app')
    expect(rows[1]!.text()).toContain('Delivered')
  })

  it('says when no event was received yet', async () => {
    stubFetchResponse(new Response(JSON.stringify([])))

    const wrapper = await mountEventsView()

    expect(wrapper.text()).toContain('No event received yet.')
  })

  it('shows an error when the events cannot be loaded', async () => {
    stubFetchResponse(new Response(null, { status: 500 }))

    const wrapper = await mountEventsView()

    expect(wrapper.text()).toContain('Unable to load events')
  })
})
