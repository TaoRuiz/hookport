import { ref } from 'vue'
import { defineStore } from 'pinia'
import { logIn as requestLogIn, type AuthenticatedUser } from '@/api/authenticationApi'

export const useAuthenticatedUserStore = defineStore('authenticatedUser', () => {
  const authenticatedUser = ref<AuthenticatedUser | null>(null)

  async function logIn(login: string, password: string): Promise<void> {
    authenticatedUser.value = await requestLogIn(login, password)
  }

  return { authenticatedUser, logIn }
})
