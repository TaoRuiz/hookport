import { ref } from 'vue'
import { defineStore } from 'pinia'
import {
  fetchAuthenticatedUser,
  logIn as requestLogIn,
  type AuthenticatedUser,
} from '@/api/authenticationApi'

export const useAuthenticatedUserStore = defineStore('authenticatedUser', () => {
  const authenticatedUser = ref<AuthenticatedUser | null>(null)
  const hasCheckedSession = ref(false)

  async function logIn(login: string, password: string): Promise<void> {
    authenticatedUser.value = await requestLogIn(login, password)
    hasCheckedSession.value = true
  }

  async function restoreSessionOnce(): Promise<void> {
    if (hasCheckedSession.value) {
      return
    }

    authenticatedUser.value = await fetchAuthenticatedUser()
    hasCheckedSession.value = true
  }

  return { authenticatedUser, hasCheckedSession, logIn, restoreSessionOnce }
})
