import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { enableAutoUnmount, flushPromises, mount } from '@vue/test-utils'
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

// Menus and tooltips render outside the wrapper: unmounting removes them before the next test.
enableAutoUnmount(afterEach)

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

  it('shows the logo before the app name in the top bar', async () => {
    const wrapper = await mountAppSignedInAsAdmin(router)

    expect(wrapper.find('header img[src="/logo.svg"]').exists()).toBe(true)
  })

  it('shows an avatar with the uppercase initial of the signed-in login', async () => {
    const wrapper = await mountAppSignedInAsAdmin(router)

    expect(wrapper.find('header .v-avatar').text()).toBe('A')
  })

  it.each([
    ['ROLE_ADMIN', 'bg-deep-purple'],
    ['ROLE_DEMO', 'bg-teal'],
    ['ROLE_UNKNOWN', 'bg-grey'],
  ])('colors the avatar of a %s user with %s', async (role, expectedColorClass) => {
    stubFetchResponse(200, { login: 'someone', roles: [role], grantedRoles: [role] })
    await router.push({ name: 'home' })

    const wrapper = mount(App, { global: { plugins: [createVuetify(), router] } })

    expect(wrapper.find('header .v-avatar').classes()).toContain(expectedColorClass)
  })

  it('shows a chevron on the user badge to signal its menu', async () => {
    const wrapper = await mountAppSignedInAsAdmin(router)

    expect(wrapper.find('header .v-btn:has(.v-avatar) .mdi-chevron-down').exists()).toBe(true)
  })

  it('opens a user menu offering a theme switch and sign out', async () => {
    const wrapper = await mountAppSignedInAsAdmin(router)

    await wrapper.find('header .v-btn:has(.v-avatar)').trigger('click')
    await flushPromises()

    const userMenu = document.body.querySelector('.v-menu.v-overlay--active')!
    expect(userMenu.textContent).toContain('Dark theme')
    expect(
      userMenu.querySelector<HTMLInputElement>('input[aria-label="Dark theme"]')!.checked,
    ).toBe(false)
    expect(userMenu.textContent).toContain('Sign out')
  })

  it('switches to the dark theme from the user menu and remembers it', async () => {
    const wrapper = await mountAppSignedInAsAdmin(router)

    await wrapper.find('header .v-btn:has(.v-avatar)').trigger('click')
    await flushPromises()
    document.body
      .querySelector<HTMLInputElement>('.v-menu.v-overlay--active input[aria-label="Dark theme"]')!
      .click()
    await flushPromises()

    expect(wrapper.find('.v-application').classes()).toContain('v-theme--dark')
    expect(JSON.parse(localStorage.getItem('hookport.userPreferences')!).themeName).toBe('dark')
  })

  it('closes the user menu once the theme is switched', async () => {
    const wrapper = await mountAppSignedInAsAdmin(router)

    await wrapper.find('header .v-btn:has(.v-avatar)').trigger('click')
    await flushPromises()
    document.body
      .querySelector<HTMLInputElement>('.v-menu.v-overlay--active input[aria-label="Dark theme"]')!
      .click()
    await flushPromises()

    expect(document.body.querySelector('.v-menu.v-overlay--active')).toBeNull()
  })

  it('closes the user menu when the pointer leaves it', async () => {
    const wrapper = await mountAppSignedInAsAdmin(router)

    await wrapper.find('header .v-btn:has(.v-avatar)').trigger('click')
    await flushPromises()
    document.body
      .querySelector('.v-menu.v-overlay--active .v-card')!
      .dispatchEvent(new MouseEvent('mouseleave'))
    await flushPromises()

    expect(document.body.querySelector('.v-menu.v-overlay--active')).toBeNull()
  })

  it('opens with the theme chosen on the last visit', async () => {
    localStorage.setItem('hookport.userPreferences', JSON.stringify({ themeName: 'dark' }))

    const wrapper = await mountAppSignedInAsAdmin(router)

    expect(wrapper.find('.v-application').classes()).toContain('v-theme--dark')
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

    const signOutButton = wrapper
      .findAll('nav .v-list-item')
      .find((listItem) => listItem.text() === 'Sign out')
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
