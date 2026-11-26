import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { DOMWrapper, flushPromises, mount, RouterLinkStub, type VueWrapper } from '@vue/test-utils'
import { createVuetify } from 'vuetify'
import EventsView from '@/views/EventsView.vue'

const deliveredEvent = {
  uuid: '01890000-0000-7000-8000-000000000001',
  type: { slug: 'record.created', label: 'Record created' },
  source: { id: 1, slug: 'emitter-app', name: 'Emitter app' },
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

function eventListResponse(events: unknown[], totalCount = events.length): Response {
  return new Response(JSON.stringify({ events, totalCount }))
}

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

    return Promise.resolve(eventListResponses.shift() ?? eventListResponse([]))
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

async function openFilterOptions(selectLabel: string, wrapper: VueWrapper) {
  const select = wrapper
    .findAll('.v-select')
    .find((candidate) => candidate.find('label').text() === selectLabel)!
  await select.find('.v-field').trigger('mousedown')
  await flushPromises()

  return new DOMWrapper(document.body).findAll('.v-overlay--active .v-list-item')
}

async function chooseInFilter(selectLabel: string, optionTitle: string, wrapper: VueWrapper) {
  const options = await openFilterOptions(selectLabel, wrapper)
  await options
    .find((option) => option.find('.v-list-item-title').text() === optionTitle)!
    .trigger('click')
  await flushPromises()
}

async function pickDayOfCurrentMonth(dateLabel: string, dayNumber: number, wrapper: VueWrapper) {
  await wrapper
    .findAll('.v-date-input')
    .find((candidate) => candidate.find('label').text() === dateLabel)!
    .find('.v-field')
    .trigger('click')
  await flushPromises()
  await new DOMWrapper(document.body)
    .findAll(
      '.v-overlay--active .v-date-picker-month__day:not(.v-date-picker-month__day--adjacent) button',
    )
    .find((dayButton) => dayButton.text() === String(dayNumber))!
    .trigger('click')
  await flushPromises()
}

async function goToPage(pageNumber: number, wrapper: VueWrapper) {
  await wrapper
    .findAll('.v-pagination button')
    .find((pageButton) => pageButton.text() === String(pageNumber))!
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
    stubFetchResponse(eventListResponse([eventNeedingAttention, deliveredEvent]))

    const wrapper = await mountEventsView()

    expect(fetch).toHaveBeenCalledWith('/api/events?page=1&perPage=25')
    const rows = wrapper.findAll('tbody tr')
    expect(rows).toHaveLength(2)
    expect(rows[0]!.text()).toContain('evt_2')
    expect(rows[0]!.text()).toContain('Needs attention')
    expect(rows[1]!.text()).toContain('Record created')
    expect(rows[1]!.text()).toContain('record.created')
    expect(rows[1]!.text()).toContain('Emitter app')
    expect(rows[1]!.text()).toContain('emitter-app')
    expect(rows[1]!.text()).toContain('Delivered')
  })

  it('shows the bare slug of a type missing from the catalog', async () => {
    const eventOfUnknownType = { ...deliveredEvent, type: { slug: 'record.archived', label: null } }
    stubFetchResponse(eventListResponse([eventOfUnknownType]))

    const wrapper = await mountEventsView()

    const typeCell = wrapper.findAll('tbody tr td')[1]!
    expect(typeCell.text()).toBe('record.archived')
  })

  it('links each event to its detail page', async () => {
    stubFetchResponse(eventListResponse([deliveredEvent]))

    const wrapper = await mountEventsView()

    expect(wrapper.findComponent(RouterLinkStub).props('to')).toEqual({
      name: 'event-detail',
      params: { uuid: deliveredEvent.uuid },
    })
  })

  it('shows each delivery as a chip named after its target and linked to the delivery', async () => {
    stubFetchResponse(eventListResponse([eventNeedingAttention]))

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
    stubFetchResponse(eventListResponse([]))

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
      eventListResponse([deliveredEvent]),
      eventListResponse([eventNeedingAttention, deliveredEvent]),
    )
    const wrapper = await mountEventsView()

    await waitForNextRefresh()

    expect(eventListUrls()).toHaveLength(2)
    expect(wrapper.findAll('tbody tr')).toHaveLength(2)
  })

  it('keeps the listed events when a refresh fails', async () => {
    stubFetchResponses(eventListResponse([deliveredEvent]), new Response(null, { status: 500 }))
    const wrapper = await mountEventsView()

    await waitForNextRefresh()

    expect(wrapper.findAll('tbody tr')).toHaveLength(1)
    expect(wrapper.text()).not.toContain('Unable to load events')
  })

  it('lists only the events matching the searched text', async () => {
    stubFetchResponses(
      eventListResponse([eventNeedingAttention, deliveredEvent]),
      eventListResponse([deliveredEvent]),
    )
    const wrapper = await mountEventsView()

    await searchFor(' REF-1234 ', wrapper)

    expect(lastEventListUrl()).toBe('/api/events?search=REF-1234&page=1&perPage=25')
    expect(wrapper.findAll('tbody tr')).toHaveLength(1)
  })

  it('keeps the search when refreshing', async () => {
    stubFetchResponses(
      eventListResponse([]),
      eventListResponse([deliveredEvent]),
      eventListResponse([deliveredEvent]),
    )
    const wrapper = await mountEventsView()
    await searchFor('evt_1', wrapper)

    await waitForNextRefresh()

    expect(eventListUrls()).toEqual([
      '/api/events?page=1&perPage=25',
      '/api/events?search=evt_1&page=1&perPage=25',
      '/api/events?search=evt_1&page=1&perPage=25',
    ])
  })

  it('says when no event matches the search', async () => {
    stubFetchResponses(eventListResponse([deliveredEvent]), eventListResponse([]))
    const wrapper = await mountEventsView()

    await searchFor('unknown', wrapper)

    expect(wrapper.text()).toContain('No event matches the search and filters.')
  })

  it('ignores a refresh answer that arrives after a new search', async () => {
    const slowRefresh = deferredResponse()
    stubFetchResponses(
      eventListResponse([deliveredEvent]),
      slowRefresh.response,
      eventListResponse([]),
    )
    const wrapper = await mountEventsView()
    await vi.advanceTimersByTimeAsync(5000)

    await searchFor('unknown', wrapper)
    slowRefresh.resolveResponse(eventListResponse([eventNeedingAttention, deliveredEvent]))
    await flushPromises()

    expect(wrapper.text()).toContain('No event matches the search and filters.')
  })

  it('sends the chosen type and source filters', async () => {
    stubFetchResponses()
    const wrapper = await mountEventsView()

    await chooseInFilter('Type', 'Record created', wrapper)
    await chooseInFilter('Source', 'Emitter app', wrapper)

    expect(lastEventListUrl()).toBe('/api/events?type=record.created&sourceId=1&page=1&perPage=25')
  })

  it.each([
    ['Type', 'Record created', 'record.created'],
    ['Source', 'Emitter app', 'emitter-app'],
  ])(
    'offers each %s filter option by its readable name above its slug',
    async (selectLabel, readableName, slug) => {
      stubFetchResponses()
      const wrapper = await mountEventsView()

      const [firstOption] = await openFilterOptions(selectLabel, wrapper)

      expect(firstOption!.find('.v-list-item-title').text()).toBe(readableName)
      expect(firstOption!.find('.v-list-item-subtitle').text()).toBe(slug)
    },
  )

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

    await pickDayOfCurrentMonth('Received from', 10, wrapper)
    await pickDayOfCurrentMonth('Received to', 10, wrapper)

    const today = new Date()
    const lastQueryParameters = new URLSearchParams(lastEventListUrl().split('?')[1])
    expect(lastQueryParameters.get('receivedFrom')).toBe(
      new Date(today.getFullYear(), today.getMonth(), 10).toISOString(),
    )
    expect(lastQueryParameters.get('receivedBefore')).toBe(
      new Date(today.getFullYear(), today.getMonth(), 11).toISOString(),
    )
  })

  it('hides the pagination when every event fits on one page', async () => {
    stubFetchResponse(eventListResponse([deliveredEvent], 25))

    const wrapper = await mountEventsView()

    expect(wrapper.text()).toContain('1–1 of 25')
    expect(wrapper.find('.v-pagination').exists()).toBe(false)
  })

  it('loads the chosen page and keeps it on the next refresh', async () => {
    stubFetchResponses(
      eventListResponse([deliveredEvent], 60),
      eventListResponse([eventNeedingAttention], 60),
      eventListResponse([eventNeedingAttention], 60),
    )
    const wrapper = await mountEventsView()

    await goToPage(2, wrapper)
    await waitForNextRefresh()

    expect(wrapper.text()).toContain('26–26 of 60')
    expect(eventListUrls().slice(1)).toEqual([
      '/api/events?page=2&perPage=25',
      '/api/events?page=2&perPage=25',
    ])
  })

  it('goes back to the first page when a filter changes', async () => {
    stubFetchResponses(
      eventListResponse([deliveredEvent], 60),
      eventListResponse([deliveredEvent], 60),
    )
    const wrapper = await mountEventsView()
    await goToPage(2, wrapper)

    await chooseInFilter('Source', 'Emitter app', wrapper)

    expect(lastEventListUrl()).toBe('/api/events?sourceId=1&page=1&perPage=25')
  })

  it('stops refreshing once the page is left', async () => {
    stubFetchResponses(eventListResponse([deliveredEvent]))
    const wrapper = await mountEventsView()

    wrapper.unmount()
    mountedWrapper = null
    await waitForNextRefresh()

    expect(eventListUrls()).toHaveLength(1)
  })
})
