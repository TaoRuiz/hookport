<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthenticatedUserStore } from '@/stores/authenticatedUser'

const authenticatedUserStore = useAuthenticatedUserStore()
const router = useRouter()
const isNavigationCollapsedToIcons = ref(false)

const navigationGroups = [
  {
    title: 'Monitoring',
    links: [{ routeName: 'events', icon: 'mdi-format-list-bulleted', title: 'Events' }],
  },
  {
    title: 'Configuration',
    links: [
      { routeName: 'sources', icon: 'mdi-application-export', title: 'Sources' },
      { routeName: 'targets', icon: 'mdi-application-import', title: 'Targets' },
      { routeName: 'event-types', icon: 'mdi-tag-outline', title: 'Event types' },
      { routeName: 'mappings', icon: 'mdi-call-split', title: 'Mappings' },
    ],
  },
]

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
        <template
          v-for="(navigationGroup, groupIndex) in navigationGroups"
          :key="navigationGroup.title"
        >
          <template v-if="isNavigationCollapsedToIcons">
            <v-divider v-if="groupIndex > 0" class="my-2" />
          </template>
          <v-list-subheader v-else>{{ navigationGroup.title }}</v-list-subheader>
          <v-tooltip
            v-for="navigationLink in navigationGroup.links"
            :key="navigationLink.routeName"
            :text="navigationLink.title"
            :disabled="!isNavigationCollapsedToIcons"
            location="end"
          >
            <template #activator="{ props: tooltipActivatorProps }">
              <v-list-item
                v-bind="tooltipActivatorProps"
                :to="{ name: navigationLink.routeName }"
                :prepend-icon="navigationLink.icon"
                :title="navigationLink.title"
              />
            </template>
          </v-tooltip>
        </template>
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
