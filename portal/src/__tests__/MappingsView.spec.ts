import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { DOMWrapper, flushPromises, mount, type VueWrapper } from '@vue/test-utils'
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

const enabledSource = { id: 1, slug: 'emitter-app', name: 'Emitter app', enabled: true }
const disabledSource = { id: 2, slug: 'retired-app', name: 'Retired app', enabled: false }
const enabledEventType = { id: 1, slug: 'record.created', label: 'Record created', enabled: true }
const enabledTarget = {
  id: 1,
  name: 'Receiver',
  protocol: 'http',
  config: { url: 'https://receiver.example.test' },
  enabled: true,
}

// Dialogs and select menus are teleported to the end of <body>, so the whole page is queried.
const page = new DOMWrapper(document.body)
let mountedWrapper: VueWrapper | null = null

function jsonResponse(status: number, body: unknown): Response {
  return new Response(JSON.stringify(body), { status })
}

function stubFetchResponses(...responses: Response[]): void {
  const fetchMock = vi.fn<typeof fetch>()
  responses.forEach((response) => fetchMock.mockResolvedValueOnce(response))
  vi.stubGlobal('fetch', fetchMock)
}

function signInWithGrantedRoles(grantedRoles: string[]): void {
  useAuthenticatedUserStore().authenticatedUser = { login: 'someone', roles: [], grantedRoles }
}

async function mountMappingsView() {
  mountedWrapper = mount(MappingsView, {
    global: { plugins: [createVuetify()] },
    attachTo: document.body,
  })
  await flushPromises()

  return mountedWrapper
}

async function openMappingCreationDialog() {
  await page
    .findAll('button')
    .find((button) => button.text() === 'New mapping')!
    .trigger('click')
  await flushPromises()
}

async function openSelectMenu(selectLabel: string) {
  const select = page
    .findAll('.v-dialog .v-select')
    .find((candidate) => candidate.find('label').text() === selectLabel)!
  await select.find('.v-field').trigger('mousedown')
  await flushPromises()
}

function optionsOfOpenMenu() {
  return page.findAll('.v-overlay--active .v-list-item')
}

async function chooseInSelect(selectLabel: string, optionTitle: string) {
  await openSelectMenu(selectLabel)
  await optionsOfOpenMenu()
    .find((option) => option.text() === optionTitle)!
    .trigger('click')
  await flushPromises()
}

async function clickRowButton(buttonText: string) {
  await page
    .findAll('tbody button')
    .find((button) => button.text() === buttonText)!
    .trigger('click')
  await flushPromises()
}

async function submitMappingCreation() {
  await page.find('.v-dialog form').trigger('submit')
  await flushPromises()
}

function stubChoicesThen(...responsesAfterChoices: Response[]): Response[] {
  return [
    jsonResponse(200, [enabledSource, disabledSource]),
    jsonResponse(200, [enabledEventType]),
    jsonResponse(200, [enabledTarget]),
    ...responsesAfterChoices,
  ]
}

describe('MappingsView', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    signInWithGrantedRoles(['ROLE_DEMO', 'ROLE_USER'])
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

  it('hides the New mapping button from a user who cannot manage configuration', async () => {
    stubFetchResponses(jsonResponse(200, []))

    const wrapper = await mountMappingsView()

    expect(wrapper.text()).not.toContain('New mapping')
  })

  it('offers only enabled sources to choose from', async () => {
    signInWithGrantedRoles(['ROLE_ADMIN', 'ROLE_CONFIGURATION_MANAGE'])
    stubFetchResponses(jsonResponse(200, []), ...stubChoicesThen())
    await mountMappingsView()
    await openMappingCreationDialog()
    await openSelectMenu('Source')

    expect(optionsOfOpenMenu().map((option) => option.text())).toEqual(['Emitter app'])
  })

  it('creates a mapping for a source and an event type and adds it to the list', async () => {
    signInWithGrantedRoles(['ROLE_ADMIN', 'ROLE_CONFIGURATION_MANAGE'])
    stubFetchResponses(
      jsonResponse(200, []),
      ...stubChoicesThen(jsonResponse(201, specificMapping)),
    )
    const wrapper = await mountMappingsView()
    await openMappingCreationDialog()

    await chooseInSelect('Source', 'Emitter app')
    await chooseInSelect('Event type', 'record.created')
    await chooseInSelect('Target', 'Receiver')
    await page.find('.v-dialog input[type="number"]').setValue('3')
    await submitMappingCreation()

    expect(fetch).toHaveBeenLastCalledWith('/api/mappings', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ sourceId: 1, eventTypeId: 1, targetId: 1, maxAttempts: 3 }),
    })
    expect(wrapper.find('tbody').text()).toContain('Receiver')
  })

  it('sends a wildcard source and event type when none is chosen', async () => {
    signInWithGrantedRoles(['ROLE_ADMIN', 'ROLE_CONFIGURATION_MANAGE'])
    const wildcardMapping = { ...disabledWildcardMapping, enabled: true }
    stubFetchResponses(
      jsonResponse(200, []),
      ...stubChoicesThen(jsonResponse(201, wildcardMapping)),
    )
    await mountMappingsView()
    await openMappingCreationDialog()

    await chooseInSelect('Target', 'Receiver')
    await submitMappingCreation()

    expect(fetch).toHaveBeenLastCalledWith('/api/mappings', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ sourceId: null, eventTypeId: null, targetId: 1, maxAttempts: 5 }),
    })
  })

  it('does not send a mapping without a target', async () => {
    signInWithGrantedRoles(['ROLE_ADMIN', 'ROLE_CONFIGURATION_MANAGE'])
    stubFetchResponses(jsonResponse(200, []), ...stubChoicesThen())
    await mountMappingsView()
    await openMappingCreationDialog()

    await submitMappingCreation()

    expect(fetch).toHaveBeenCalledTimes(4)
    expect(page.text()).toContain('Target is required.')
  })

  it('explains when the mapping already exists', async () => {
    signInWithGrantedRoles(['ROLE_ADMIN', 'ROLE_CONFIGURATION_MANAGE'])
    stubFetchResponses(
      jsonResponse(200, []),
      ...stubChoicesThen(jsonResponse(409, { error: 'This mapping already exists.' })),
    )
    await mountMappingsView()
    await openMappingCreationDialog()

    await chooseInSelect('Target', 'Receiver')
    await submitMappingCreation()

    expect(page.text()).toContain('This mapping already exists.')
  })

  it('hides status and max attempts actions from a user who cannot manage configuration', async () => {
    stubFetchResponses(jsonResponse(200, [specificMapping]))

    const wrapper = await mountMappingsView()

    expect(wrapper.find('tbody').text()).not.toContain('Max attempts')
    expect(wrapper.find('tbody input[type="checkbox"]').exists()).toBe(false)
  })

  it('disables a mapping from its row', async () => {
    signInWithGrantedRoles(['ROLE_ADMIN', 'ROLE_CONFIGURATION_MANAGE'])
    stubFetchResponses(
      jsonResponse(200, [specificMapping]),
      jsonResponse(200, { ...specificMapping, enabled: false }),
    )
    const wrapper = await mountMappingsView()
    await wrapper.find('input[aria-label="Show disabled mappings"]').setValue(true)

    await wrapper.find('tbody input[type="checkbox"]').setValue(false)
    await flushPromises()

    expect(fetch).toHaveBeenLastCalledWith('/api/mappings/1', {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ enabled: false }),
    })
    expect(wrapper.find('tbody').text()).toContain('Disabled')
  })

  it('keeps the mapping status and explains when the status change fails', async () => {
    signInWithGrantedRoles(['ROLE_ADMIN', 'ROLE_CONFIGURATION_MANAGE'])
    stubFetchResponses(jsonResponse(200, [specificMapping]), jsonResponse(500, {}))
    const wrapper = await mountMappingsView()

    await wrapper.find('tbody input[type="checkbox"]').setValue(false)
    await flushPromises()

    expect(wrapper.text()).toContain('Unable to change the status of the mapping')
    expect(wrapper.find('tbody').text()).toContain('Enabled')
  })

  it('changes the max attempts of a mapping from its row', async () => {
    signInWithGrantedRoles(['ROLE_ADMIN', 'ROLE_CONFIGURATION_MANAGE'])
    stubFetchResponses(
      jsonResponse(200, [specificMapping]),
      jsonResponse(200, { ...specificMapping, maxAttempts: 8 }),
    )
    const wrapper = await mountMappingsView()

    await clickRowButton('Max attempts')
    await page.find('.v-dialog input[type="number"]').setValue('8')
    await flushPromises()
    await page.find('.v-dialog form').trigger('submit')
    await flushPromises()

    expect(fetch).toHaveBeenLastCalledWith('/api/mappings/1', {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ maxAttempts: 8 }),
    })
    expect(wrapper.findAll('tbody tr td').map((cell) => cell.text())).toContain('8')
  })

  it('does not send max attempts out of range', async () => {
    signInWithGrantedRoles(['ROLE_ADMIN', 'ROLE_CONFIGURATION_MANAGE'])
    stubFetchResponses(jsonResponse(200, [specificMapping]))
    await mountMappingsView()

    await clickRowButton('Max attempts')
    await page.find('.v-dialog input[type="number"]').setValue('11')
    await flushPromises()
    await page.find('.v-dialog form').trigger('submit')
    await flushPromises()

    expect(fetch).toHaveBeenCalledTimes(1)
    expect(page.text()).toContain('Between 1 and 10 attempts.')
  })

  it('keeps the dialog open and explains when the max attempts change fails', async () => {
    signInWithGrantedRoles(['ROLE_ADMIN', 'ROLE_CONFIGURATION_MANAGE'])
    stubFetchResponses(jsonResponse(200, [specificMapping]), jsonResponse(500, {}))
    const wrapper = await mountMappingsView()

    await clickRowButton('Max attempts')
    await page.find('.v-dialog form').trigger('submit')
    await flushPromises()

    expect(page.text()).toContain('Unable to change the max attempts')
    expect(wrapper.findAll('tbody tr td').map((cell) => cell.text())).toContain('3')
  })
})
