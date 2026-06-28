<template>
  <input
    type="datetime-local"
    :min="minDateTime"
    :value="modelValue"
    v-bind="$attrs"
    @input="emit('update:modelValue', ($event.target as HTMLInputElement).value)"
  />
</template>

<script setup lang="ts">
import { computed } from 'vue'

defineOptions({ inheritAttrs: false })

const props = withDefaults(defineProps<{
  modelValue: string
  allowPast?: boolean
}>(), {
  allowPast: false,
})

const emit = defineEmits<{
  (e: 'update:modelValue', value: string): void
}>()

const minDateTime = computed(() => {
  if (props.allowPast) return undefined
  const d = new Date()
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`
})
</script>
