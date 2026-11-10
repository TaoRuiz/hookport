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
const targetId = ref<number | null>(null)
const maxAttempts = ref(defaultMaxAttempts)
const isFormValid = ref(false)
const isSubmitting = ref(false)
const errorMessage = ref<string | null>(null)

const targetRules = [(value: number | null) => value !== null || 'Target is required.']
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
  targetId.value = null
  maxAttempts.value = defaultMaxAttempts
  errorMessage.value = null
  isOpen.value = true
  await loadEnabledChoices()
}

async function submitMappingCreation(): Promise<void> {
  if (!isFormValid.value || targetId.value === null) {
    return
  }

  isSubmitting.value = true
  errorMessage.value = null

  try {
    emit(
      'created',
      await createMapping({
        sourceId: sourceId.value,
        eventTypeId: eventTypeId.value,
        targetId: targetId.value,
        maxAttempts: maxAttempts.value,
      }),
    )
    isOpen.value = false
  } catch (error) {
    errorMessage.value =
      error instanceof MappingAlreadyExistsError
        ? error.message
        : 'Unable to create the mapping, please try again later.'
  } finally {
    isSubmitting.value = false
  }
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
            v-model="targetId"
            label="Target"
            :items="enabledTargets"
            item-title="name"
            item-value="id"
            :rules="targetRules"
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
