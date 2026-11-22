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
    localStorage.clear()
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

  it('separates the monitoring pages from the configuration pages in the navigation', async () => {
    const wrapper = await mountAppSignedInAsAdmin(router)
    const navigationText = wrapper.find('nav').text()

    expect(navigationText).toMatch(
      /Monitoring\s*Events\s*Configuration\s*Sources\s*Targets\s*Event types\s*Mappings/,
    )
  })

  it('spans the top bar across the full width, with the navigation below it', async () => {
    const wrapper = await mountAppSignedInAsAdmin(router)
    await flushPromises()

    expect(wrapper.find('header').attributes('style')).toContain('left: 0px')
    expect(wrapper.find('header').attributes('style')).toContain('width: calc(100% - 0px)')
    expect(wrapper.find('nav').attributes('style')).toContain('top: 64px')
  })

  it('collapses the navigation to icons and expands it back', async () => {
    const wrapper = await mountAppSignedInAsAdmin(router)
    const toggleButton = wrapper.find('button[aria-label="Collapse or expand the navigation"]')

    await toggleButton.trigger('click')
    expect(wrapper.find('nav').classes()).toContain('v-navigation-drawer--rail')
    expect(wrapper.find('nav').text()).not.toContain('Configuration')

    await toggleButton.trigger('click')
    expect(wrapper.find('nav').classes()).not.toContain('v-navigation-drawer--rail')
    expect(wrapper.find('nav').text()).toContain('Configuration')
  })

  it('opens with the navigation collapsed when that was the last choice', async () => {
    localStorage.setItem(
      'hookport.userPreferences',
      JSON.stringify({ isNavigationCollapsedToIcons: true }),
    )

    const wrapper = await mountAppSignedInAsAdmin(router)

    expect(wrapper.find('nav').classes()).toContain('v-navigation-drawer--rail')
  })

  it('names a navigation icon on hover once the navigation is collapsed', async () => {
    const wrapper = await mountAppSignedInAsAdmin(router)
    await wrapper.find('button[aria-label="Collapse or expand the navigation"]').trigger('click')

    await wrapper.find('nav a[href="/sources"]').trigger('mouseenter')
    await new Promise((resolve) => setTimeout(resolve, 50))

    expect(document.body.querySelector('.v-tooltip.v-overlay--active')?.textContent).toContain(
      'Sources',
    )
  })

  it('logs out with a POST and goes back to the login page', async () => {
    const wrapper = await mountAppSignedInAsAdmin(router)
    stubFetchResponse(204, null)

    const signOutButton = wrapper.findAll('button').find((button) => button.text() === 'Sign out')
    await signOutButton!.trigger('click')
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
