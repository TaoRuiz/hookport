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
const EVENTS_PER_PAGE = 25

const receivedEvents = ref<ReceivedEvent[]>([])
const totalEventsCount = ref(0)
const currentPage = ref(1)
const pageCount = computed(() => Math.ceil(totalEventsCount.value / EVENTS_PER_PAGE))
const firstShownEventNumber = computed(() => (currentPage.value - 1) * EVENTS_PER_PAGE + 1)
const lastShownEventNumber = computed(
  () => firstShownEventNumber.value + receivedEvents.value.length - 1,
)
const isLoading = ref(true)
const loadingErrorMessage = ref<string | null>(null)

function formatReceivedAt(receivedAt: string): string {
  return new Date(receivedAt).toLocaleString()
}

// A cleared field holds null.
const searchedText = ref<string | null>('')
const appliedSearchedText = ref('')
// Date inputs give a Date at local midnight, or null once cleared.
const receivedFromDate = ref<Date | null>(null)
const receivedToDate = ref<Date | null>(null)
const chosenType = ref<string | null>(null)
const chosenSourceId = ref<number | null>(null)
const allEventStates = Object.keys(eventStateDisplays) as EventState[]
const chosenStates = ref<EventState[]>([...allEventStates])

// Disabled sources and event types stay listed: their past events remain in the list.
const sources = ref<Source[]>([])
const eventTypes = ref<EventType[]>([])

// The slug is what emitters send: shown under each readable name, it helps match raw events.
function describeSlugAsSubtitle(sourceOrEventType: { slug: string }): { subtitle: string } {
  return { subtitle: sourceOrEventType.slug }
}

function convertLocalDateToIsoString(localDate: Date, daysToAdd = 0): string {
  const midnight = new Date(localDate)
  midnight.setHours(0, 0, 0, 0)
  midnight.setDate(midnight.getDate() + daysToAdd)

  return midnight.toISOString()
}

const eventListFilters = computed<EventListFilters>(() => ({
  search: appliedSearchedText.value,
  receivedFrom:
    receivedFromDate.value === null ? null : convertLocalDateToIsoString(receivedFromDate.value),
  // The chosen "to" day is included: the API excludes receivedBefore, so it gets the next midnight.
  receivedBefore:
    receivedToDate.value === null ? null : convertLocalDateToIsoString(receivedToDate.value, 1),
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
  const loadedEventPage = await listEvents(
    eventListFilters.value,
    currentPage.value,
    EVENTS_PER_PAGE,
  ).catch(() => null)

  // A newer load started meanwhile (new search or filters): its answer and its refresh loop win.
  if (loadNumber !== latestLoadNumber || isUnmounted) {
    return
  }

  if (loadedEventPage !== null) {
    receivedEvents.value = loadedEventPage.events
    totalEventsCount.value = loadedEventPage.totalCount
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

function showPage(page: number): void {
  currentPage.value = page
  reloadEventsNow()
}

// New search or filters: the previous page number would point into a different list.
function showFirstPage(): void {
  showPage(1)
}

function applySearch(): void {
  appliedSearchedText.value = (searchedText.value ?? '').trim()
  showFirstPage()
}

watch([receivedFromDate, receivedToDate, chosenType, chosenSourceId, chosenStates], showFirstPage)

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

    <v-row class="mb-2" dense>
      <v-col cols="12" lg="4">
        <v-form @submit.prevent="applySearch">
          <v-text-field
            v-model="searchedText"
            label="Search"
            placeholder="External id, type or any text of the payload"
            persistent-placeholder
            append-inner-icon="mdi-magnify"
            density="compact"
            variant="outlined"
            clearable
            hide-details
            @click:clear="applySearch"
          />
        </v-form>
      </v-col>
      <v-col cols="12" sm="6" md="3" lg="2">
        <v-date-input
          v-model="receivedFromDate"
          label="Received from"
          prepend-icon=""
          append-inner-icon="mdi-calendar"
          density="compact"
          variant="outlined"
          clearable
          hide-details
        />
      </v-col>
      <v-col cols="12" sm="6" md="3" lg="2">
        <v-date-input
          v-model="receivedToDate"
          label="Received to"
          prepend-icon=""
          append-inner-icon="mdi-calendar"
          density="compact"
          variant="outlined"
          clearable
          hide-details
        />
      </v-col>
      <v-col cols="12" sm="6" md="3" lg="2">
        <v-select
          v-model="chosenType"
          label="Type"
          :items="eventTypes"
          item-title="label"
          item-value="slug"
          :item-props="describeSlugAsSubtitle"
          placeholder="Any type"
          persistent-placeholder
          density="compact"
          variant="outlined"
          clearable
          hide-details
        />
      </v-col>
      <v-col cols="12" sm="6" md="3" lg="2">
        <v-select
          v-model="chosenSourceId"
          label="Source"
          :items="sources"
          item-title="name"
          item-value="id"
          :item-props="describeSlugAsSubtitle"
          placeholder="Any source"
          persistent-placeholder
          density="compact"
          variant="outlined"
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
        size="small"
      >
        {{ eventStateDisplays[eventState].label }}
      </v-chip>
    </v-chip-group>

    <v-alert v-if="loadingErrorMessage" type="error" :text="loadingErrorMessage" />
    <v-progress-linear v-else-if="isLoading" indeterminate />
    <p v-else-if="receivedEvents.length === 0" class="text-medium-emphasis">
      {{ hasActiveFilters ? 'No event matches the search and filters.' : 'No event received yet.' }}
    </p>

    <v-table v-else density="compact">
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
            <template v-if="receivedEvent.type.label">
              <div>{{ receivedEvent.type.label }}</div>
              <code class="text-caption text-disabled">{{ receivedEvent.type.slug }}</code>
            </template>
            <code v-else>{{ receivedEvent.type.slug }}</code>
          </td>
          <td>
            <div>{{ receivedEvent.source.name }}</div>
            <code class="text-caption text-medium-emphasis">{{ receivedEvent.source.slug }}</code>
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

    <div v-if="receivedEvents.length > 0" class="d-flex align-center mt-2">
      <span class="text-body-2 text-medium-emphasis">
        {{ firstShownEventNumber }}–{{ lastShownEventNumber }} of {{ totalEventsCount }}
      </span>
      <v-spacer />
      <v-pagination
        v-if="pageCount > 1"
        :model-value="currentPage"
        :length="pageCount"
        :total-visible="7"
        density="compact"
        @update:model-value="showPage"
      />
    </div>
  </v-container>
</template>
