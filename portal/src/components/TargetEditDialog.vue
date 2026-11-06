<script setup lang="ts">
import { ref } from 'vue'
import { modifyTarget, type Target } from '@/api/targetsApi'
import { targetNameRules, targetUrlRules } from '@/components/targetFormRules'

const props = defineProps<{ target: Target }>()
const emit = defineEmits<{ modified: [target: Target] }>()

const isOpen = ref(false)
const name = ref('')
const url = ref('')
const isFormValid = ref(false)
const isSubmitting = ref(false)
const errorMessage = ref<string | null>(null)

function openFormWithCurrentValues(): void {
  name.value = props.target.name
  url.value = props.target.config.url
  errorMessage.value = null
  isOpen.value = true
}

async function submitTargetModification(): Promise<void> {
  if (!isFormValid.value) {
    return
  }

  isSubmitting.value = true
  errorMessage.value = null

  try {
    emit('modified', await modifyTarget(props.target.id, name.value, url.value))
    isOpen.value = false
  } catch {
    errorMessage.value = 'Unable to modify the target, please try again later.'
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <v-btn variant="text" size="small" prepend-icon="mdi-pencil" @click="openFormWithCurrentValues">
    Edit
  </v-btn>

  <v-dialog v-model="isOpen" max-width="560">
    <v-card :title="`Edit ${target.name}`">
      <v-form v-model="isFormValid" @submit.prevent="submitTargetModification">
        <v-card-text>
          <v-text-field v-model="name" label="Name" :rules="targetNameRules" class="mb-2" />
          <v-text-field v-model="url" label="URL" :rules="targetUrlRules" />
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
