import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createVuetify } from 'vuetify'
import { useAuthenticatedUserStore } from '@/stores/authenticatedUser'
import MappingsView from '@/views/MappingsView.vue'

const specificMapping = {
  id: 1,
  source: { id: 1, slug: 'emitter-app', name: 'Emitter app' },
  eventType: { id: 1, slug: 'record.created', label: 'Record created' },
  target: { id: 1, name: 'Receiver' },
  maxAttempts: 3,
  enabled: true,
}
const disabledWildcardMapping = {
  id: 2,
  source: null,
  eventType: null,
  target: { id: 2, name: 'Archive' },
  maxAttempts: 5,
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

async function mountMappingsView() {
  mountedWrapper = mount(MappingsView, {
    global: { plugins: [createVuetify()] },
    attachTo: document.body,
  })
  await flushPromises()

  return mountedWrapper
}

describe('MappingsView', () => {
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

  it('loads the mappings from the API', async () => {
    stubFetchResponses(jsonResponse(200, [specificMapping]))

    const wrapper = await mountMappingsView()

    expect(fetch).toHaveBeenCalledWith('/api/mappings')
    const cells = wrapper.findAll('tbody tr td').map((cell) => cell.text())
    expect(cells).toEqual(['Emitter app', 'record.created', 'Receiver', '3', 'Enabled'])
  })

  it('shows a wildcard as any source or any event type', async () => {
    stubFetchResponses(jsonResponse(200, [disabledWildcardMapping]))
    const wrapper = await mountMappingsView()

    await wrapper.find('input[type="checkbox"]').setValue(true)

    const cells = wrapper.findAll('tbody tr td').map((cell) => cell.text())
    expect(cells).toEqual(['Any source', 'Any event type', 'Archive', '5', 'Disabled'])
  })

  it('hides disabled mappings by default', async () => {
    stubFetchResponses(jsonResponse(200, [specificMapping, disabledWildcardMapping]))

    const wrapper = await mountMappingsView()

    expect(wrapper.text()).not.toContain('Archive')
  })

  it('says so when there is no mapping to show', async () => {
    stubFetchResponses(jsonResponse(200, [disabledWildcardMapping]))

    const wrapper = await mountMappingsView()

    expect(wrapper.text()).toContain('No mapping to show.')
  })

  it('shows an error when the mappings cannot be loaded', async () => {
    stubFetchResponses(jsonResponse(500, {}))

    const wrapper = await mountMappingsView()

    expect(wrapper.text()).toContain('Unable to load mappings')
  })
})
