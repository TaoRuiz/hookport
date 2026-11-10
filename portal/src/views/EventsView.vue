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

let nextRefreshTimeoutId: ReturnType<typeof setTimeout> | undefined
let isUnmounted = false

// The next refresh is scheduled once the current one ends, so slow answers never overlap.
async function loadEventsThenScheduleNextRefresh(): Promise<void> {
  try {
    receivedEvents.value = await listEvents()
    loadingErrorMessage.value = null
  } catch {
    if (isLoading.value) {
      loadingErrorMessage.value = 'Unable to load events, please try again later.'
    }
  } finally {
    isLoading.value = false
    if (!isUnmounted) {
      nextRefreshTimeoutId = setTimeout(
        loadEventsThenScheduleNextRefresh,
        refreshDelayInMilliseconds,
      )
    }
  }
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

    <v-alert v-if="loadingErrorMessage" type="error" :text="loadingErrorMessage" />
    <v-progress-linear v-else-if="isLoading" indeterminate />
    <p v-else-if="receivedEvents.length === 0" class="text-medium-emphasis">
      No event received yet.
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
