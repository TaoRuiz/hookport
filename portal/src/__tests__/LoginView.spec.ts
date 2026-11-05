import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createMemoryHistory, createRouter, type Router } from 'vue-router'
import { createVuetify } from 'vuetify'
import { routes } from '@/router'
import { useAuthenticatedUserStore } from '@/stores/authenticatedUser'
import LoginView from '@/views/LoginView.vue'

function respondToLoginWith(status: number, body: unknown): void {
  vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response(JSON.stringify(body), { status })))
}

async function submitLoginForm(router: Router, login: string, password: string) {
  const wrapper = mount(LoginView, {
    global: { plugins: [createVuetify(), router] },
  })
  await wrapper.find('input[autocomplete="username"]').setValue(login)
  await wrapper.find('input[type="password"]').setValue(password)
  await wrapper.find('form').trigger('submit')
  await flushPromises()

  return wrapper
}

describe('LoginView', () => {
  let router: Router

  beforeEach(async () => {
    setActivePinia(createPinia())
    router = createRouter({ history: createMemoryHistory(), routes })
    await router.push({ name: 'login' })
  })

  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('sends the credentials as JSON to the login endpoint', async () => {
    respondToLoginWith(200, { login: 'admin', roles: ['ROLE_ADMIN'] })

    await submitLoginForm(router, 'admin', 'correct-password')

    expect(fetch).toHaveBeenCalledWith('/api/login', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ login: 'admin', password: 'correct-password' }),
    })
  })

  it('remembers the user and goes to the home page after a successful login', async () => {
    respondToLoginWith(200, { login: 'admin', roles: ['ROLE_ADMIN'] })

    await submitLoginForm(router, 'admin', 'correct-password')

    expect(useAuthenticatedUserStore().authenticatedUser?.login).toBe('admin')
    expect(router.currentRoute.value.name).toBe('home')
  })

  it('shows an error and stays on the login page when credentials are rejected', async () => {
    respondToLoginWith(401, { error: 'Invalid credentials.' })

    const wrapper = await submitLoginForm(router, 'admin', 'wrong-password')

    expect(wrapper.text()).toContain('Invalid login or password.')
    expect(useAuthenticatedUserStore().authenticatedUser).toBeNull()
    expect(router.currentRoute.value.name).toBe('login')
  })

  it('shows a generic error when the server fails', async () => {
    respondToLoginWith(500, {})

    const wrapper = await submitLoginForm(router, 'admin', 'correct-password')

    expect(wrapper.text()).toContain('Unable to sign in right now')
    expect(router.currentRoute.value.name).toBe('login')
  })
})
