<script setup lang="ts">
import { ref } from 'vue'
import { createEventType, EventTypeSlugAlreadyUsedError, type EventType } from '@/api/eventTypesApi'

const emit = defineEmits<{ created: [eventType: EventType] }>()

const isOpen = ref(false)
const slug = ref('')
const label = ref('')
const isFormValid = ref(false)
const isSubmitting = ref(false)
const errorMessage = ref<string | null>(null)

const slugRules = [
  (value: string) => value.length > 0 || 'Slug is required.',
  (value: string) => value.length <= 100 || 'Slug is at most 100 characters.',
  (value: string) => /^\S+$/.test(value) || 'The slug cannot contain spaces.',
]
const labelRules = [
  (value: string) => value.trim().length > 0 || 'Label is required.',
  (value: string) => value.length <= 255 || 'Label is at most 255 characters.',
]

function openEmptyForm(): void {
  slug.value = ''
  label.value = ''
  errorMessage.value = null
  isOpen.value = true
}

async function submitEventTypeCreation(): Promise<void> {
  if (!isFormValid.value) {
    return
  }

  isSubmitting.value = true
  errorMessage.value = null

  try {
    emit('created', await createEventType(slug.value, label.value))
    isOpen.value = false
  } catch (error) {
    errorMessage.value =
      error instanceof EventTypeSlugAlreadyUsedError
        ? error.message
        : 'Unable to create the event type, please try again later.'
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <v-btn color="primary" prepend-icon="mdi-plus" @click="openEmptyForm">New event type</v-btn>

  <v-dialog v-model="isOpen" max-width="560">
    <v-card title="New event type">
      <v-form v-model="isFormValid" @submit.prevent="submitEventTypeCreation">
        <v-card-text>
          <v-text-field
            v-model="slug"
            label="Slug"
            hint="Exactly the type sent by emitters, e.g. record.created. It cannot be changed later."
            persistent-hint
            :rules="slugRules"
            class="mb-2"
          />
          <v-text-field v-model="label" label="Label" :rules="labelRules" />
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
