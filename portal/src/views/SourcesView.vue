<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { changeSourceEnabled, listSources, type CreatedSource, type Source } from '@/api/sourcesApi'
import SourceCreationDialog from '@/components/SourceCreationDialog.vue'
import SourceSecretDialog from '@/components/SourceSecretDialog.vue'
import { useAuthenticatedUserStore } from '@/stores/authenticatedUser'

const authenticatedUserStore = useAuthenticatedUserStore()

const sources = ref<Source[]>([])
const isShowingDisabledSources = ref(false)
const isLoading = ref(true)
const loadingErrorMessage = ref<string | null>(null)
const statusChangeErrorMessage = ref<string | null>(null)

const visibleSources = computed(() =>
  isShowingDisabledSources.value ? sources.value : sources.value.filter((source) => source.enabled),
)

function addCreatedSourceToList(createdSource: CreatedSource): void {
  const source: Source = {
    id: createdSource.id,
    slug: createdSource.slug,
    name: createdSource.name,
    enabled: createdSource.enabled,
  }
  sources.value = [...sources.value, source].sort((first, second) =>
    first.name.localeCompare(second.name),
  )
}

async function switchSourceEnabled(source: Source, enabled: boolean): Promise<void> {
  statusChangeErrorMessage.value = null

  try {
    const changedSource = await changeSourceEnabled(source.id, enabled)
    sources.value = sources.value.map((listedSource) =>
      listedSource.id === changedSource.id ? changedSource : listedSource,
    )
  } catch {
    statusChangeErrorMessage.value = `Unable to change the status of ${source.slug}, please try again later.`
  }
}

onMounted(async () => {
  try {
    sources.value = await listSources()
  } catch {
    loadingErrorMessage.value = 'Unable to load sources, please try again later.'
  } finally {
    isLoading.value = false
  }
})
</script>

<template>
  <v-container>
    <div class="d-flex align-center mb-4">
      <h1 class="text-h4">Sources</h1>
      <v-spacer />
      <SourceCreationDialog
        v-if="authenticatedUserStore.canManageConfiguration"
        @created="addCreatedSourceToList"
      />
    </div>

    <v-switch
      v-model="isShowingDisabledSources"
      label="Show disabled sources"
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
    <p v-else-if="visibleSources.length === 0" class="text-medium-emphasis">No source to show.</p>

    <v-table v-else>
      <thead>
        <tr>
          <th>Name</th>
          <th>Slug</th>
          <th>Status</th>
          <th v-if="authenticatedUserStore.canManageConfiguration">Actions</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="source in visibleSources" :key="source.id">
          <td>{{ source.name }}</td>
          <td>
            <code>{{ source.slug }}</code>
          </td>
          <td>
            <v-chip :color="source.enabled ? 'success' : 'grey'" size="small">
              {{ source.enabled ? 'Enabled' : 'Disabled' }}
            </v-chip>
          </td>
          <td v-if="authenticatedUserStore.canManageConfiguration">
            <div class="d-flex align-center ga-2">
              <v-switch
                :model-value="source.enabled"
                :aria-label="`Enable ${source.slug}`"
                color="success"
                density="compact"
                hide-details
                @update:model-value="switchSourceEnabled(source, Boolean($event))"
              />
              <SourceSecretDialog :source="source" />
            </div>
          </td>
        </tr>
      </tbody>
    </v-table>
  </v-container>
</template>
