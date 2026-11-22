<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue'
import { listEvents, type ReceivedEvent } from '@/api/eventsApi'
import { eventStateDisplays } from '@/components/eventStateDisplays'

const refreshDelayInMilliseconds = 5000

const receivedEvents = ref<ReceivedEvent[]>([])
const isLoading = ref(true)
const loadingErrorMessage = ref<string | null>(null)

function formatReceivedAt(receivedAt: string): string {
  return new Date(receivedAt).toLocaleString()
}

// A cleared field holds null.
const searchedText = ref<string | null>('')
const appliedSearchedText = ref('')

let nextRefreshTimeoutId: ReturnType<typeof setTimeout> | undefined
let isUnmounted = false
let latestLoadNumber = 0

// The next refresh is scheduled once the current one ends, so slow answers never overlap.
async function loadEventsThenScheduleNextRefresh(): Promise<void> {
  const loadNumber = ++latestLoadNumber
  const loadedEvents = await listEvents(appliedSearchedText.value).catch(() => null)

  // A newer load started meanwhile (a new search): its answer and its refresh loop win.
  if (loadNumber !== latestLoadNumber || isUnmounted) {
    return
  }

  if (loadedEvents !== null) {
    receivedEvents.value = loadedEvents
    loadingErrorMessage.value = null
  } else if (isLoading.value) {
    loadingErrorMessage.value = 'Unable to load events, please try again later.'
  }

  isLoading.value = false
  nextRefreshTimeoutId = setTimeout(loadEventsThenScheduleNextRefresh, refreshDelayInMilliseconds)
}

function applySearch(): void {
  appliedSearchedText.value = (searchedText.value ?? '').trim()
  clearTimeout(nextRefreshTimeoutId)
  void loadEventsThenScheduleNextRefresh()
}

onMounted(loadEventsThenScheduleNextRefresh)

onUnmounted(() => {
  isUnmounted = true
  clearTimeout(nextRefreshTimeoutId)
})
</script>

<template>
  <v-container>
    <h1 class="text-h4 mb-4">Events</h1>

    <v-form class="mb-4" @submit.prevent="applySearch">
      <v-text-field
        v-model="searchedText"
        label="Search"
        placeholder="External id, type or any text of the payload"
        persistent-placeholder
        prepend-inner-icon="mdi-magnify"
        clearable
        hide-details
        @click:clear="applySearch"
      />
    </v-form>

    <v-alert v-if="loadingErrorMessage" type="error" :text="loadingErrorMessage" />
    <v-progress-linear v-else-if="isLoading" indeterminate />
    <p v-else-if="receivedEvents.length === 0" class="text-medium-emphasis">
      {{ appliedSearchedText === '' ? 'No event received yet.' : 'No event matches this search.' }}
    </p>

    <v-table v-else>
      <thead>
        <tr>
          <th>Received at</th>
          <th>Type</th>
          <th>Source</th>
          <th>External id</th>
          <th>State</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="receivedEvent in receivedEvents" :key="receivedEvent.uuid">
          <td>{{ formatReceivedAt(receivedEvent.receivedAt) }}</td>
          <td>
            <code>{{ receivedEvent.type }}</code>
          </td>
          <td>
            <code>{{ receivedEvent.source.slug }}</code>
          </td>
          <td>
            <router-link :to="{ name: 'event-detail', params: { uuid: receivedEvent.uuid } }">
              {{ receivedEvent.externalId }}
            </router-link>
          </td>
          <td>
            <v-chip :color="eventStateDisplays[receivedEvent.state].color" size="small">
              {{ eventStateDisplays[receivedEvent.state].label }}
            </v-chip>
          </td>
        </tr>
      </tbody>
    </v-table>
  </v-container>
</template>
