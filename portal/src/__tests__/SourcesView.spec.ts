import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { DOMWrapper, flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createVuetify } from 'vuetify'
import { useAuthenticatedUserStore } from '@/stores/authenticatedUser'
import SourcesView from '@/views/SourcesView.vue'

const enabledSource = { id: 1, slug: 'emitter-app', name: 'Emitter app', enabled: true }
const disabledSource = { id: 2, slug: 'retired-app', name: 'Retired app', enabled: false }
const generatedSecret = 'a'.repeat(64)

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

async function mountSourcesView() {
  mountedWrapper = mount(SourcesView, {
    global: { plugins: [createVuetify()] },
    attachTo: document.body,
  })
  await flushPromises()

  return mountedWrapper
}

async function fillAndSubmitSourceCreation(slug: string, name: string) {
  await page
    .findAll('button')
    .find((button) => button.text() === 'New source')!
    .trigger('click')
  await flushPromises()
  const [slugInput, nameInput] = page.findAll('.v-dialog form input')
  await slugInput!.setValue(slug)
  await nameInput!.setValue(name)
  await flushPromises()
  await page.find('.v-dialog form').trigger('submit')
  await flushPromises()
}

describe('SourcesView', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    signInWithGrantedRoles(['ROLE_DEMO', 'ROLE_USER'])
  })

  afterEach(() => {
    mountedWrapper?.unmount()
    document.body.innerHTML = ''
    vi.unstubAllGlobals()
  })

  it('loads the sources from the API', async () => {
    stubFetchResponses(jsonResponse(200, [enabledSource]))

    const wrapper = await mountSourcesView()

    expect(fetch).toHaveBeenCalledWith('/api/sources')
    expect(wrapper.text()).toContain('Emitter app')
    expect(wrapper.text()).toContain('emitter-app')
    expect(wrapper.text()).toContain('Enabled')
  })

  it('hides disabled sources by default', async () => {
    stubFetchResponses(jsonResponse(200, [enabledSource, disabledSource]))

    const wrapper = await mountSourcesView()

    expect(wrapper.text()).not.toContain('Retired app')
  })

  it('shows disabled sources when the toggle is on', async () => {
    stubFetchResponses(jsonResponse(200, [enabledSource, disabledSource]))
    const wrapper = await mountSourcesView()

    await wrapper.find('input[type="checkbox"]').setValue(true)

    expect(wrapper.text()).toContain('Retired app')
    expect(wrapper.text()).toContain('Disabled')
  })

  it('says so when there is no source to show', async () => {
    stubFetchResponses(jsonResponse(200, [disabledSource]))

    const wrapper = await mountSourcesView()

    expect(wrapper.text()).toContain('No source to show.')
  })

  it('shows an error when the sources cannot be loaded', async () => {
    stubFetchResponses(jsonResponse(500, {}))

    const wrapper = await mountSourcesView()

    expect(wrapper.text()).toContain('Unable to load sources')
  })

  it('hides the New source button from a user who cannot manage configuration', async () => {
    stubFetchResponses(jsonResponse(200, []))

    const wrapper = await mountSourcesView()

    expect(wrapper.text()).not.toContain('New source')
  })

  it('creates a source, shows its secret and adds it to the list', async () => {
    signInWithGrantedRoles(['ROLE_ADMIN', 'ROLE_CONFIGURATION_MANAGE'])
    stubFetchResponses(
      jsonResponse(200, []),
      jsonResponse(201, { ...enabledSource, secret: generatedSecret }),
    )
    const wrapper = await mountSourcesView()

    await fillAndSubmitSourceCreation('emitter-app', 'Emitter app')

    expect(fetch).toHaveBeenLastCalledWith('/api/sources', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ slug: 'emitter-app', name: 'Emitter app' }),
    })
    expect(page.text()).toContain('Source emitter-app created')
    expect(page.find<HTMLInputElement>('.v-dialog input[readonly]').element.value).toBe(
      generatedSecret,
    )
    expect(wrapper.find('tbody').text()).toContain('Emitter app')
  })

  it('does not send a slug that breaks the format rules', async () => {
    signInWithGrantedRoles(['ROLE_ADMIN', 'ROLE_CONFIGURATION_MANAGE'])
    stubFetchResponses(jsonResponse(200, []))
    await mountSourcesView()

    await fillAndSubmitSourceCreation('Emitter App', 'Emitter app')

    expect(fetch).toHaveBeenCalledTimes(1)
    expect(page.text()).toContain('Use lowercase letters, digits and single hyphens only.')
  })

  it('explains when the slug is already used', async () => {
    signInWithGrantedRoles(['ROLE_ADMIN', 'ROLE_CONFIGURATION_MANAGE'])
    stubFetchResponses(
      jsonResponse(200, [enabledSource]),
      jsonResponse(409, { error: 'This slug is already used.' }),
    )
    await mountSourcesView()

    await fillAndSubmitSourceCreation('emitter-app', 'Emitter app')

    expect(page.text()).toContain('This slug is already used.')
    expect(page.text()).not.toContain('Source emitter-app created')
  })

  it('hides status and secret actions from a user who cannot manage configuration', async () => {
    stubFetchResponses(jsonResponse(200, [enabledSource]))

    const wrapper = await mountSourcesView()

    expect(wrapper.find('tbody').text()).not.toContain('Show secret')
    expect(wrapper.find('tbody input[type="checkbox"]').exists()).toBe(false)
  })

  it('disables a source from its row', async () => {
    signInWithGrantedRoles(['ROLE_ADMIN', 'ROLE_CONFIGURATION_MANAGE'])
    stubFetchResponses(
      jsonResponse(200, [enabledSource]),
      jsonResponse(200, { ...enabledSource, enabled: false }),
    )
    const wrapper = await mountSourcesView()
    await wrapper.find('input[aria-label="Show disabled sources"]').setValue(true)

    await wrapper.find('tbody input[type="checkbox"]').setValue(false)
    await flushPromises()

    expect(fetch).toHaveBeenLastCalledWith('/api/sources/1', {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ enabled: false }),
    })
    expect(wrapper.find('tbody').text()).toContain('Disabled')
  })

  it('keeps the source status and explains when the status change fails', async () => {
    signInWithGrantedRoles(['ROLE_ADMIN', 'ROLE_CONFIGURATION_MANAGE'])
    stubFetchResponses(jsonResponse(200, [enabledSource]), jsonResponse(500, {}))
    const wrapper = await mountSourcesView()

    await wrapper.find('tbody input[type="checkbox"]').setValue(false)
    await flushPromises()

    expect(wrapper.text()).toContain('Unable to change the status of emitter-app')
    expect(wrapper.find('tbody').text()).toContain('Enabled')
  })

  it('shows the secret of a source on demand', async () => {
    signInWithGrantedRoles(['ROLE_ADMIN', 'ROLE_CONFIGURATION_MANAGE'])
    stubFetchResponses(
      jsonResponse(200, [enabledSource]),
      jsonResponse(200, { secret: generatedSecret }),
    )
    const wrapper = await mountSourcesView()

    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'Show secret')!
      .trigger('click')
    await flushPromises()

    expect(fetch).toHaveBeenLastCalledWith('/api/sources/1/secret')
    expect(page.text()).toContain('Secret of emitter-app')
    expect(page.find<HTMLInputElement>('.v-dialog input[readonly]').element.value).toBe(
      generatedSecret,
    )
  })

  it('renames a source from its row and keeps the list sorted by name', async () => {
    signInWithGrantedRoles(['ROLE_ADMIN', 'ROLE_CONFIGURATION_MANAGE'])
    const otherSource = { id: 3, slug: 'other-app', name: 'Other app', enabled: true }
    stubFetchResponses(
      jsonResponse(200, [enabledSource, otherSource]),
      jsonResponse(200, { ...enabledSource, name: 'Zulu app' }),
    )
    const wrapper = await mountSourcesView()

    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'Rename')!
      .trigger('click')
    await flushPromises()
    await page.find('.v-dialog form input').setValue('Zulu app')
    await flushPromises()
    await page.find('.v-dialog form').trigger('submit')
    await flushPromises()

    expect(fetch).toHaveBeenLastCalledWith('/api/sources/1', {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ name: 'Zulu app' }),
    })
    const rowNames = wrapper.findAll('tbody tr').map((row) => row.find('td').text())
    expect(rowNames).toEqual(['Other app', 'Zulu app'])
  })

  it('keeps the dialog open and explains when the rename fails', async () => {
    signInWithGrantedRoles(['ROLE_ADMIN', 'ROLE_CONFIGURATION_MANAGE'])
    stubFetchResponses(jsonResponse(200, [enabledSource]), jsonResponse(500, {}))
    const wrapper = await mountSourcesView()

    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'Rename')!
      .trigger('click')
    await flushPromises()
    await page.find('.v-dialog form').trigger('submit')
    await flushPromises()

    expect(page.text()).toContain('Unable to rename the source')
    expect(wrapper.find('tbody').text()).toContain('Emitter app')
  })
})
