<script setup lang="ts">
import { useRouter } from 'vue-router'
import { useAuthenticatedUserStore } from '@/stores/authenticatedUser'

const authenticatedUserStore = useAuthenticatedUserStore()
const router = useRouter()

async function logOutAndGoToLogin(): Promise<void> {
  await authenticatedUserStore.logOut()
  await router.push({ name: 'login' })
}
</script>

<template>
  <v-app>
    <v-app-bar v-if="authenticatedUserStore.authenticatedUser" title="HookPort">
      <v-btn :to="{ name: 'sources' }">Sources</v-btn>
      <v-btn :to="{ name: 'targets' }">Targets</v-btn>
      <v-btn :to="{ name: 'event-types' }">Event types</v-btn>
      <v-spacer />
      <span class="mr-4">{{ authenticatedUserStore.authenticatedUser.login }}</span>
      <v-btn prepend-icon="mdi-logout" @click="logOutAndGoToLogin">Sign out</v-btn>
    </v-app-bar>
    <v-main>
      <router-view />
    </v-main>
  </v-app>
</template>
