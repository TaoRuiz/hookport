import { beforeEach, describe, expect, it } from 'vitest'
import { nextTick } from 'vue'
import { createPinia, setActivePinia } from 'pinia'
import { useUserPreferencesStore } from '@/stores/userPreferences'

const USER_PREFERENCES_STORAGE_KEY = 'hookport.userPreferences'

describe('userPreferences store', () => {
  beforeEach(() => {
    localStorage.clear()
    setActivePinia(createPinia())
  })

  it('shows the navigation expanded when nothing is stored', () => {
    expect(useUserPreferencesStore().isNavigationCollapsedToIcons).toBe(false)
  })

  it('restores the stored navigation state', () => {
    localStorage.setItem(
      USER_PREFERENCES_STORAGE_KEY,
      JSON.stringify({ isNavigationCollapsedToIcons: true }),
    )

    expect(useUserPreferencesStore().isNavigationCollapsedToIcons).toBe(true)
  })

  it('stores the navigation state when it is toggled', async () => {
    useUserPreferencesStore().toggleNavigationCollapsedToIcons()
    await nextTick()

    expect(JSON.parse(localStorage.getItem(USER_PREFERENCES_STORAGE_KEY)!)).toEqual({
      isNavigationCollapsedToIcons: true,
    })
  })

  it('falls back to defaults when the stored value is unreadable', () => {
    localStorage.setItem(USER_PREFERENCES_STORAGE_KEY, 'not json')

    expect(useUserPreferencesStore().isNavigationCollapsedToIcons).toBe(false)
  })
})
