<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { changeTargetEnabled, listTargets, type CreatedTarget, type Target } from '@/api/targetsApi'
import TargetCreationDialog from '@/components/TargetCreationDialog.vue'
import TargetSecretDialog from '@/components/TargetSecretDialog.vue'
import { useAuthenticatedUserStore } from '@/stores/authenticatedUser'

const authenticatedUserStore = useAuthenticatedUserStore()

const targets = ref<Target[]>([])
const isShowingDisabledTargets = ref(false)
const isLoading = ref(true)
const loadingErrorMessage = ref<string | null>(null)
const statusChangeErrorMessage = ref<string | null>(null)

const visibleTargets = computed(() =>
  isShowingDisabledTargets.value ? targets.value : targets.value.filter((target) => target.enabled),
)

function addCreatedTargetToList(createdTarget: CreatedTarget): void {
  const target: Target = {
    id: createdTarget.id,
    name: createdTarget.name,
    protocol: createdTarget.protocol,
    config: createdTarget.config,
    enabled: createdTarget.enabled,
  }
  targets.value = [...targets.value, target].sort((first, second) =>
    first.name.localeCompare(second.name),
  )
}

function replaceTargetInList(changedTarget: Target): void {
  targets.value = targets.value
    .map((listedTarget) => (listedTarget.id === changedTarget.id ? changedTarget : listedTarget))
    .sort((first, second) => first.name.localeCompare(second.name))
}

async function switchTargetEnabled(target: Target, enabled: boolean): Promise<void> {
  statusChangeErrorMessage.value = null

  try {
    replaceTargetInList(await changeTargetEnabled(target.id, enabled))
  } catch {
    statusChangeErrorMessage.value = `Unable to change the status of ${target.name}, please try again later.`
  }
}

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
      <v-spacer />
      <TargetCreationDialog
        v-if="authenticatedUserStore.canManageConfiguration"
        @created="addCreatedTargetToList"
      />
    </div>

    <v-switch
      v-model="isShowingDisabledTargets"
      label="Show disabled targets"
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
    <p v-else-if="visibleTargets.length === 0" class="text-medium-emphasis">No target to show.</p>

    <v-table v-else>
      <thead>
        <tr>
          <th>Name</th>
          <th>Protocol</th>
          <th>URL</th>
          <th>Status</th>
          <th v-if="authenticatedUserStore.canManageConfiguration">Actions</th>
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
          <td v-if="authenticatedUserStore.canManageConfiguration">
            <div class="d-flex align-center ga-2">
              <v-switch
                :model-value="target.enabled"
                :aria-label="`Enable ${target.name}`"
                color="success"
                density="compact"
                hide-details
                @update:model-value="switchTargetEnabled(target, Boolean($event))"
              />
              <TargetSecretDialog :target="target" />
            </div>
          </td>
        </tr>
      </tbody>
    </v-table>
  </v-container>
</template>
