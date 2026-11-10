<script setup lang="ts">
import { ref } from 'vue'
import { changeMappingMaxAttempts, type Mapping } from '@/api/mappingsApi'

const props = defineProps<{ mapping: Mapping }>()
const emit = defineEmits<{ changed: [mapping: Mapping] }>()

const isOpen = ref(false)
const maxAttempts = ref(0)
const isFormValid = ref(false)
const isSubmitting = ref(false)
const errorMessage = ref<string | null>(null)

const maxAttemptsRules = [
  (value: number) =>
    (Number.isInteger(value) && value >= 1 && value <= 10) || 'Between 1 and 10 attempts.',
]

function openFormWithCurrentMaxAttempts(): void {
  maxAttempts.value = props.mapping.maxAttempts
  errorMessage.value = null
  isOpen.value = true
}

async function submitMaxAttemptsChange(): Promise<void> {
  if (!isFormValid.value) {
    return
  }

  isSubmitting.value = true
  errorMessage.value = null

  try {
    emit('changed', await changeMappingMaxAttempts(props.mapping.id, maxAttempts.value))
    isOpen.value = false
  } catch {
    errorMessage.value = 'Unable to change the max attempts, please try again later.'
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <v-btn
    variant="text"
    size="small"
    prepend-icon="mdi-pencil"
    @click="openFormWithCurrentMaxAttempts"
  >
    Max attempts
  </v-btn>

  <v-dialog v-model="isOpen" max-width="560">
    <v-card title="Change max attempts">
      <v-form v-model="isFormValid" @submit.prevent="submitMaxAttemptsChange">
        <v-card-text>
          <p class="mb-4">Applies to new deliveries only. Existing deliveries keep their limit.</p>
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
          <v-btn type="submit" color="primary" :loading="isSubmitting">Save</v-btn>
        </v-card-actions>
      </v-form>
    </v-card>
  </v-dialog>
</template>
