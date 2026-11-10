<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { showEvent, type DeliveryStatus, type ReceivedEventDetail } from '@/api/eventsApi'
import { eventStateDisplays } from '@/components/eventStateDisplays'

const props = defineProps<{ uuid: string }>()

const deliveryStatusDisplays: Record<DeliveryStatus, { label: string; color: string }> = {
  pending: { label: 'Pending', color: 'grey' },
  retrying: { label: 'Retrying', color: 'warning' },
  delivered: { label: 'Delivered', color: 'success' },
  failed: { label: 'Failed', color: 'error' },
}

const receivedEvent = ref<ReceivedEventDetail | null>(null)
const loadingErrorMessage = ref<string | null>(null)

function formatDateTime(dateTime: string): string {
  return new Date(dateTime).toLocaleString()
}

onMounted(async () => {
  try {
    receivedEvent.value = await showEvent(props.uuid)
  } catch {
    loadingErrorMessage.value = 'Unable to load this event, please try again later.'
  }
})
</script>

<template>
  <v-container>
    <h1 class="text-h4 mb-4">Event</h1>

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
      <p v-if="receivedEvent.deliveries.length === 0" class="text-medium-emphasis">
        No target matched this event.
      </p>
      <v-table v-else data-test="deliveries">
        <thead>
          <tr>
            <th>Target</th>
            <th>Status</th>
            <th>Attempts</th>
            <th>Next attempt at</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="delivery in receivedEvent.deliveries" :key="delivery.uuid">
            <td>{{ delivery.target.name }}</td>
            <td>
              <v-chip :color="deliveryStatusDisplays[delivery.status].color" size="small">
                {{ deliveryStatusDisplays[delivery.status].label }}
              </v-chip>
            </td>
            <td>{{ delivery.attemptsCount }} / {{ delivery.maxAttempts }}</td>
            <td>{{ delivery.nextAttemptAt ? formatDateTime(delivery.nextAttemptAt) : '' }}</td>
          </tr>
        </tbody>
      </v-table>
    </template>
  </v-container>
</template>
