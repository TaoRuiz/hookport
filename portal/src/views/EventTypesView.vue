<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { changeEventTypeEnabled, listEventTypes, type EventType } from '@/api/eventTypesApi'
import EventTypeCreationDialog from '@/components/EventTypeCreationDialog.vue'
import EventTypeRelabelDialog from '@/components/EventTypeRelabelDialog.vue'
import { useAuthenticatedUserStore } from '@/stores/authenticatedUser'

const authenticatedUserStore = useAuthenticatedUserStore()

const eventTypes = ref<EventType[]>([])
const isShowingDisabledEventTypes = ref(false)
const isLoading = ref(true)
const loadingErrorMessage = ref<string | null>(null)
const statusChangeErrorMessage = ref<string | null>(null)

const visibleEventTypes = computed(() =>
  isShowingDisabledEventTypes.value
    ? eventTypes.value
    : eventTypes.value.filter((eventType) => eventType.enabled),
)

function addCreatedEventTypeToList(createdEventType: EventType): void {
  eventTypes.value = [...eventTypes.value, createdEventType].sort((first, second) =>
    first.label.localeCompare(second.label),
  )
}

function replaceEventTypeInList(changedEventType: EventType): void {
  eventTypes.value = eventTypes.value
    .map((listedEventType) =>
      listedEventType.id === changedEventType.id ? changedEventType : listedEventType,
    )
    .sort((first, second) => first.label.localeCompare(second.label))
}

async function switchEventTypeEnabled(eventType: EventType, enabled: boolean): Promise<void> {
  statusChangeErrorMessage.value = null

  try {
    replaceEventTypeInList(await changeEventTypeEnabled(eventType.id, enabled))
  } catch {
    statusChangeErrorMessage.value = `Unable to change the status of ${eventType.slug}, please try again later.`
  }
}

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
    <div class="d-flex align-center mb-4">
      <h1 class="text-h4">Event types</h1>
      <v-spacer />
      <EventTypeCreationDialog
        v-if="authenticatedUserStore.canManageConfiguration"
        @created="addCreatedEventTypeToList"
      />
    </div>

    <v-switch
      v-model="isShowingDisabledEventTypes"
      label="Show disabled event types"
      color="primary"
      hide-details
    />

    <v-alert
      v-if="statusChangeErrorMessage"
      type="error"
      class="mb-4"
      closable
      :text="statusChangeErrorMessage"
      @click:close="statusChangeErrorMessage = null"
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
          <th v-if="authenticatedUserStore.canManageConfiguration">Actions</th>
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
          <td v-if="authenticatedUserStore.canManageConfiguration">
            <div class="d-flex align-center ga-2">
              <v-switch
                :model-value="eventType.enabled"
                :aria-label="`Enable ${eventType.slug}`"
                color="success"
                density="compact"
                hide-details
                @update:model-value="switchEventTypeEnabled(eventType, Boolean($event))"
              />
              <EventTypeRelabelDialog :event-type="eventType" @relabeled="replaceEventTypeInList" />
            </div>
          </td>
        </tr>
      </tbody>
    </v-table>
  </v-container>
</template>
