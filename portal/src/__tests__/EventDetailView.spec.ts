import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount, RouterLinkStub, type VueWrapper } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createVuetify } from 'vuetify'
import { useAuthenticatedUserStore } from '@/stores/authenticatedUser'
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

const unroutedEvent = { ...routedEvent, state: 'unrouted', deliveries: [] }
const failedDeliveryUuid = routedEvent.deliveries[0]!.uuid

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

function findButtonByText(wrapper: VueWrapper, text: string) {
  return wrapper.findAll('button').find((button) => button.text() === text)
}

async function waitForNextRefresh(): Promise<void> {
  await vi.advanceTimersByTimeAsync(5000)
  await flushPromises()
}

async function mountEventDetailView() {
  mountedWrapper = mount(EventDetailView, {
    props: { uuid: eventUuid },
    global: { plugins: [createVuetify()], stubs: { RouterLink: RouterLinkStub } },
  })
  await flushPromises()

  return mountedWrapper
}

describe('EventDetailView', () => {
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
    stubFetchResponse(new Response(JSON.stringify(unroutedEvent)))

    const wrapper = await mountEventDetailView()

    expect(wrapper.text()).toContain('No target matched this event.')
  })

  it('shows an error when the event cannot be loaded', async () => {
    stubFetchResponse(new Response(null, { status: 404 }))

    const wrapper = await mountEventDetailView()

    expect(wrapper.text()).toContain('Unable to load this event')
  })

  it('replays an unrouted event then shows its new deliveries', async () => {
    stubFetchResponses(
      new Response(JSON.stringify(unroutedEvent)),
      new Response(JSON.stringify([])),
      new Response(JSON.stringify(routedEvent)),
    )
    const wrapper = await mountEventDetailView()

    await findButtonByText(wrapper, 'Replay event')!.trigger('click')
    await flushPromises()

    expect(fetch).toHaveBeenCalledWith(`/api/events/${eventUuid}/replay`, { method: 'POST' })
    expect(wrapper.findAll('[data-test="deliveries"] tbody tr')).toHaveLength(2)
  })

  it('replays a failed delivery then reloads the event', async () => {
    stubFetchResponses(
      new Response(JSON.stringify(routedEvent)),
      new Response(null, { status: 202 }),
      new Response(JSON.stringify(routedEvent)),
    )
    const wrapper = await mountEventDetailView()

    const replayButtons = wrapper.findAll('[data-test="deliveries"] button')
    expect(replayButtons).toHaveLength(1)
    await replayButtons[0]!.trigger('click')
    await flushPromises()

    expect(fetch).toHaveBeenCalledWith(`/api/deliveries/${failedDeliveryUuid}/replay`, {
      method: 'POST',
    })
    expect(fetch).toHaveBeenCalledTimes(3)
  })

  it('shows an error when a replay fails', async () => {
    stubFetchResponses(
      new Response(JSON.stringify(routedEvent)),
      new Response(null, { status: 409 }),
    )
    const wrapper = await mountEventDetailView()

    await wrapper.find('[data-test="deliveries"] button').trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('Unable to replay the delivery to Receiver')
  })

  it('hides the replay buttons from a user who cannot replay', async () => {
    signInWithGrantedRoles(['ROLE_USER'])
    stubFetchResponse(new Response(JSON.stringify(unroutedEvent)))

    const wrapper = await mountEventDetailView()

    expect(findButtonByText(wrapper, 'Replay event')).toBeUndefined()
  })

  it('refreshes the event every five seconds', async () => {
    stubFetchResponses(
      new Response(JSON.stringify(unroutedEvent)),
      new Response(JSON.stringify(routedEvent)),
    )
    const wrapper = await mountEventDetailView()

    await waitForNextRefresh()

    expect(fetch).toHaveBeenCalledTimes(2)
    expect(wrapper.findAll('[data-test="deliveries"] tbody tr')).toHaveLength(2)
  })

  it('keeps the shown event when a refresh fails', async () => {
    stubFetchResponses(
      new Response(JSON.stringify(routedEvent)),
      new Response(null, { status: 500 }),
    )
    const wrapper = await mountEventDetailView()

    await waitForNextRefresh()

    expect(wrapper.findAll('[data-test="deliveries"] tbody tr')).toHaveLength(2)
    expect(wrapper.text()).not.toContain('Unable to load this event')
  })

  it('stops refreshing once the page is left', async () => {
    stubFetchResponses(new Response(JSON.stringify(routedEvent)))
    const wrapper = await mountEventDetailView()

    wrapper.unmount()
    mountedWrapper = null
    await waitForNextRefresh()

    expect(fetch).toHaveBeenCalledTimes(1)
  })

  it('links each delivery to its detail page', async () => {
    stubFetchResponse(new Response(JSON.stringify(routedEvent)))

    const wrapper = await mountEventDetailView()

    expect(wrapper.findComponent(RouterLinkStub).props('to')).toEqual({
      name: 'delivery-detail',
      params: { uuid: failedDeliveryUuid },
    })
  })
})
