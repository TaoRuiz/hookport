import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { DOMWrapper, flushPromises, mount, RouterLinkStub, type VueWrapper } from '@vue/test-utils'
import { createVuetify } from 'vuetify'
import EventsView from '@/views/EventsView.vue'

const deliveredEvent = {
  uuid: '01890000-0000-7000-8000-000000000001',
  type: 'record.created',
  source: { id: 1, slug: 'emitter-app' },
  externalId: 'evt_1',
  receivedAt: '2026-11-10T10:00:00+00:00',
  state: 'delivered',
  deliveries: [],
}
const eventNeedingAttention = {
  ...deliveredEvent,
  uuid: '01890000-0000-7000-8000-000000000002',
  externalId: 'evt_2',
  state: 'needs_attention',
  deliveries: [
    {
      uuid: '01890000-0000-7000-8000-0000000000d1',
      target: { id: 1, name: 'Receiver' },
      status: 'delivered',
    },
    {
      uuid: '01890000-0000-7000-8000-0000000000d2',
      target: { id: 2, name: 'Archive' },
      status: 'failed',
    },
  ],
}

let mountedWrapper: VueWrapper | null = null

function stubFetchResponse(response: Response): void {
  stubFetchResponses(response)
}

const source = { id: 1, slug: 'emitter-app', name: 'Emitter app', enabled: true }
const eventType = { id: 1, slug: 'record.created', label: 'Record created', enabled: true }

// The page also loads the filter choices, so answers are given by URL, events list answers in order.
function stubFetchResponses(...eventListResponses: (Response | Promise<Response>)[]): void {
  const fetchMock = vi.fn<typeof fetch>((input) => {
    if (input === '/api/sources') {
      return Promise.resolve(new Response(JSON.stringify([source])))
    }
    if (input === '/api/event-types') {
      return Promise.resolve(new Response(JSON.stringify([eventType])))
    }

    return Promise.resolve(eventListResponses.shift() ?? new Response(JSON.stringify([])))
  })
  vi.stubGlobal('fetch', fetchMock)
}

function eventListUrls(): string[] {
  return vi
    .mocked(fetch)
    .mock.calls.map(([input]) => String(input))
    .filter((url) => url.startsWith('/api/events'))
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

function lastEventListUrl(): string {
  const urls = eventListUrls()

  return urls[urls.length - 1]!
}

async function chooseInFilter(selectLabel: string, optionTitle: string, wrapper: VueWrapper) {
  const select = wrapper
    .findAll('.v-select')
    .find((candidate) => candidate.find('label').text() === selectLabel)!
  await select.find('.v-field').trigger('mousedown')
  await flushPromises()
  await new DOMWrapper(document.body)
    .findAll('.v-overlay--active .v-list-item')
    .find((option) => option.text() === optionTitle)!
    .trigger('click')
  await flushPromises()
}

async function toggleStateChip(stateLabel: string, wrapper: VueWrapper) {
  await wrapper
    .findAll('.v-chip-group .v-chip')
    .find((chip) => chip.text() === stateLabel)!
    .trigger('click')
  await flushPromises()
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

  it('shows each delivery as a chip named after its target and linked to the delivery', async () => {
    stubFetchResponse(new Response(JSON.stringify([eventNeedingAttention])))

    const wrapper = await mountEventsView()

    const deliveryChips = wrapper.findAll('tbody .v-chip').slice(1)
    expect(deliveryChips.map((chip) => chip.attributes('title'))).toEqual([
      'Receiver: Delivered',
      'Archive: Failed',
    ])
    expect(
      wrapper
        .findAllComponents(RouterLinkStub)
        .slice(1)
        .map((link) => link.props('to')),
    ).toEqual([
      { name: 'delivery-detail', params: { uuid: '01890000-0000-7000-8000-0000000000d1' } },
      { name: 'delivery-detail', params: { uuid: '01890000-0000-7000-8000-0000000000d2' } },
    ])
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

    expect(eventListUrls()).toHaveLength(2)
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

    expect(lastEventListUrl()).toBe('/api/events?search=REF-1234')
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

    expect(eventListUrls()).toEqual([
      '/api/events',
      '/api/events?search=evt_1',
      '/api/events?search=evt_1',
    ])
  })

  it('says when no event matches the search', async () => {
    stubFetchResponses(
      new Response(JSON.stringify([deliveredEvent])),
      new Response(JSON.stringify([])),
    )
    const wrapper = await mountEventsView()

    await searchFor('unknown', wrapper)

    expect(wrapper.text()).toContain('No event matches the search and filters.')
  })

  it('ignores a refresh answer that arrives after a new search', async () => {
    const slowRefresh = deferredResponse()
    stubFetchResponses(
      new Response(JSON.stringify([deliveredEvent])),
      slowRefresh.response,
      new Response(JSON.stringify([])),
    )
    const wrapper = await mountEventsView()
    await vi.advanceTimersByTimeAsync(5000)

    await searchFor('unknown', wrapper)
    slowRefresh.resolveResponse(
      new Response(JSON.stringify([eventNeedingAttention, deliveredEvent])),
    )
    await flushPromises()

    expect(wrapper.text()).toContain('No event matches the search and filters.')
  })

  it('sends the chosen type and source filters', async () => {
    stubFetchResponses()
    const wrapper = await mountEventsView()

    await chooseInFilter('Type', 'record.created', wrapper)
    await chooseInFilter('Source', 'emitter-app', wrapper)

    expect(lastEventListUrl()).toBe('/api/events?type=record.created&sourceId=1')
  })

  it('sends only the states left checked once one is unchecked', async () => {
    stubFetchResponses()
    const wrapper = await mountEventsView()

    await toggleStateChip('Delivered', wrapper)

    expect(new URLSearchParams(lastEventListUrl().split('?')[1]).getAll('states[]')).toEqual([
      'unrouted',
      'in_progress',
      'needs_attention',
    ])
  })

  it('keeps at least one state checked', async () => {
    stubFetchResponses()
    const wrapper = await mountEventsView()

    for (const stateLabel of ['Unrouted', 'In progress', 'Delivered', 'Needs attention']) {
      await toggleStateChip(stateLabel, wrapper)
    }

    expect(new URLSearchParams(lastEventListUrl().split('?')[1]).getAll('states[]')).toEqual([
      'needs_attention',
    ])
  })

  it('sends the received dates as local midnights, the "to" day included', async () => {
    stubFetchResponses()
    const wrapper = await mountEventsView()

    await wrapper.find('input[type="date"]').setValue('2026-01-10')
    await wrapper.findAll('input[type="date"]')[1]!.setValue('2026-01-10')
    await flushPromises()

    const lastQueryParameters = new URLSearchParams(lastEventListUrl().split('?')[1])
    expect(lastQueryParameters.get('receivedFrom')).toBe(
      new Date('2026-01-10T00:00:00').toISOString(),
    )
    expect(lastQueryParameters.get('receivedBefore')).toBe(
      new Date('2026-01-11T00:00:00').toISOString(),
    )
  })

  it('stops refreshing once the page is left', async () => {
    stubFetchResponses(new Response(JSON.stringify([deliveredEvent])))
    const wrapper = await mountEventsView()

    wrapper.unmount()
    mountedWrapper = null
    await waitForNextRefresh()

    expect(eventListUrls()).toHaveLength(1)
  })
})
