<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { listTargets, type Target } from '@/api/targetsApi'

const targets = ref<Target[]>([])
const isShowingDisabledTargets = ref(false)
const isLoading = ref(true)
const loadingErrorMessage = ref<string | null>(null)

const visibleTargets = computed(() =>
  isShowingDisabledTargets.value ? targets.value : targets.value.filter((target) => target.enabled),
)

onMounted(async () => {
  try {
    targets.value = await listTargets()
  } catch {
    loadingErrorMessage.value = 'Unable to load targets, please try again later.'
  } finally {
    isLoading.value = false
  }
})
</script>

<template>
  <v-container>
    <div class="d-flex align-center mb-4">
      <h1 class="text-h4">Targets</h1>
    </div>

    <v-switch
      v-model="isShowingDisabledTargets"
      label="Show disabled targets"
      color="primary"
      hide-details
    />

    <v-alert v-if="loadingErrorMessage" type="error" :text="loadingErrorMessage" />
    <v-progress-linear v-else-if="isLoading" indeterminate />
    <p v-else-if="visibleTargets.length === 0" class="text-medium-emphasis">No target to show.</p>

    <v-table v-else>
      <thead>
        <tr>
          <th>Name</th>
          <th>Protocol</th>
          <th>URL</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="target in visibleTargets" :key="target.id">
          <td>{{ target.name }}</td>
          <td>{{ target.protocol }}</td>
          <td>
            <code>{{ target.config.url }}</code>
          </td>
          <td>
            <v-chip :color="target.enabled ? 'success' : 'grey'" size="small">
              {{ target.enabled ? 'Enabled' : 'Disabled' }}
            </v-chip>
          </td>
        </tr>
      </tbody>
    </v-table>
  </v-container>
</template>
