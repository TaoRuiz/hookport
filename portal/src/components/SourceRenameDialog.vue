<script setup lang="ts">
import { ref } from 'vue'
import { renameSource, type Source } from '@/api/sourcesApi'

const props = defineProps<{ source: Source }>()
const emit = defineEmits<{ renamed: [source: Source] }>()

const isOpen = ref(false)
const name = ref('')
const isFormValid = ref(false)
const isSubmitting = ref(false)
const errorMessage = ref<string | null>(null)

const nameRules = [
  (value: string) => value.trim().length > 0 || 'Name is required.',
  (value: string) => value.length <= 255 || 'Name is at most 255 characters.',
]

function openFormWithCurrentName(): void {
  name.value = props.source.name
  errorMessage.value = null
  isOpen.value = true
}

async function submitSourceRename(): Promise<void> {
  if (!isFormValid.value) {
    return
  }

  isSubmitting.value = true
  errorMessage.value = null

  try {
    emit('renamed', await renameSource(props.source.id, name.value))
    isOpen.value = false
  } catch {
    errorMessage.value = 'Unable to rename the source, please try again later.'
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <v-btn variant="text" size="small" prepend-icon="mdi-pencil" @click="openFormWithCurrentName">
    Rename
  </v-btn>

  <v-dialog v-model="isOpen" max-width="560">
    <v-card :title="`Rename ${source.slug}`">
      <v-form v-model="isFormValid" @submit.prevent="submitSourceRename">
        <v-card-text>
          <v-text-field v-model="name" label="Name" :rules="nameRules" />
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
