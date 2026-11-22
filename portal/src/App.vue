<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useTheme } from 'vuetify'
import { useAuthenticatedUserStore } from '@/stores/authenticatedUser'
import { useUserPreferencesStore } from '@/stores/userPreferences'

const authenticatedUserStore = useAuthenticatedUserStore()
const router = useRouter()
const userPreferencesStore = useUserPreferencesStore()
const theme = useTheme()

watch(
  () => userPreferencesStore.themeName,
  (themeName) => theme.change(themeName ?? 'system'),
  { immediate: true },
)

const isUserMenuOpen = ref(false)

const isDarkThemeDisplayed = computed(() => theme.global.current.value.dark)

function switchToTheOtherTheme(): void {
  userPreferencesStore.chooseTheme(isDarkThemeDisplayed.value ? 'light' : 'dark')
}

const AVATAR_COLOR_BY_ROLE: Record<string, string> = {
  ROLE_ADMIN: 'deep-purple',
  ROLE_DEMO: 'teal',
}
const AVATAR_COLOR_FOR_UNKNOWN_ROLE = 'grey'

// Each user has exactly one role, so the first one is the user's role.
const avatarColor = computed(() => {
  const userRole = authenticatedUserStore.authenticatedUser?.roles[0] ?? ''

  return AVATAR_COLOR_BY_ROLE[userRole] ?? AVATAR_COLOR_FOR_UNKNOWN_ROLE
})

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
    <v-app-bar v-if="authenticatedUserStore.authenticatedUser" title="HookPort">
      <template #prepend>
        <v-app-bar-nav-icon
          aria-label="Collapse or expand the navigation"
          @click="userPreferencesStore.toggleNavigationCollapsedToIcons"
        />
      </template>
      <v-spacer />
      <v-menu v-model="isUserMenuOpen" location="bottom end" offset="8">
        <template #activator="{ props: menuActivatorProps }">
          <v-btn
            v-bind="menuActivatorProps"
            variant="text"
            append-icon="mdi-chevron-down"
            class="text-none mr-2"
          >
            <v-avatar :color="avatarColor" size="32" class="mr-3">
              {{ authenticatedUserStore.authenticatedUser.login.charAt(0).toUpperCase() }}
            </v-avatar>
            {{ authenticatedUserStore.authenticatedUser.login }}
          </v-btn>
        </template>
        <v-card min-width="220" @mouseleave="isUserMenuOpen = false">
          <v-list density="compact" nav>
            <v-list-item
              slim
              prepend-icon="mdi-weather-night"
              title="Dark theme"
              @click="switchToTheOtherTheme"
            >
              <template #append>
                <!-- Readonly: the click reaches the list item, which switches the theme once. -->
                <v-switch
                  :model-value="isDarkThemeDisplayed"
                  readonly
                  hide-details
                  density="compact"
                  color="primary"
                  aria-label="Dark theme"
                />
              </template>
            </v-list-item>
            <v-divider class="my-1" />
            <v-list-item
              slim
              prepend-icon="mdi-logout"
              title="Sign out"
              @click="logOutAndGoToLogin"
            />
          </v-list>
        </v-card>
      </v-menu>
    </v-app-bar>
    <v-navigation-drawer
      v-if="authenticatedUserStore.authenticatedUser"
      :rail="userPreferencesStore.isNavigationCollapsedToIcons"
      permanent
    >
      <v-list nav density="compact">
        <template
          v-for="(navigationGroup, groupIndex) in navigationGroups"
          :key="navigationGroup.title"
        >
          <template v-if="userPreferencesStore.isNavigationCollapsedToIcons">
            <v-divider v-if="groupIndex > 0" class="my-2" />
          </template>
          <v-list-subheader v-else>{{ navigationGroup.title }}</v-list-subheader>
          <v-tooltip
            v-for="navigationLink in navigationGroup.links"
            :key="navigationLink.routeName"
            :text="navigationLink.title"
            :disabled="!userPreferencesStore.isNavigationCollapsedToIcons"
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
      <template #append>
        <v-divider />
        <v-list nav density="compact">
          <v-tooltip
            text="Sign out"
            :disabled="!userPreferencesStore.isNavigationCollapsedToIcons"
            location="end"
          >
            <template #activator="{ props: tooltipActivatorProps }">
              <v-list-item
                v-bind="tooltipActivatorProps"
                prepend-icon="mdi-logout"
                title="Sign out"
                @click="logOutAndGoToLogin"
              />
            </template>
          </v-tooltip>
        </v-list>
      </template>
    </v-navigation-drawer>
    <v-main>
      <router-view />
    </v-main>
  </v-app>
</template>
