<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue'
import {
  replayFailedDelivery,
  replayUnroutedEvent,
  showEvent,
  type EventDelivery,
  type ReceivedEventDetail,
} from '@/api/eventsApi'
import { deliveryStatusDisplays } from '@/components/deliveryStatusDisplays'
import { eventStateDisplays } from '@/components/eventStateDisplays'
import { useAuthenticatedUserStore } from '@/stores/authenticatedUser'

const props = defineProps<{ uuid: string }>()

const authenticatedUserStore = useAuthenticatedUserStore()

const refreshDelayInMilliseconds = 5000

const receivedEvent = ref<ReceivedEventDetail | null>(null)
const loadingErrorMessage = ref<string | null>(null)
const replayErrorMessage = ref<string | null>(null)

function formatDateTime(dateTime: string): string {
  return new Date(dateTime).toLocaleString()
}

async function loadEvent(): Promise<void> {
  receivedEvent.value = await showEvent(props.uuid)
  loadingErrorMessage.value = null
}

let nextRefreshTimeoutId: ReturnType<typeof setTimeout> | undefined
let isUnmounted = false

// The next refresh is scheduled once the current one ends, so slow answers never overlap.
async function loadEventThenScheduleNextRefresh(): Promise<void> {
  try {
    await loadEvent()
  } catch {
    if (!receivedEvent.value) {
      loadingErrorMessage.value = 'Unable to load this event, please try again later.'
    }
  } finally {
    if (!isUnmounted) {
      nextRefreshTimeoutId = setTimeout(
        loadEventThenScheduleNextRefresh,
        refreshDelayInMilliseconds,
      )
    }
  }
}

// After a replay, a failed reload is left to the next scheduled refresh.
async function reloadEventAfterReplay(): Promise<void> {
  try {
    await loadEvent()
  } catch {}
}

async function replayEventThenReload(): Promise<void> {
  replayErrorMessage.value = null

  try {
    await replayUnroutedEvent(props.uuid)
  } catch {
    replayErrorMessage.value = 'Unable to replay this event, please try again later.'
    return
  }

  await reloadEventAfterReplay()
}

async function replayDeliveryThenReload(delivery: EventDelivery): Promise<void> {
  replayErrorMessage.value = null

  try {
    await replayFailedDelivery(delivery.uuid)
  } catch {
    replayErrorMessage.value = `Unable to replay the delivery to ${delivery.target.name}, please try again later.`
    return
  }

  await reloadEventAfterReplay()
}

onMounted(loadEventThenScheduleNextRefresh)

onUnmounted(() => {
  isUnmounted = true
  clearTimeout(nextRefreshTimeoutId)
})
</script>

<template>
  <v-container>
    <h1 class="text-h4 mb-4">Event</h1>

    <v-alert
      v-if="replayErrorMessage"
      type="error"
      class="mb-4"
      closable
      :text="replayErrorMessage"
      @click:close="replayErrorMessage = null"
    />

    <v-alert v-if="loadingErrorMessage" type="error" :text="loadingErrorMessage" />
    <v-progress-linear v-else-if="!receivedEvent" indeterminate />

    <template v-else>
      <v-table class="mb-6">
        <tbody>
          <tr>
            <th>Type</th>
            <td>
              <code>{{ receivedEvent.type }}</code>
            </td>
          </tr>
          <tr>
            <th>Source</th>
            <td>
              <code>{{ receivedEvent.source.slug }}</code>
            </td>
          </tr>
          <tr>
            <th>External id</th>
            <td>{{ receivedEvent.externalId }}</td>
          </tr>
          <tr>
            <th>Received at</th>
            <td>{{ formatDateTime(receivedEvent.receivedAt) }}</td>
          </tr>
          <tr>
            <th>State</th>
            <td>
              <v-chip :color="eventStateDisplays[receivedEvent.state].color" size="small">
                {{ eventStateDisplays[receivedEvent.state].label }}
              </v-chip>
            </td>
          </tr>
        </tbody>
      </v-table>

      <h2 class="text-h6 mb-2">Payload</h2>
      <pre class="mb-6">{{ JSON.stringify(receivedEvent.payload, null, 2) }}</pre>

      <h2 class="text-h6 mb-2">Deliveries</h2>
      <template v-if="receivedEvent.deliveries.length === 0">
        <p class="text-medium-emphasis mb-2">No target matched this event.</p>
        <v-btn
          v-if="authenticatedUserStore.canReplayEvents"
          prepend-icon="mdi-replay"
          @click="replayEventThenReload"
        >
          Replay event
        </v-btn>
      </template>
      <v-table v-else data-test="deliveries">
        <thead>
          <tr>
            <th>Target</th>
            <th>Status</th>
            <th>Attempts</th>
            <th>Next attempt at</th>
            <th v-if="authenticatedUserStore.canReplayEvents">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="delivery in receivedEvent.deliveries" :key="delivery.uuid">
            <td>
              <router-link :to="{ name: 'delivery-detail', params: { uuid: delivery.uuid } }">
                {{ delivery.target.name }}
              </router-link>
            </td>
            <td>
              <v-chip :color="deliveryStatusDisplays[delivery.status].color" size="small">
                {{ deliveryStatusDisplays[delivery.status].label }}
              </v-chip>
            </td>
            <td>{{ delivery.attemptsCount }} / {{ delivery.maxAttempts }}</td>
            <td>{{ delivery.nextAttemptAt ? formatDateTime(delivery.nextAttemptAt) : '' }}</td>
            <td v-if="authenticatedUserStore.canReplayEvents">
              <v-btn
                v-if="delivery.status === 'failed'"
                size="small"
                prepend-icon="mdi-replay"
                @click="replayDeliveryThenReload(delivery)"
              >
                Replay
              </v-btn>
            </td>
          </tr>
        </tbody>
      </v-table>
    </template>
  </v-container>
</template>
