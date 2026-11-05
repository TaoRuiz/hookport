import { describe, it, expect } from 'vitest'

import { mount } from '@vue/test-utils'
import { createVuetify } from 'vuetify'
import App from '../App.vue'

describe('App', () => {
  it('renders the application name inside the Vuetify layout', () => {
    const wrapper = mount(App, { global: { plugins: [createVuetify()] } })

    expect(wrapper.find('.v-application').exists()).toBe(true)
    expect(wrapper.text()).toContain('HookPort')
  })
})
