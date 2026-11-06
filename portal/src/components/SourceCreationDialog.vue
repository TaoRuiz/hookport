<script setup lang="ts">
import { ref } from 'vue'
import { createSource, SlugAlreadyUsedError, type CreatedSource } from '@/api/sourcesApi'
import SecretField from '@/components/SecretField.vue'

const emit = defineEmits<{ created: [source: CreatedSource] }>()

const isOpen = ref(false)
const slug = ref('')
const name = ref('')
const isFormValid = ref(false)
const isSubmitting = ref(false)
const errorMessage = ref<string | null>(null)
const createdSource = ref<CreatedSource | null>(null)

const slugRules = [
  (value: string) => value.length > 0 || 'Slug is required.',
  (value: string) => value.length <= 100 || 'Slug is at most 100 characters.',
  (value: string) =>
    /^[a-z0-9]+(-[a-z0-9]+)*$/.test(value) ||
    'Use lowercase letters, digits and single hyphens only.',
]
const nameRules = [
  (value: string) => value.trim().length > 0 || 'Name is required.',
  (value: string) => value.length <= 255 || 'Name is at most 255 characters.',
]

function openEmptyForm(): void {
  slug.value = ''
  name.value = ''
  errorMessage.value = null
  createdSource.value = null
  isOpen.value = true
}

async function submitSourceCreation(): Promise<void> {
  if (!isFormValid.value) {
    return
  }

  isSubmitting.value = true
  errorMessage.value = null

  try {
    createdSource.value = await createSource(slug.value, name.value)
    emit('created', createdSource.value)
  } catch (error) {
    errorMessage.value =
      error instanceof SlugAlreadyUsedError
        ? error.message
        : 'Unable to create the source, please try again later.'
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <v-btn color="primary" prepend-icon="mdi-plus" @click="openEmptyForm">New source</v-btn>

  <v-dialog v-model="isOpen" max-width="560">
    <v-card v-if="createdSource" :title="`Source ${createdSource.slug} created`">
      <v-card-text>
        <p class="mb-4">
          Give this secret to the emitting application. It signs every event it sends with it.
        </p>
        <SecretField :secret="createdSource.secret" />
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn @click="isOpen = false">Done</v-btn>
      </v-card-actions>
    </v-card>

    <v-card v-else title="New source">
      <v-form v-model="isFormValid" @submit.prevent="submitSourceCreation">
        <v-card-text>
          <v-text-field
            v-model="slug"
            label="Slug"
            hint="Sent by the emitter in X-Source. It cannot be changed later."
            persistent-hint
            :rules="slugRules"
            class="mb-2"
          />
          <v-text-field v-model="name" label="Name" :rules="nameRules" />
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
