import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import {
  fetchAuthenticatedUser,
  logIn as requestLogIn,
  logOut as requestLogOut,
  type AuthenticatedUser,
} from '@/api/authenticationApi'

export const useAuthenticatedUserStore = defineStore('authenticatedUser', () => {
  const authenticatedUser = ref<AuthenticatedUser | null>(null)
  const hasCheckedSession = ref(false)

  const canManageConfiguration = computed(
    () => authenticatedUser.value?.grantedRoles.includes('ROLE_CONFIGURATION_MANAGE') ?? false,
  )

  async function logIn(login: string, password: string): Promise<void> {
    authenticatedUser.value = await requestLogIn(login, password)
    hasCheckedSession.value = true
  }

  async function logOut(): Promise<void> {
    await requestLogOut()
    authenticatedUser.value = null
  }

  async function restoreSessionOnce(): Promise<void> {
    if (hasCheckedSession.value) {
      return
    }

    authenticatedUser.value = await fetchAuthenticatedUser()
    hasCheckedSession.value = true
  }

  return {
    authenticatedUser,
    hasCheckedSession,
    canManageConfiguration,
    logIn,
    logOut,
    restoreSessionOnce,
  }
})
