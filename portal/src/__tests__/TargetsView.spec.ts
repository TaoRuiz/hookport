import { afterEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import { createVuetify } from 'vuetify'
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

let mountedWrapper: VueWrapper | null = null

function jsonResponse(status: number, body: unknown): Response {
  return new Response(JSON.stringify(body), { status })
}

function stubFetchResponses(...responses: Response[]): void {
  const fetchMock = vi.fn<typeof fetch>()
  responses.forEach((response) => fetchMock.mockResolvedValueOnce(response))
  vi.stubGlobal('fetch', fetchMock)
}

async function mountTargetsView() {
  mountedWrapper = mount(TargetsView, {
    global: { plugins: [createVuetify()] },
    attachTo: document.body,
  })
  await flushPromises()

  return mountedWrapper
}

describe('TargetsView', () => {
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
})
