import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createVuetify } from 'vuetify'
import { useAuthenticatedUserStore } from '@/stores/authenticatedUser'
import EventTypesView from '@/views/EventTypesView.vue'

const enabledEventType = { id: 1, slug: 'record.created', label: 'Record created', enabled: true }
const disabledEventType = {
  id: 2,
  slug: 'record.archived',
  label: 'Record archived',
  enabled: false,
}

let mountedWrapper: VueWrapper | null = null

function jsonResponse(status: number, body: unknown): Response {
  return new Response(JSON.stringify(body), { status })
}

function stubFetchResponses(...responses: Response[]): void {
  const fetchMock = vi.fn<typeof fetch>()
  responses.forEach((response) => fetchMock.mockResolvedValueOnce(response))
  vi.stubGlobal('fetch', fetchMock)
}

async function mountEventTypesView() {
  mountedWrapper = mount(EventTypesView, {
    global: { plugins: [createVuetify()] },
    attachTo: document.body,
  })
  await flushPromises()

  return mountedWrapper
}

describe('EventTypesView', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    useAuthenticatedUserStore().authenticatedUser = {
      login: 'someone',
      roles: [],
      grantedRoles: ['ROLE_DEMO', 'ROLE_USER'],
    }
  })

  afterEach(() => {
    mountedWrapper?.unmount()
    document.body.innerHTML = ''
    vi.unstubAllGlobals()
  })

  it('loads the event types from the API', async () => {
    stubFetchResponses(jsonResponse(200, [enabledEventType]))

    const wrapper = await mountEventTypesView()

    expect(fetch).toHaveBeenCalledWith('/api/event-types')
    expect(wrapper.text()).toContain('Record created')
    expect(wrapper.text()).toContain('record.created')
    expect(wrapper.text()).toContain('Enabled')
  })

  it('hides disabled event types by default', async () => {
    stubFetchResponses(jsonResponse(200, [enabledEventType, disabledEventType]))

    const wrapper = await mountEventTypesView()

    expect(wrapper.text()).not.toContain('Record archived')
  })

  it('shows disabled event types when the toggle is on', async () => {
    stubFetchResponses(jsonResponse(200, [enabledEventType, disabledEventType]))
    const wrapper = await mountEventTypesView()

    await wrapper.find('input[type="checkbox"]').setValue(true)

    expect(wrapper.text()).toContain('Record archived')
    expect(wrapper.text()).toContain('Disabled')
  })

  it('says so when there is no event type to show', async () => {
    stubFetchResponses(jsonResponse(200, [disabledEventType]))

    const wrapper = await mountEventTypesView()

    expect(wrapper.text()).toContain('No event type to show.')
  })

  it('shows an error when the event types cannot be loaded', async () => {
    stubFetchResponses(jsonResponse(500, {}))

    const wrapper = await mountEventTypesView()

    expect(wrapper.text()).toContain('Unable to load event types')
  })
})
