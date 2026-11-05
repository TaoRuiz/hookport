<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { InvalidCredentialsError } from '@/api/authenticationApi'
import { useAuthenticatedUserStore } from '@/stores/authenticatedUser'

const authenticatedUserStore = useAuthenticatedUserStore()
const router = useRouter()

const login = ref('')
const password = ref('')
const isSubmitting = ref(false)
const errorMessage = ref<string | null>(null)

async function submitLogin(): Promise<void> {
  isSubmitting.value = true
  errorMessage.value = null

  try {
    await authenticatedUserStore.logIn(login.value, password.value)
    await router.push({ name: 'home' })
  } catch (error) {
    errorMessage.value =
      error instanceof InvalidCredentialsError
        ? error.message
        : 'Unable to sign in right now, please try again later.'
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <v-container class="fill-height" max-width="420">
    <v-card class="w-100" title="Sign in to HookPort">
      <v-card-text>
        <v-form @submit.prevent="submitLogin">
          <v-text-field v-model="login" label="Login" autocomplete="username" required />
          <v-text-field
            v-model="password"
            label="Password"
            type="password"
            autocomplete="current-password"
            required
          />
          <v-alert v-if="errorMessage" type="error" class="mb-4" :text="errorMessage" />
          <v-btn type="submit" color="primary" block :loading="isSubmitting">Sign in</v-btn>
        </v-form>
      </v-card-text>
    </v-card>
  </v-container>
</template>
