<script setup lang="ts">
import { ref } from 'vue'
import { fetchSourceSecret, type Source } from '@/api/sourcesApi'
import SecretField from '@/components/SecretField.vue'

const props = defineProps<{ source: Source }>()

const isOpen = ref(false)
const secret = ref<string | null>(null)
const errorMessage = ref<string | null>(null)

async function openAndLoadSecret(): Promise<void> {
  secret.value = null
  errorMessage.value = null
  isOpen.value = true

  try {
    secret.value = await fetchSourceSecret(props.source.id)
  } catch {
    errorMessage.value = 'Unable to load the secret, please try again later.'
  }
}
</script>

<template>
  <v-btn variant="text" size="small" prepend-icon="mdi-key" @click="openAndLoadSecret">
    Show secret
  </v-btn>

  <v-dialog v-model="isOpen" max-width="560">
    <v-card :title="`Secret of ${source.slug}`">
      <v-card-text>
        <v-alert v-if="errorMessage" type="error" :text="errorMessage" />
        <SecretField v-else-if="secret" :secret="secret" />
        <v-progress-linear v-else indeterminate />
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn @click="isOpen = false">Close</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
