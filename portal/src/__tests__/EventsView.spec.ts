import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount, RouterLinkStub, type VueWrapper } from '@vue/test-utils'
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
  stubFetchResponses(response)
}

function stubFetchResponses(...responses: Response[]): void {
  const fetchMock = vi.fn<typeof fetch>()
  responses.forEach((response) => fetchMock.mockResolvedValueOnce(response))
  vi.stubGlobal('fetch', fetchMock)
}

async function waitForNextRefresh(): Promise<void> {
  await vi.advanceTimersByTimeAsync(5000)
  await flushPromises()
}

async function mountEventsView() {
  mountedWrapper = mount(EventsView, {
    global: { plugins: [createVuetify()], stubs: { RouterLink: RouterLinkStub } },
  })
  await flushPromises()

  return mountedWrapper
}

async function searchFor(searchedText: string, wrapper: VueWrapper): Promise<void> {
  await wrapper.find('input').setValue(searchedText)
  await wrapper.find('form').trigger('submit')
  await flushPromises()
}

function deferredResponse() {
  let resolveResponse: (response: Response) => void = () => {}
  const response = new Promise<Response>((resolve) => {
    resolveResponse = resolve
  })

  return { response, resolveResponse }
}

describe('EventsView', () => {
  beforeEach(() => {
    vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] })
  })

  afterEach(() => {
    mountedWrapper?.unmount()
    vi.unstubAllGlobals()
    vi.useRealTimers()
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

  it('links each event to its detail page', async () => {
    stubFetchResponse(new Response(JSON.stringify([deliveredEvent])))

    const wrapper = await mountEventsView()

    expect(wrapper.findComponent(RouterLinkStub).props('to')).toEqual({
      name: 'event-detail',
      params: { uuid: deliveredEvent.uuid },
    })
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

  it('refreshes the events every five seconds', async () => {
    stubFetchResponses(
      new Response(JSON.stringify([deliveredEvent])),
      new Response(JSON.stringify([eventNeedingAttention, deliveredEvent])),
    )
    const wrapper = await mountEventsView()

    await waitForNextRefresh()

    expect(fetch).toHaveBeenCalledTimes(2)
    expect(wrapper.findAll('tbody tr')).toHaveLength(2)
  })

  it('keeps the listed events when a refresh fails', async () => {
    stubFetchResponses(
      new Response(JSON.stringify([deliveredEvent])),
      new Response(null, { status: 500 }),
    )
    const wrapper = await mountEventsView()

    await waitForNextRefresh()

    expect(wrapper.findAll('tbody tr')).toHaveLength(1)
    expect(wrapper.text()).not.toContain('Unable to load events')
  })

  it('lists only the events matching the searched text', async () => {
    stubFetchResponses(
      new Response(JSON.stringify([eventNeedingAttention, deliveredEvent])),
      new Response(JSON.stringify([deliveredEvent])),
    )
    const wrapper = await mountEventsView()

    await searchFor(' REF-1234 ', wrapper)

    expect(fetch).toHaveBeenLastCalledWith('/api/events?search=REF-1234')
    expect(wrapper.findAll('tbody tr')).toHaveLength(1)
  })

  it('keeps the search when refreshing', async () => {
    stubFetchResponses(
      new Response(JSON.stringify([])),
      new Response(JSON.stringify([deliveredEvent])),
      new Response(JSON.stringify([deliveredEvent])),
    )
    const wrapper = await mountEventsView()
    await searchFor('evt_1', wrapper)

    await waitForNextRefresh()

    expect(fetch).toHaveBeenCalledTimes(3)
    expect(fetch).toHaveBeenLastCalledWith('/api/events?search=evt_1')
  })

  it('says when no event matches the search', async () => {
    stubFetchResponses(
      new Response(JSON.stringify([deliveredEvent])),
      new Response(JSON.stringify([])),
    )
    const wrapper = await mountEventsView()

    await searchFor('unknown', wrapper)

    expect(wrapper.text()).toContain('No event matches this search.')
  })

  it('ignores a refresh answer that arrives after a new search', async () => {
    const slowRefresh = deferredResponse()
    const fetchMock = vi
      .fn<typeof fetch>()
      .mockResolvedValueOnce(new Response(JSON.stringify([deliveredEvent])))
      .mockReturnValueOnce(slowRefresh.response)
      .mockResolvedValueOnce(new Response(JSON.stringify([])))
    vi.stubGlobal('fetch', fetchMock)
    const wrapper = await mountEventsView()
    await vi.advanceTimersByTimeAsync(5000)

    await searchFor('unknown', wrapper)
    slowRefresh.resolveResponse(new Response(JSON.stringify([eventNeedingAttention, deliveredEvent])))
    await flushPromises()

    expect(wrapper.text()).toContain('No event matches this search.')
  })

  it('stops refreshing once the page is left', async () => {
    stubFetchResponses(new Response(JSON.stringify([deliveredEvent])))
    const wrapper = await mountEventsView()

    wrapper.unmount()
    mountedWrapper = null
    await waitForNextRefresh()

    expect(fetch).toHaveBeenCalledTimes(1)
  })
})
