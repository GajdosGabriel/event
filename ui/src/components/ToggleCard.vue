<template>
  <!-- Prepínač s nadpisom a vysvetlivkou — celá karta je klikateľná. -->
  <label
    class="flex cursor-pointer items-start gap-3 rounded-xl border p-3 transition-colors"
    :class="model ? 'border-green-500 bg-green-50' : 'border-slate-200 hover:border-slate-300'"
  >
    <input v-model="model" type="checkbox" class="peer sr-only" />
    <span
      class="relative mt-0.5 h-6 w-11 shrink-0 rounded-full transition-colors peer-focus-visible:ring-2 peer-focus-visible:ring-blue-500 peer-focus-visible:ring-offset-2"
      :class="model ? 'bg-green-600' : 'bg-slate-300'"
      aria-hidden="true"
    >
      <span class="absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white shadow transition-transform"
        :class="model ? 'translate-x-5' : ''" />
    </span>
    <span class="grid gap-0.5">
      <span class="text-sm font-semibold text-slate-900">{{ label }}</span>
      <span v-if="hint" class="text-xs text-slate-500">{{ hint }}</span>
    </span>
  </label>
</template>

<script setup lang="ts">
defineProps<{ label: string; hint?: string }>()
const model = defineModel<boolean>({ required: true })
</script>
