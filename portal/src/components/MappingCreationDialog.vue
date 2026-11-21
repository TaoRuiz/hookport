<script setup lang="ts">
import { ref } from 'vue'
import { listEventTypes, type EventType } from '@/api/eventTypesApi'
import { createMapping, MappingAlreadyExistsError, type Mapping } from '@/api/mappingsApi'
import { listSources, type Source } from '@/api/sourcesApi'
import { listTargets, type Target } from '@/api/targetsApi'

const emit = defineEmits<{ created: [mapping: Mapping] }>()

const defaultMaxAttempts = 5

const isOpen = ref(false)
const enabledSources = ref<Source[]>([])
const enabledEventTypes = ref<EventType[]>([])
const enabledTargets = ref<Target[]>([])
const isLoadingChoices = ref(false)
const sourceId = ref<number | null>(null)
const eventTypeId = ref<number | null>(null)
const targetIds = ref<number[]>([])
const maxAttempts = ref(defaultMaxAttempts)
const isFormValid = ref(false)
const isSubmitting = ref(false)
const errorMessage = ref<string | null>(null)

const targetRules = [(value: number[]) => value.length > 0 || 'Choose at least one target.']
const maxAttemptsRules = [
  (value: number) =>
    (Number.isInteger(value) && value >= 1 && value <= 10) || 'Between 1 and 10 attempts.',
]

async function loadEnabledChoices(): Promise<void> {
  isLoadingChoices.value = true

  try {
    const [sources, eventTypes, targets] = await Promise.all([
      listSources(),
      listEventTypes(),
      listTargets(),
    ])
    enabledSources.value = sources.filter((source) => source.enabled)
    enabledEventTypes.value = eventTypes.filter((eventType) => eventType.enabled)
    enabledTargets.value = targets.filter((target) => target.enabled)
  } catch {
    errorMessage.value = 'Unable to load the choices, please try again later.'
  } finally {
    isLoadingChoices.value = false
  }
}

async function openEmptyForm(): Promise<void> {
  sourceId.value = null
  eventTypeId.value = null
  targetIds.value = []
  maxAttempts.value = defaultMaxAttempts
  errorMessage.value = null
  isOpen.value = true
  await loadEnabledChoices()
}

function nameTargets(targetIdsToName: number[]): string {
  return enabledTargets.value
    .filter((target) => targetIdsToName.includes(target.id))
    .map((target) => target.name)
    .join(', ')
}

function describeMappingsNotCreated(
  targetIdsAlreadyMapped: number[],
  targetIdsFailed: number[],
): string {
  const explanations: string[] = []

  if (targetIdsAlreadyMapped.length > 0) {
    explanations.push(`A mapping already exists for: ${nameTargets(targetIdsAlreadyMapped)}.`)
  }

  if (targetIdsFailed.length > 0) {
    explanations.push(
      `Unable to create the mapping for: ${nameTargets(targetIdsFailed)}, please try again later.`,
    )
  }

  return explanations.join(' ')
}

async function submitMappingCreation(): Promise<void> {
  if (!isFormValid.value || targetIds.value.length === 0) {
    return
  }

  isSubmitting.value = true
  errorMessage.value = null

  const chosenTargetIds = targetIds.value
  const creationResults = await Promise.allSettled(
    chosenTargetIds.map((targetId) =>
      createMapping({
        sourceId: sourceId.value,
        eventTypeId: eventTypeId.value,
        targetId,
        maxAttempts: maxAttempts.value,
      }),
    ),
  )

  const targetIdsAlreadyMapped: number[] = []
  const targetIdsFailed: number[] = []
  creationResults.forEach((creationResult, index) => {
    if (creationResult.status === 'fulfilled') {
      emit('created', creationResult.value)
      return
    }

    const targetId = chosenTargetIds[index]!
    if (creationResult.reason instanceof MappingAlreadyExistsError) {
      targetIdsAlreadyMapped.push(targetId)
    } else {
      targetIdsFailed.push(targetId)
    }
  })

  isSubmitting.value = false

  if (targetIdsAlreadyMapped.length === 0 && targetIdsFailed.length === 0) {
    isOpen.value = false
    return
  }

  // Keep only the targets left to create, so submitting again does not duplicate the others.
  targetIds.value = [...targetIdsAlreadyMapped, ...targetIdsFailed]
  errorMessage.value = describeMappingsNotCreated(targetIdsAlreadyMapped, targetIdsFailed)
}
</script>

<template>
  <v-btn color="primary" prepend-icon="mdi-plus" @click="openEmptyForm">New mapping</v-btn>

  <v-dialog v-model="isOpen" max-width="560">
    <v-card title="New mapping">
      <v-form v-model="isFormValid" @submit.prevent="submitMappingCreation">
        <v-card-text>
          <v-select
            v-model="sourceId"
            label="Source"
            :items="enabledSources"
            item-title="name"
            item-value="id"
            placeholder="Any source"
            persistent-placeholder
            clearable
            :loading="isLoadingChoices"
            class="mb-2"
          />
          <v-select
            v-model="eventTypeId"
            label="Event type"
            :items="enabledEventTypes"
            item-title="slug"
            item-value="id"
            placeholder="Any event type"
            persistent-placeholder
            clearable
            :loading="isLoadingChoices"
            class="mb-2"
          />
          <v-select
            v-model="targetIds"
            label="Targets"
            :items="enabledTargets"
            item-title="name"
            item-value="id"
            :rules="targetRules"
            multiple
            chips
            closable-chips
            :loading="isLoadingChoices"
            class="mb-2"
          />
          <v-text-field
            v-model.number="maxAttempts"
            label="Max attempts"
            type="number"
            min="1"
            max="10"
            :rules="maxAttemptsRules"
          />
          <v-alert v-if="errorMessage" type="error" :text="errorMessage" />
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn @click="isOpen = false">Cancel</v-btn>
          <v-btn type="submit" color="primary" :loading="isSubmitting">Create</v-btn>
        </v-card-actions>
      </v-form>
    </v-card>
  </v-dialog>
</template>
