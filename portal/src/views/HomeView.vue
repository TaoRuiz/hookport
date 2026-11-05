<script setup lang="ts">
import { useAuthenticatedUserStore } from '@/stores/authenticatedUser'

const authenticatedUserStore = useAuthenticatedUserStore()
</script>

<template>
  <v-container>
    <h1 class="text-h4">HookPort</h1>
    <p v-if="authenticatedUserStore.authenticatedUser">
      Signed in as {{ authenticatedUserStore.authenticatedUser.login }}
    </p>
  </v-container>
</template>
