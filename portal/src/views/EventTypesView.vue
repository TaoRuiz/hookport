<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { listEventTypes, type EventType } from '@/api/eventTypesApi'

const eventTypes = ref<EventType[]>([])
const isShowingDisabledEventTypes = ref(false)
const isLoading = ref(true)
const loadingErrorMessage = ref<string | null>(null)

const visibleEventTypes = computed(() =>
  isShowingDisabledEventTypes.value
    ? eventTypes.value
    : eventTypes.value.filter((eventType) => eventType.enabled),
)

onMounted(async () => {
  try {
    eventTypes.value = await listEventTypes()
  } catch {
    loadingErrorMessage.value = 'Unable to load event types, please try again later.'
  } finally {
    isLoading.value = false
  }
})
</script>

<template>
  <v-container>
    <h1 class="text-h4 mb-4">Event types</h1>

    <v-switch
      v-model="isShowingDisabledEventTypes"
      label="Show disabled event types"
      color="primary"
      hide-details
    />

    <v-alert v-if="loadingErrorMessage" type="error" :text="loadingErrorMessage" />
    <v-progress-linear v-else-if="isLoading" indeterminate />
    <p v-else-if="visibleEventTypes.length === 0" class="text-medium-emphasis">
      No event type to show.
    </p>

    <v-table v-else>
      <thead>
        <tr>
          <th>Label</th>
          <th>Slug</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="eventType in visibleEventTypes" :key="eventType.id">
          <td>{{ eventType.label }}</td>
          <td>
            <code>{{ eventType.slug }}</code>
          </td>
          <td>
            <v-chip :color="eventType.enabled ? 'success' : 'grey'" size="small">
              {{ eventType.enabled ? 'Enabled' : 'Disabled' }}
            </v-chip>
          </td>
        </tr>
      </tbody>
    </v-table>
  </v-container>
</template>
