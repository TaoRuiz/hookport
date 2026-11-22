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
    <v-navigation-drawer v-if="authenticatedUserStore.authenticatedUser" permanent>
      <v-list nav density="compact">
        <v-list-subheader>Monitoring</v-list-subheader>
        <v-list-item
          :to="{ name: 'events' }"
          prepend-icon="mdi-format-list-bulleted"
          title="Events"
        />
        <v-list-subheader>Configuration</v-list-subheader>
        <v-list-item
          :to="{ name: 'sources' }"
          prepend-icon="mdi-application-export"
          title="Sources"
        />
        <v-list-item
          :to="{ name: 'targets' }"
          prepend-icon="mdi-application-import"
          title="Targets"
        />
        <v-list-item
          :to="{ name: 'event-types' }"
          prepend-icon="mdi-tag-outline"
          title="Event types"
        />
        <v-list-item :to="{ name: 'mappings' }" prepend-icon="mdi-call-split" title="Mappings" />
      </v-list>
    </v-navigation-drawer>
    <v-app-bar v-if="authenticatedUserStore.authenticatedUser" title="HookPort">
      <v-spacer />
      <span class="mr-4">{{ authenticatedUserStore.authenticatedUser.login }}</span>
      <v-btn prepend-icon="mdi-logout" @click="logOutAndGoToLogin">Sign out</v-btn>
    </v-app-bar>
    <v-main>
      <router-view />
    </v-main>
  </v-app>
</template>
