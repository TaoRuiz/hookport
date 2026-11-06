<script setup lang="ts">
import { ref } from 'vue'

const props = defineProps<{ secret: string }>()

const hasCopiedSecret = ref(false)

async function copySecretToClipboard(): Promise<void> {
  await navigator.clipboard.writeText(props.secret)
  hasCopiedSecret.value = true
}
</script>

<template>
  <v-text-field
    :model-value="secret"
    label="Secret"
    readonly
    append-inner-icon="mdi-content-copy"
    :hint="hasCopiedSecret ? 'Copied to clipboard.' : ''"
    persistent-hint
    @click:append-inner="copySecretToClipboard"
  />
</template>
