import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { DOMWrapper, flushPromises, mount, type VueWrapper } from '@vue/test-utils'
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

// Dialogs are teleported to the end of <body>, so the whole page is queried, not only the view.
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

async function mountEventTypesView() {
  mountedWrapper = mount(EventTypesView, {
    global: { plugins: [createVuetify()] },
    attachTo: document.body,
  })
  await flushPromises()

  return mountedWrapper
}

async function fillAndSubmitEventTypeCreation(slug: string, label: string) {
  await page
    .findAll('button')
    .find((button) => button.text() === 'New event type')!
    .trigger('click')
  await flushPromises()
  const [slugInput, labelInput] = page.findAll('.v-dialog form input')
  await slugInput!.setValue(slug)
  await labelInput!.setValue(label)
  await flushPromises()
  await page.find('.v-dialog form').trigger('submit')
  await flushPromises()
}

describe('EventTypesView', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    signInWithGrantedRoles(['ROLE_DEMO', 'ROLE_USER'])
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

  it('hides the New event type button from a user who cannot manage configuration', async () => {
    stubFetchResponses(jsonResponse(200, []))

    const wrapper = await mountEventTypesView()

    expect(wrapper.text()).not.toContain('New event type')
  })

  it('creates an event type and adds it to the list sorted by label', async () => {
    signInWithGrantedRoles(['ROLE_ADMIN', 'ROLE_CONFIGURATION_MANAGE'])
    const otherEventType = { id: 3, slug: 'record.updated', label: 'Record updated', enabled: true }
    stubFetchResponses(jsonResponse(200, [otherEventType]), jsonResponse(201, enabledEventType))
    const wrapper = await mountEventTypesView()

    await fillAndSubmitEventTypeCreation('record.created', 'Record created')

    expect(fetch).toHaveBeenLastCalledWith('/api/event-types', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ slug: 'record.created', label: 'Record created' }),
    })
    const labelCells = wrapper.findAll('tbody tr td:first-child').map((cell) => cell.text())
    expect(labelCells).toEqual(['Record created', 'Record updated'])
  })

  it('does not send a slug containing spaces', async () => {
    signInWithGrantedRoles(['ROLE_ADMIN', 'ROLE_CONFIGURATION_MANAGE'])
    stubFetchResponses(jsonResponse(200, []))
    await mountEventTypesView()

    await fillAndSubmitEventTypeCreation('record created', 'Record created')

    expect(fetch).toHaveBeenCalledTimes(1)
    expect(page.text()).toContain('The slug cannot contain spaces.')
  })

  it('explains when the event type slug is already used', async () => {
    signInWithGrantedRoles(['ROLE_ADMIN', 'ROLE_CONFIGURATION_MANAGE'])
    stubFetchResponses(
      jsonResponse(200, [enabledEventType]),
      jsonResponse(409, { error: 'This slug is already used.' }),
    )
    await mountEventTypesView()

    await fillAndSubmitEventTypeCreation('record.created', 'Record created')

    expect(page.text()).toContain('This slug is already used.')
  })

  it('hides status and rename actions from a user who cannot manage configuration', async () => {
    stubFetchResponses(jsonResponse(200, [enabledEventType]))

    const wrapper = await mountEventTypesView()

    expect(wrapper.find('tbody').text()).not.toContain('Rename')
    expect(wrapper.find('tbody input[type="checkbox"]').exists()).toBe(false)
  })

  it('disables an event type from its row', async () => {
    signInWithGrantedRoles(['ROLE_ADMIN', 'ROLE_CONFIGURATION_MANAGE'])
    stubFetchResponses(
      jsonResponse(200, [enabledEventType]),
      jsonResponse(200, { ...enabledEventType, enabled: false }),
    )
    const wrapper = await mountEventTypesView()
    await wrapper.find('input[aria-label="Show disabled event types"]').setValue(true)

    await wrapper.find('tbody input[type="checkbox"]').setValue(false)
    await flushPromises()

    expect(fetch).toHaveBeenLastCalledWith('/api/event-types/1', {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ enabled: false }),
    })
    expect(wrapper.find('tbody').text()).toContain('Disabled')
  })

  it('keeps the event type status and explains when the status change fails', async () => {
    signInWithGrantedRoles(['ROLE_ADMIN', 'ROLE_CONFIGURATION_MANAGE'])
    stubFetchResponses(jsonResponse(200, [enabledEventType]), jsonResponse(500, {}))
    const wrapper = await mountEventTypesView()

    await wrapper.find('tbody input[type="checkbox"]').setValue(false)
    await flushPromises()

    expect(wrapper.text()).toContain('Unable to change the status of record.created')
    expect(wrapper.find('tbody').text()).toContain('Enabled')
  })

  it('renames an event type from its row and keeps the list sorted by label', async () => {
    signInWithGrantedRoles(['ROLE_ADMIN', 'ROLE_CONFIGURATION_MANAGE'])
    const otherEventType = { id: 3, slug: 'record.updated', label: 'Record updated', enabled: true }
    stubFetchResponses(
      jsonResponse(200, [enabledEventType, otherEventType]),
      jsonResponse(200, { ...enabledEventType, label: 'Zulu record' }),
    )
    const wrapper = await mountEventTypesView()

    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'Rename')!
      .trigger('click')
    await flushPromises()
    await page.find('.v-dialog form input').setValue('Zulu record')
    await flushPromises()
    await page.find('.v-dialog form').trigger('submit')
    await flushPromises()

    expect(fetch).toHaveBeenLastCalledWith('/api/event-types/1', {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ label: 'Zulu record' }),
    })
    const labelCells = wrapper.findAll('tbody tr td:first-child').map((cell) => cell.text())
    expect(labelCells).toEqual(['Record updated', 'Zulu record'])
  })

  it('keeps the dialog open and explains when the rename fails', async () => {
    signInWithGrantedRoles(['ROLE_ADMIN', 'ROLE_CONFIGURATION_MANAGE'])
    stubFetchResponses(jsonResponse(200, [enabledEventType]), jsonResponse(500, {}))
    const wrapper = await mountEventTypesView()

    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'Rename')!
      .trigger('click')
    await flushPromises()
    await page.find('.v-dialog form').trigger('submit')
    await flushPromises()

    expect(page.text()).toContain('Unable to rename the event type')
    expect(wrapper.find('tbody').text()).toContain('Record created')
  })
})
