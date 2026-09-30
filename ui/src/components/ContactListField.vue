<template>
  <div class="grid gap-2">
    <span class="text-sm font-semibold text-slate-900">{{ label }}</span>

    <div v-for="(value, index) in rows" :key="index" class="flex items-center gap-2">
      <input
        :value="value"
        :type="type"
        class="form-input min-w-0 flex-1"
        :class="{ invalid: index === 0 && Boolean(error) }"
        :aria-label="label"
        @input="setRow(index, ($event.target as HTMLInputElement).value)"
      />
      <span v-if="index === 0" class="shrink-0 rounded-full bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-800">
        {{ t('common.contactList.primary') }}
      </span>
      <button
        v-else
        type="button"
        class="shrink-0 text-xs text-blue-700 hover:underline"
        @click="makePrimary(index)"
      >{{ t('common.contactList.makePrimary') }}</button>
      <button
        v-if="rows.length > 1"
        type="button"
        class="shrink-0 text-slate-400 hover:text-red-600"
        :aria-label="t('common.contactList.remove')"
        :title="t('common.contactList.remove')"
        @click="removeRow(index)"
      >✕</button>
    </div>

    <span v-if="error" class="field-error">{{ error }}</span>

    <button type="button" class="justify-self-start text-sm text-blue-700 hover:underline" @click="addRow">
      + {{ addLabel }}
    </button>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { t } from '@/i18n'

/**
 * Zoznam kontaktov s jedným primárnym: prvý riadok je `primary` (stĺpec
 * `email` / `phone`), ostatné idú do `additional`. Primárny sa mení
 * presunutím vybraného riadku na prvé miesto.
 */
defineProps<{
  label: string
  addLabel: string
  type: 'email' | 'tel'
  error?: string
}>()

const primary = defineModel<string>('primary', { required: true })
const additional = defineModel<string[]>('additional', { required: true })

const rows = computed(() => [primary.value, ...additional.value])

function commit(next: string[]): void {
  primary.value = next[0] ?? ''
  additional.value = next.slice(1)
}

function setRow(index: number, value: string): void {
  const next = [...rows.value]
  next[index] = value
  commit(next)
}

function addRow(): void {
  commit([...rows.value, ''])
}

function removeRow(index: number): void {
  commit(rows.value.filter((_, i) => i !== index))
}

function makePrimary(index: number): void {
  const next = [...rows.value]
  const [chosen] = next.splice(index, 1)
  commit([chosen ?? '', ...next])
}
</script>
