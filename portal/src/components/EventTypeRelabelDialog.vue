<script setup lang="ts">
import { ref } from 'vue'
import { relabelEventType, type EventType } from '@/api/eventTypesApi'

const props = defineProps<{ eventType: EventType }>()
const emit = defineEmits<{ relabeled: [eventType: EventType] }>()

const isOpen = ref(false)
const label = ref('')
const isFormValid = ref(false)
const isSubmitting = ref(false)
const errorMessage = ref<string | null>(null)

const labelRules = [
  (value: string) => value.trim().length > 0 || 'Label is required.',
  (value: string) => value.length <= 255 || 'Label is at most 255 characters.',
]

function openFormWithCurrentLabel(): void {
  label.value = props.eventType.label
  errorMessage.value = null
  isOpen.value = true
}

async function submitEventTypeRelabel(): Promise<void> {
  if (!isFormValid.value) {
    return
  }

  isSubmitting.value = true
  errorMessage.value = null

  try {
    emit('relabeled', await relabelEventType(props.eventType.id, label.value))
    isOpen.value = false
  } catch {
    errorMessage.value = 'Unable to rename the event type, please try again later.'
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <v-btn variant="text" size="small" prepend-icon="mdi-pencil" @click="openFormWithCurrentLabel">
    Rename
  </v-btn>

  <v-dialog v-model="isOpen" max-width="560">
    <v-card :title="`Rename ${eventType.slug}`">
      <v-form v-model="isFormValid" @submit.prevent="submitEventTypeRelabel">
        <v-card-text>
          <v-text-field v-model="label" label="Label" :rules="labelRules" />
          <v-alert v-if="errorMessage" type="error" :text="errorMessage" />
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn @click="isOpen = false">Cancel</v-btn>
          <v-btn type="submit" color="primary" :loading="isSubmitting">Rename</v-btn>
        </v-card-actions>
      </v-form>
    </v-card>
  </v-dialog>
</template>
