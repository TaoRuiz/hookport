import { afterEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { h } from 'vue'
import { VDateInput } from 'vuetify/components'

async function importVuetifyForBrowserLanguage(browserLanguage: string) {
  vi.spyOn(navigator, 'language', 'get').mockReturnValue(browserLanguage)
  vi.resetModules()

  return (await import('@/plugins/vuetify')).default
}

describe('vuetify plugin', () => {
  afterEach(() => {
    vi.restoreAllMocks()
  })

  it.each([
    ['fr-FR', 'jj/mm/aaaa'],
    ['en-US', 'mm/dd/yyyy'],
  ])('formats date inputs for a %s browser as %s', async (browserLanguage, expectedDateFormat) => {
    const vuetify = await importVuetifyForBrowserLanguage(browserLanguage)

    const wrapper = mount(() => h(VDateInput), { global: { plugins: [vuetify] } })

    expect(wrapper.find('input').attributes('placeholder')).toBe(expectedDateFormat)
  })
})
