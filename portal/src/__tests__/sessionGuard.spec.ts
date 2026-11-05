import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { createMemoryHistory, type Router } from 'vue-router'
import { createAppRouter } from '@/router'
import { useAuthenticatedUserStore } from '@/stores/authenticatedUser'

function stubFetchResponse(status: number, body: unknown): void {
  vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response(JSON.stringify(body), { status })))
}

describe('session guard', () => {
  let router: Router

  beforeEach(() => {
    setActivePinia(createPinia())
    router = createAppRouter(createMemoryHistory())
  })

  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('redirects an anonymous visitor to the login page', async () => {
    stubFetchResponse(401, { error: 'Not authenticated.' })

    await router.push({ name: 'home' })

    expect(router.currentRoute.value.name).toBe('login')
  })

  it('restores an existing session and lets the user in', async () => {
    stubFetchResponse(200, { login: 'admin', roles: ['ROLE_ADMIN'] })

    await router.push({ name: 'home' })

    expect(fetch).toHaveBeenCalledWith('/api/me')
    expect(useAuthenticatedUserStore().authenticatedUser?.login).toBe('admin')
    expect(router.currentRoute.value.name).toBe('home')
  })

  it('checks the session only once across navigations', async () => {
    stubFetchResponse(200, { login: 'admin', roles: ['ROLE_ADMIN'] })

    await router.push({ name: 'home' })
    await router.push({ name: 'login' })
    await router.push({ name: 'home' })

    expect(fetch).toHaveBeenCalledTimes(1)
  })

  it('lets an anonymous visitor open the login page', async () => {
    stubFetchResponse(401, { error: 'Not authenticated.' })

    await router.push({ name: 'login' })

    expect(router.currentRoute.value.name).toBe('login')
  })
})
