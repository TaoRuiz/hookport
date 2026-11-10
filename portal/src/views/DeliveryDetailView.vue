<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { showDelivery, type DeliveryDetail } from '@/api/deliveriesApi'
import { replayFailedDelivery } from '@/api/eventsApi'
import { deliveryStatusDisplays } from '@/components/deliveryStatusDisplays'
import { useAuthenticatedUserStore } from '@/stores/authenticatedUser'

const props = defineProps<{ uuid: string }>()

const authenticatedUserStore = useAuthenticatedUserStore()

const delivery = ref<DeliveryDetail | null>(null)
const loadingErrorMessage = ref<string | null>(null)
const replayErrorMessage = ref<string | null>(null)

function formatDateTime(dateTime: string): string {
  return new Date(dateTime).toLocaleString()
}

async function loadDelivery(): Promise<void> {
  try {
    delivery.value = await showDelivery(props.uuid)
  } catch {
    loadingErrorMessage.value = 'Unable to load this delivery, please try again later.'
  }
}

async function replayDeliveryThenReload(): Promise<void> {
  replayErrorMessage.value = null

  try {
    await replayFailedDelivery(props.uuid)
  } catch {
    replayErrorMessage.value = 'Unable to replay this delivery, please try again later.'
    return
  }

  await loadDelivery()
}

onMounted(loadDelivery)
</script>

<template>
  <v-container>
    <h1 class="text-h4 mb-4">Delivery</h1>

    <v-alert
      v-if="replayErrorMessage"
      type="error"
      class="mb-4"
      closable
      :text="replayErrorMessage"
      @click:close="replayErrorMessage = null"
    />

    <v-alert v-if="loadingErrorMessage" type="error" :text="loadingErrorMessage" />
    <v-progress-linear v-else-if="!delivery" indeterminate />

    <template v-else>
      <v-table class="mb-6">
        <tbody>
          <tr>
            <th>Event</th>
            <td>
              <router-link :to="{ name: 'event-detail', params: { uuid: delivery.eventUuid } }">
                {{ delivery.eventUuid }}
              </router-link>
            </td>
          </tr>
          <tr>
            <th>Target</th>
            <td>{{ delivery.target.name }}</td>
          </tr>
          <tr>
            <th>Status</th>
            <td>
              <v-chip :color="deliveryStatusDisplays[delivery.status].color" size="small">
                {{ deliveryStatusDisplays[delivery.status].label }}
              </v-chip>
            </td>
          </tr>
          <tr>
            <th>Attempts</th>
            <td>{{ delivery.attemptsCount }} / {{ delivery.maxAttempts }}</td>
          </tr>
          <tr>
            <th>Next attempt at</th>
            <td>{{ delivery.nextAttemptAt ? formatDateTime(delivery.nextAttemptAt) : '' }}</td>
          </tr>
        </tbody>
      </v-table>

      <v-btn
        v-if="authenticatedUserStore.canReplayEvents && delivery.status === 'failed'"
        class="mb-6"
        prepend-icon="mdi-replay"
        @click="replayDeliveryThenReload"
      >
        Replay
      </v-btn>

      <h2 class="text-h6 mb-2">Attempts</h2>
      <p v-if="delivery.attempts.length === 0" class="text-medium-emphasis">No attempt yet.</p>
      <v-table v-else data-test="attempts">
        <thead>
          <tr>
            <th>#</th>
            <th>Sent at</th>
            <th>Duration</th>
            <th>HTTP code</th>
            <th>Error</th>
            <th>Response excerpt</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="attempt in delivery.attempts" :key="attempt.attemptNo">
            <td>{{ attempt.attemptNo }}</td>
            <td>{{ formatDateTime(attempt.sentAt) }}</td>
            <td>{{ attempt.durationMs }} ms</td>
            <td>{{ attempt.responseCode ?? '' }}</td>
            <td>{{ attempt.error ?? '' }}</td>
            <td>
              <code v-if="attempt.responseExcerpt">{{ attempt.responseExcerpt }}</code>
            </td>
          </tr>
        </tbody>
      </v-table>
    </template>
  </v-container>
</template>
