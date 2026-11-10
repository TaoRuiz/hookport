<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { listMappings, type Mapping } from '@/api/mappingsApi'
import MappingCreationDialog from '@/components/MappingCreationDialog.vue'
import { useAuthenticatedUserStore } from '@/stores/authenticatedUser'

const authenticatedUserStore = useAuthenticatedUserStore()

const mappings = ref<Mapping[]>([])
const isShowingDisabledMappings = ref(false)
const isLoading = ref(true)
const loadingErrorMessage = ref<string | null>(null)

const visibleMappings = computed(() =>
  isShowingDisabledMappings.value
    ? mappings.value
    : mappings.value.filter((mapping) => mapping.enabled),
)

function addCreatedMappingToList(createdMapping: Mapping): void {
  mappings.value = [...mappings.value, createdMapping].sort(
    (first, second) => first.target.name.localeCompare(second.target.name) || first.id - second.id,
  )
}

onMounted(async () => {
  try {
    mappings.value = await listMappings()
  } catch {
    loadingErrorMessage.value = 'Unable to load mappings, please try again later.'
  } finally {
    isLoading.value = false
  }
})
</script>

<template>
  <v-container>
    <div class="d-flex align-center mb-4">
      <h1 class="text-h4">Mappings</h1>
      <v-spacer />
      <MappingCreationDialog
        v-if="authenticatedUserStore.canManageConfiguration"
        @created="addCreatedMappingToList"
      />
    </div>

    <v-switch
      v-model="isShowingDisabledMappings"
      label="Show disabled mappings"
      color="primary"
      hide-details
    />

    <v-alert v-if="loadingErrorMessage" type="error" :text="loadingErrorMessage" />
    <v-progress-linear v-else-if="isLoading" indeterminate />
    <p v-else-if="visibleMappings.length === 0" class="text-medium-emphasis">No mapping to show.</p>

    <v-table v-else>
      <thead>
        <tr>
          <th>Source</th>
          <th>Event type</th>
          <th>Target</th>
          <th>Max attempts</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="mapping in visibleMappings" :key="mapping.id">
          <td>
            <span v-if="mapping.source">{{ mapping.source.name }}</span>
            <em v-else class="text-medium-emphasis">Any source</em>
          </td>
          <td>
            <code v-if="mapping.eventType">{{ mapping.eventType.slug }}</code>
            <em v-else class="text-medium-emphasis">Any event type</em>
          </td>
          <td>{{ mapping.target.name }}</td>
          <td>{{ mapping.maxAttempts }}</td>
          <td>
            <v-chip :color="mapping.enabled ? 'success' : 'grey'" size="small">
              {{ mapping.enabled ? 'Enabled' : 'Disabled' }}
            </v-chip>
          </td>
        </tr>
      </tbody>
    </v-table>
  </v-container>
</template>
