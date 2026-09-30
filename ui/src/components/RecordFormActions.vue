<template>
  <div class="flex gap-2">
    <button type="submit" class="btn btn-primary" :disabled="saving">
      {{ saving ? t(key('form.saving')) : t(key('form.save')) }}
    </button>
    <RouterLink :to="cancelTo" class="btn btn-secondary">{{ t(key('form.cancel')) }}</RouterLink>
  </div>
</template>

<script setup lang="ts">
import { t, type MessageKey } from '@/i18n'

const props = defineProps<{
  kind: 'canals' | 'venues'
  saving: boolean
  cancelTo: string
}>()

const key = (path: string) => `${props.kind}.${path}` as MessageKey
</script>
