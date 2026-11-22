<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthenticatedUserStore } from '@/stores/authenticatedUser'

const authenticatedUserStore = useAuthenticatedUserStore()
const router = useRouter()
const isNavigationCollapsedToIcons = ref(false)

async function logOutAndGoToLogin(): Promise<void> {
  await authenticatedUserStore.logOut()
  await router.push({ name: 'login' })
}
</script>

<template>
  <v-app>
    <v-navigation-drawer
      v-if="authenticatedUserStore.authenticatedUser"
      :rail="isNavigationCollapsedToIcons"
      permanent
    >
      <v-list nav density="compact">
        <v-list-subheader v-if="!isNavigationCollapsedToIcons">Monitoring</v-list-subheader>
        <v-list-item
          :to="{ name: 'events' }"
          prepend-icon="mdi-format-list-bulleted"
          title="Events"
        />
        <v-divider v-if="isNavigationCollapsedToIcons" class="my-2" />
        <v-list-subheader v-else>Configuration</v-list-subheader>
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
      <template #prepend>
        <v-app-bar-nav-icon
          aria-label="Collapse or expand the navigation"
          @click="isNavigationCollapsedToIcons = !isNavigationCollapsedToIcons"
        />
      </template>
      <v-spacer />
      <span class="mr-4">{{ authenticatedUserStore.authenticatedUser.login }}</span>
      <v-btn prepend-icon="mdi-logout" @click="logOutAndGoToLogin">Sign out</v-btn>
    </v-app-bar>
    <v-main>
      <router-view />
    </v-main>
  </v-app>
</template>
