<script setup lang="ts">
import { ref } from 'vue'
import { createTarget, type CreatedTarget } from '@/api/targetsApi'
import SecretField from '@/components/SecretField.vue'

const emit = defineEmits<{ created: [target: CreatedTarget] }>()

const isOpen = ref(false)
const name = ref('')
const url = ref('')
const isFormValid = ref(false)
const isSubmitting = ref(false)
const errorMessage = ref<string | null>(null)
const createdTarget = ref<CreatedTarget | null>(null)

function isHttpUrl(value: string): boolean {
  if (!URL.canParse(value)) {
    return false
  }

  return ['http:', 'https:'].includes(new URL(value).protocol)
}

const nameRules = [
  (value: string) => value.trim().length > 0 || 'Name is required.',
  (value: string) => value.length <= 255 || 'Name is at most 255 characters.',
]
const urlRules = [
  (value: string) => value.length > 0 || 'URL is required.',
  (value: string) => isHttpUrl(value) || 'Use a full http:// or https:// URL.',
]

function openEmptyForm(): void {
  name.value = ''
  url.value = ''
  errorMessage.value = null
  createdTarget.value = null
  isOpen.value = true
}

async function submitTargetCreation(): Promise<void> {
  if (!isFormValid.value) {
    return
  }

  isSubmitting.value = true
  errorMessage.value = null

  try {
    createdTarget.value = await createTarget(name.value, url.value)
    emit('created', createdTarget.value)
  } catch {
    errorMessage.value = 'Unable to create the target, please try again later.'
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <v-btn color="primary" prepend-icon="mdi-plus" @click="openEmptyForm">New target</v-btn>

  <v-dialog v-model="isOpen" max-width="560">
    <v-card v-if="createdTarget" :title="`Target ${createdTarget.name} created`">
      <v-card-text>
        <p class="mb-4">
          Give this secret to the receiving application. It verifies the signature of every delivery
          with it.
        </p>
        <SecretField :secret="createdTarget.secret" />
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn @click="isOpen = false">Done</v-btn>
      </v-card-actions>
    </v-card>

    <v-card v-else title="New target">
      <v-form v-model="isFormValid" @submit.prevent="submitTargetCreation">
        <v-card-text>
          <v-text-field v-model="name" label="Name" :rules="nameRules" class="mb-2" />
          <v-text-field
            v-model="url"
            label="URL"
            hint="Events are delivered with an HTTP POST to this URL."
            persistent-hint
            :rules="urlRules"
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
