import { afterEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createVuetify } from 'vuetify'
import SourcesView from '@/views/SourcesView.vue'

const enabledSource = { id: 1, slug: 'emitter-app', name: 'Emitter app', enabled: true }
const disabledSource = { id: 2, slug: 'retired-app', name: 'Retired app', enabled: false }

function stubFetchResponse(status: number, body: unknown): void {
  vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response(JSON.stringify(body), { status })))
}

async function mountSourcesView() {
  const wrapper = mount(SourcesView, { global: { plugins: [createVuetify()] } })
  await flushPromises()

  return wrapper
}

describe('SourcesView', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('loads the sources from the API', async () => {
    stubFetchResponse(200, [enabledSource])

    const wrapper = await mountSourcesView()

    expect(fetch).toHaveBeenCalledWith('/api/sources')
    expect(wrapper.text()).toContain('Emitter app')
    expect(wrapper.text()).toContain('emitter-app')
    expect(wrapper.text()).toContain('Enabled')
  })

  it('hides disabled sources by default', async () => {
    stubFetchResponse(200, [enabledSource, disabledSource])

    const wrapper = await mountSourcesView()

    expect(wrapper.text()).not.toContain('Retired app')
  })

  it('shows disabled sources when the toggle is on', async () => {
    stubFetchResponse(200, [enabledSource, disabledSource])
    const wrapper = await mountSourcesView()

    await wrapper.find('input[type="checkbox"]').setValue(true)

    expect(wrapper.text()).toContain('Retired app')
    expect(wrapper.text()).toContain('Disabled')
  })

  it('says so when there is no source to show', async () => {
    stubFetchResponse(200, [disabledSource])

    const wrapper = await mountSourcesView()

    expect(wrapper.text()).toContain('No source to show.')
  })

  it('shows an error when the sources cannot be loaded', async () => {
    stubFetchResponse(500, {})

    const wrapper = await mountSourcesView()

    expect(wrapper.text()).toContain('Unable to load sources')
  })
})
