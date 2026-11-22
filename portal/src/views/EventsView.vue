<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { listEventTypes, type EventType } from '@/api/eventTypesApi'
import {
  listEvents,
  type EventListFilters,
  type EventState,
  type ReceivedEvent,
} from '@/api/eventsApi'
import { listSources, type Source } from '@/api/sourcesApi'
import { deliveryStatusDisplays } from '@/components/deliveryStatusDisplays'
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
// Dates come from native date inputs: '' or 'YYYY-MM-DD', in the browser time zone.
const receivedFromDate = ref('')
const receivedToDate = ref('')
const chosenType = ref<string | null>(null)
const chosenSourceId = ref<number | null>(null)
const allEventStates = Object.keys(eventStateDisplays) as EventState[]
const chosenStates = ref<EventState[]>([...allEventStates])

// Disabled sources and event types stay listed: their past events remain in the list.
const sources = ref<Source[]>([])
const eventTypes = ref<EventType[]>([])

function convertLocalDateToIsoString(localDate: string, daysToAdd = 0): string {
  const midnight = new Date(`${localDate}T00:00:00`)
  midnight.setDate(midnight.getDate() + daysToAdd)

  return midnight.toISOString()
}

const eventListFilters = computed<EventListFilters>(() => ({
  search: appliedSearchedText.value,
  receivedFrom:
    receivedFromDate.value === '' ? null : convertLocalDateToIsoString(receivedFromDate.value),
  // The chosen "to" day is included: the API excludes receivedBefore, so it gets the next midnight.
  receivedBefore:
    receivedToDate.value === '' ? null : convertLocalDateToIsoString(receivedToDate.value, 1),
  type: chosenType.value,
  sourceId: chosenSourceId.value,
  // Every state chosen means no state filter.
  states: chosenStates.value.length === allEventStates.length ? [] : chosenStates.value,
}))

const hasActiveFilters = computed(() =>
  Object.values(eventListFilters.value).some((filterValue) =>
    Array.isArray(filterValue)
      ? filterValue.length > 0
      : filterValue !== null && filterValue !== '',
  ),
)

let nextRefreshTimeoutId: ReturnType<typeof setTimeout> | undefined
let isUnmounted = false
let latestLoadNumber = 0

// The next refresh is scheduled once the current one ends, so slow answers never overlap.
async function loadEventsThenScheduleNextRefresh(): Promise<void> {
  const loadNumber = ++latestLoadNumber
  const loadedEvents = await listEvents(eventListFilters.value).catch(() => null)

  // A newer load started meanwhile (new search or filters): its answer and its refresh loop win.
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

function reloadEventsNow(): void {
  clearTimeout(nextRefreshTimeoutId)
  void loadEventsThenScheduleNextRefresh()
}

function applySearch(): void {
  appliedSearchedText.value = (searchedText.value ?? '').trim()
  reloadEventsNow()
}

watch([receivedFromDate, receivedToDate, chosenType, chosenSourceId, chosenStates], reloadEventsNow)

// Without the choices, the type and source filters stay empty but the list still works.
async function loadFilterChoices(): Promise<void> {
  const [loadedSources, loadedEventTypes] = await Promise.all([
    listSources().catch(() => []),
    listEventTypes().catch(() => []),
  ])
  sources.value = loadedSources
  eventTypes.value = loadedEventTypes
}

onMounted(() => {
  void loadEventsThenScheduleNextRefresh()
  void loadFilterChoices()
})

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

    <v-row class="mb-4" dense>
      <v-col cols="12" sm="6" md="2">
        <v-text-field v-model="receivedFromDate" label="Received from" type="date" hide-details />
      </v-col>
      <v-col cols="12" sm="6" md="2">
        <v-text-field v-model="receivedToDate" label="Received to" type="date" hide-details />
      </v-col>
      <v-col cols="12" sm="6" md="4">
        <v-select
          v-model="chosenType"
          label="Type"
          :items="eventTypes"
          item-title="slug"
          item-value="slug"
          placeholder="Any type"
          persistent-placeholder
          clearable
          hide-details
        />
      </v-col>
      <v-col cols="12" sm="6" md="4">
        <v-select
          v-model="chosenSourceId"
          label="Source"
          :items="sources"
          item-title="slug"
          item-value="id"
          placeholder="Any source"
          persistent-placeholder
          clearable
          hide-details
        />
      </v-col>
    </v-row>

    <!-- Mandatory: at least one state stays chosen, so the list never filters out everything. -->
    <v-chip-group v-model="chosenStates" class="mb-4" aria-label="States" multiple mandatory filter>
      <v-chip
        v-for="eventState in allEventStates"
        :key="eventState"
        :value="eventState"
        :color="eventStateDisplays[eventState].color"
        variant="outlined"
      >
        {{ eventStateDisplays[eventState].label }}
      </v-chip>
    </v-chip-group>

    <v-alert v-if="loadingErrorMessage" type="error" :text="loadingErrorMessage" />
    <v-progress-linear v-else-if="isLoading" indeterminate />
    <p v-else-if="receivedEvents.length === 0" class="text-medium-emphasis">
      {{ hasActiveFilters ? 'No event matches the search and filters.' : 'No event received yet.' }}
    </p>

    <v-table v-else>
      <thead>
        <tr>
          <th>Received at</th>
          <th>Type</th>
          <th>Source</th>
          <th>External id</th>
          <th>State</th>
          <th>Deliveries</th>
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
          <td>
            <router-link
              v-for="delivery in receivedEvent.deliveries"
              :key="delivery.uuid"
              :to="{ name: 'delivery-detail', params: { uuid: delivery.uuid } }"
              class="mr-1"
            >
              <v-chip
                :color="deliveryStatusDisplays[delivery.status].color"
                :title="`${delivery.target.name}: ${deliveryStatusDisplays[delivery.status].label}`"
                size="small"
              >
                {{ delivery.target.name }}
              </v-chip>
            </router-link>
          </td>
        </tr>
      </tbody>
    </v-table>
  </v-container>
</template>
