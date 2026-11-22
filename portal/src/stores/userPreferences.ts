import { ref, watch } from 'vue'
import { defineStore } from 'pinia'

const USER_PREFERENCES_STORAGE_KEY = 'hookport.userPreferences'

export type ThemeName = 'light' | 'dark'

interface UserPreferences {
  isNavigationCollapsedToIcons: boolean
  /** Null follows the operating system theme. */
  themeName: ThemeName | null
}

const DEFAULT_USER_PREFERENCES: UserPreferences = {
  isNavigationCollapsedToIcons: false,
  themeName: null,
}

function readStoredUserPreferences(): UserPreferences {
  try {
    const storedUserPreferences = localStorage.getItem(USER_PREFERENCES_STORAGE_KEY)
    if (storedUserPreferences === null) {
      return { ...DEFAULT_USER_PREFERENCES }
    }

    return { ...DEFAULT_USER_PREFERENCES, ...JSON.parse(storedUserPreferences) }
  } catch {
    // Unreadable or blocked storage must never break the portal: fall back to defaults.
    return { ...DEFAULT_USER_PREFERENCES }
  }
}

function storeUserPreferences(userPreferences: UserPreferences): void {
  try {
    localStorage.setItem(USER_PREFERENCES_STORAGE_KEY, JSON.stringify(userPreferences))
  } catch {
    // Blocked storage (private browsing, quota): the preference just lasts for this visit.
  }
}

export const useUserPreferencesStore = defineStore('userPreferences', () => {
  const storedUserPreferences = readStoredUserPreferences()
  const isNavigationCollapsedToIcons = ref(storedUserPreferences.isNavigationCollapsedToIcons)
  const themeName = ref(storedUserPreferences.themeName)

  watch([isNavigationCollapsedToIcons, themeName], () => {
    storeUserPreferences({
      isNavigationCollapsedToIcons: isNavigationCollapsedToIcons.value,
      themeName: themeName.value,
    })
  })

  function toggleNavigationCollapsedToIcons(): void {
    isNavigationCollapsedToIcons.value = !isNavigationCollapsedToIcons.value
  }

  function chooseTheme(chosenThemeName: ThemeName): void {
    themeName.value = chosenThemeName
  }

  return { isNavigationCollapsedToIcons, themeName, toggleNavigationCollapsedToIcons, chooseTheme }
})
