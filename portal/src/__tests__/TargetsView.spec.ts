import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { DOMWrapper, flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createVuetify } from 'vuetify'
import { useAuthenticatedUserStore } from '@/stores/authenticatedUser'
import TargetsView from '@/views/TargetsView.vue'

const enabledTarget = {
  id: 1,
  name: 'Receiver',
  protocol: 'http',
  config: { url: 'https://receiver.example/hook' },
  enabled: true,
}
const disabledTarget = {
  id: 2,
  name: 'Retired receiver',
  protocol: 'http',
  config: { url: 'https://retired.example/hook' },
  enabled: false,
}

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

async function mountTargetsView() {
  mountedWrapper = mount(TargetsView, {
    global: { plugins: [createVuetify()] },
    attachTo: document.body,
  })
  await flushPromises()

  return mountedWrapper
}

async function fillAndSubmitTargetCreation(name: string, url: string) {
  await page
    .findAll('button')
    .find((button) => button.text() === 'New target')!
    .trigger('click')
  await flushPromises()
  const [nameInput, urlInput] = page.findAll('.v-dialog form input')
  await nameInput!.setValue(name)
  await urlInput!.setValue(url)
  await flushPromises()
  await page.find('.v-dialog form').trigger('submit')
  await flushPromises()
}

describe('TargetsView', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    signInWithGrantedRoles(['ROLE_DEMO', 'ROLE_USER'])
  })

  afterEach(() => {
    mountedWrapper?.unmount()
    document.body.innerHTML = ''
    vi.unstubAllGlobals()
  })

  it('loads the targets from the API', async () => {
    stubFetchResponses(jsonResponse(200, [enabledTarget]))

    const wrapper = await mountTargetsView()

    expect(fetch).toHaveBeenCalledWith('/api/targets')
    expect(wrapper.text()).toContain('Receiver')
    expect(wrapper.text()).toContain('http')
    expect(wrapper.text()).toContain('https://receiver.example/hook')
    expect(wrapper.text()).toContain('Enabled')
  })

  it('hides disabled targets by default', async () => {
    stubFetchResponses(jsonResponse(200, [enabledTarget, disabledTarget]))

    const wrapper = await mountTargetsView()

    expect(wrapper.text()).not.toContain('Retired receiver')
  })

  it('shows disabled targets when the toggle is on', async () => {
    stubFetchResponses(jsonResponse(200, [enabledTarget, disabledTarget]))
    const wrapper = await mountTargetsView()

    await wrapper.find('input[type="checkbox"]').setValue(true)

    expect(wrapper.text()).toContain('Retired receiver')
    expect(wrapper.text()).toContain('Disabled')
  })

  it('says so when there is no target to show', async () => {
    stubFetchResponses(jsonResponse(200, [disabledTarget]))

    const wrapper = await mountTargetsView()

    expect(wrapper.text()).toContain('No target to show.')
  })

  it('shows an error when the targets cannot be loaded', async () => {
    stubFetchResponses(jsonResponse(500, {}))

    const wrapper = await mountTargetsView()

    expect(wrapper.text()).toContain('Unable to load targets')
  })

  it('hides the New target button from a user who cannot manage configuration', async () => {
    stubFetchResponses(jsonResponse(200, []))

    const wrapper = await mountTargetsView()

    expect(wrapper.text()).not.toContain('New target')
  })

  it('creates a target, shows its secret and adds it to the list', async () => {
    signInWithGrantedRoles(['ROLE_ADMIN', 'ROLE_CONFIGURATION_MANAGE'])
    stubFetchResponses(
      jsonResponse(200, []),
      jsonResponse(201, { ...enabledTarget, secret: generatedSecret }),
    )
    const wrapper = await mountTargetsView()

    await fillAndSubmitTargetCreation('Receiver', 'https://receiver.example/hook')

    expect(fetch).toHaveBeenLastCalledWith('/api/targets', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ name: 'Receiver', url: 'https://receiver.example/hook' }),
    })
    expect(page.text()).toContain('Target Receiver created')
    expect(page.find<HTMLInputElement>('.v-dialog input[readonly]').element.value).toBe(
      generatedSecret,
    )
    expect(wrapper.find('tbody').text()).toContain('Receiver')
  })

  it('does not send a URL that is not http or https', async () => {
    signInWithGrantedRoles(['ROLE_ADMIN', 'ROLE_CONFIGURATION_MANAGE'])
    stubFetchResponses(jsonResponse(200, []))
    await mountTargetsView()

    await fillAndSubmitTargetCreation('Receiver', 'ftp://receiver.example/hook')

    expect(fetch).toHaveBeenCalledTimes(1)
    expect(page.text()).toContain('Use a full http:// or https:// URL.')
  })

  it('keeps the form open and explains when the creation fails', async () => {
    signInWithGrantedRoles(['ROLE_ADMIN', 'ROLE_CONFIGURATION_MANAGE'])
    stubFetchResponses(jsonResponse(200, []), jsonResponse(500, {}))
    await mountTargetsView()

    await fillAndSubmitTargetCreation('Receiver', 'https://receiver.example/hook')

    expect(page.text()).toContain('Unable to create the target')
    expect(page.text()).not.toContain('Target Receiver created')
  })

  it('hides status and secret actions from a user who cannot manage configuration', async () => {
    stubFetchResponses(jsonResponse(200, [enabledTarget]))

    const wrapper = await mountTargetsView()

    expect(wrapper.find('tbody').text()).not.toContain('Show secret')
    expect(wrapper.find('tbody input[type="checkbox"]').exists()).toBe(false)
  })

  it('disables a target from its row', async () => {
    signInWithGrantedRoles(['ROLE_ADMIN', 'ROLE_CONFIGURATION_MANAGE'])
    stubFetchResponses(
      jsonResponse(200, [enabledTarget]),
      jsonResponse(200, { ...enabledTarget, enabled: false }),
    )
    const wrapper = await mountTargetsView()
    await wrapper.find('input[aria-label="Show disabled targets"]').setValue(true)

    await wrapper.find('tbody input[type="checkbox"]').setValue(false)
    await flushPromises()

    expect(fetch).toHaveBeenLastCalledWith('/api/targets/1', {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ enabled: false }),
    })
    expect(wrapper.find('tbody').text()).toContain('Disabled')
  })

  it('keeps the target status and explains when the status change fails', async () => {
    signInWithGrantedRoles(['ROLE_ADMIN', 'ROLE_CONFIGURATION_MANAGE'])
    stubFetchResponses(jsonResponse(200, [enabledTarget]), jsonResponse(500, {}))
    const wrapper = await mountTargetsView()

    await wrapper.find('tbody input[type="checkbox"]').setValue(false)
    await flushPromises()

    expect(wrapper.text()).toContain('Unable to change the status of Receiver')
    expect(wrapper.find('tbody').text()).toContain('Enabled')
  })

  it('shows the secret of a target on demand', async () => {
    signInWithGrantedRoles(['ROLE_ADMIN', 'ROLE_CONFIGURATION_MANAGE'])
    stubFetchResponses(
      jsonResponse(200, [enabledTarget]),
      jsonResponse(200, { secret: generatedSecret }),
    )
    const wrapper = await mountTargetsView()

    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'Show secret')!
      .trigger('click')
    await flushPromises()

    expect(fetch).toHaveBeenLastCalledWith('/api/targets/1/secret')
    expect(page.text()).toContain('Secret of Receiver')
    expect(page.find<HTMLInputElement>('.v-dialog input[readonly]').element.value).toBe(
      generatedSecret,
    )
  })

  it('edits the name and URL of a target from its row and keeps the list sorted by name', async () => {
    signInWithGrantedRoles(['ROLE_ADMIN', 'ROLE_CONFIGURATION_MANAGE'])
    const otherTarget = { ...enabledTarget, id: 3, name: 'Other receiver' }
    const modifiedTarget = {
      ...enabledTarget,
      name: 'Zulu receiver',
      config: { url: 'https://zulu.example/hook' },
    }
    stubFetchResponses(
      jsonResponse(200, [enabledTarget, otherTarget]),
      jsonResponse(200, modifiedTarget),
    )
    const wrapper = await mountTargetsView()

    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'Edit')!
      .trigger('click')
    await flushPromises()
    const [nameInput, urlInput] = page.findAll('.v-dialog form input')
    await nameInput!.setValue('Zulu receiver')
    await urlInput!.setValue('https://zulu.example/hook')
    await flushPromises()
    await page.find('.v-dialog form').trigger('submit')
    await flushPromises()

    expect(fetch).toHaveBeenLastCalledWith('/api/targets/1', {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ name: 'Zulu receiver', url: 'https://zulu.example/hook' }),
    })
    const rowNames = wrapper.findAll('tbody tr').map((row) => row.find('td').text())
    expect(rowNames).toEqual(['Other receiver', 'Zulu receiver'])
    expect(wrapper.find('tbody').text()).toContain('https://zulu.example/hook')
  })

  it('keeps the dialog open and explains when the modification fails', async () => {
    signInWithGrantedRoles(['ROLE_ADMIN', 'ROLE_CONFIGURATION_MANAGE'])
    stubFetchResponses(jsonResponse(200, [enabledTarget]), jsonResponse(500, {}))
    const wrapper = await mountTargetsView()

    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'Edit')!
      .trigger('click')
    await flushPromises()
    await page.find('.v-dialog form').trigger('submit')
    await flushPromises()

    expect(page.text()).toContain('Unable to modify the target')
    expect(wrapper.find('tbody').text()).toContain('https://receiver.example/hook')
  })
})
