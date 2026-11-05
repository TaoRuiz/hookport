<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { listSources, type Source } from '@/api/sourcesApi'

const sources = ref<Source[]>([])
const isShowingDisabledSources = ref(false)
const isLoading = ref(true)
const loadingErrorMessage = ref<string | null>(null)

const visibleSources = computed(() =>
  isShowingDisabledSources.value ? sources.value : sources.value.filter((source) => source.enabled),
)

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
    <h1 class="text-h4 mb-4">Sources</h1>

    <v-switch
      v-model="isShowingDisabledSources"
      label="Show disabled sources"
      color="primary"
      hide-details
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
        </tr>
      </tbody>
    </v-table>
  </v-container>
</template>
