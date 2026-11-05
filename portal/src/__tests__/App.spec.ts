import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createMemoryHistory, type Router } from 'vue-router'
import { createVuetify } from 'vuetify'
import App from '@/App.vue'
import { createAppRouter } from '@/router'
import { useAuthenticatedUserStore } from '@/stores/authenticatedUser'

function stubFetchResponse(status: number, body: unknown): void {
  vi.stubGlobal(
    'fetch',
    vi
      .fn()
      .mockResolvedValue(new Response(status === 204 ? null : JSON.stringify(body), { status })),
  )
}

async function mountAppSignedInAsAdmin(router: Router) {
  stubFetchResponse(200, { login: 'admin', roles: ['ROLE_ADMIN'] })
  await router.push({ name: 'home' })

  return mount(App, { global: { plugins: [createVuetify(), router] } })
}

describe('App', () => {
  let router: Router

  beforeEach(() => {
    setActivePinia(createPinia())
    router = createAppRouter(createMemoryHistory())
  })

  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('shows the signed-in login and a sign out button', async () => {
    const wrapper = await mountAppSignedInAsAdmin(router)

    expect(wrapper.text()).toContain('admin')
    expect(wrapper.text()).toContain('Sign out')
  })

  it('logs out with a POST and goes back to the login page', async () => {
    const wrapper = await mountAppSignedInAsAdmin(router)
    stubFetchResponse(204, null)

    await wrapper.find('button').trigger('click')
    await flushPromises()

    expect(fetch).toHaveBeenCalledWith('/api/logout', { method: 'POST' })
    expect(useAuthenticatedUserStore().authenticatedUser).toBeNull()
    expect(router.currentRoute.value.name).toBe('login')
    expect(wrapper.text()).not.toContain('Sign out')
  })

  it('hides the sign out button from an anonymous visitor', async () => {
    stubFetchResponse(401, { error: 'Not authenticated.' })
    await router.push({ name: 'login' })

    const wrapper = mount(App, { global: { plugins: [createVuetify(), router] } })

    expect(wrapper.text()).not.toContain('Sign out')
  })
})
