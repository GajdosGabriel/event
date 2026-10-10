<template>
  <div class="grid gap-2">
    <span class="text-sm font-semibold text-slate-900">{{ label }}</span>

    <div v-for="(value, index) in rows" :key="index" class="flex items-center gap-2">
      <input
        :value="value"
        :type="type"
        class="form-input min-w-0 flex-1"
        :class="{ invalid: (index === 0 && Boolean(error)) || rowInvalid(value) }"
        :maxlength="maxlength ?? (type === 'email' ? 100 : 30)"
        :aria-invalid="(index === 0 && Boolean(error)) || rowInvalid(value) || undefined"
        :aria-label="index === 0 ? label : `${label} ${index + 1}`"
        @input="setRow(index, ($event.target as HTMLInputElement).value)"
      />
      <span v-if="badgeOf(value)" class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium"
        :class="badgeOf(value)!.class" :title="badgeOf(value)!.title">
        {{ badgeOf(value)!.label }}
      </span>
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

    <span v-if="hint" class="text-xs text-slate-500">{{ hint }}</span>
    <span v-if="error" class="field-error">{{ error }}</span>
    <span v-else-if="type === 'tel' && rows.some(rowInvalid)" class="field-error">{{ t('common.phoneInvalid') }}</span>

    <button type="button" class="justify-self-start text-sm text-blue-700 hover:underline" @click="addRow">
      + {{ addLabel }}
    </button>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { t } from '@/i18n'
import { isValidPhone } from '@/utils/contact'

export interface ContactBadge {
  label: string
  class: string
  title?: string
}

/**
 * Zoznam kontaktov s jedným primárnym: prvý riadok je `primary` (stĺpec
 * `email` / `phone`), ostatné idú do `additional`. Primárny sa mení
 * presunutím vybraného riadku na prvé miesto.
 */
const props = defineProps<{
  label: string
  addLabel: string
  type: 'email' | 'tel'
  error?: string
  hint?: string
  maxlength?: number
  /** Stav už uložených hodnôt (kľúč = hodnota malými písmenami), napr. „nedoručiteľný". */
  badges?: Record<string, ContactBadge>
}>()

function badgeOf(value: string): ContactBadge | undefined {
  return props.badges?.[value.trim().toLowerCase()]
}

const primary = defineModel<string>('primary', { required: true })
const additional = defineModel<string[]>('additional', { required: true })

const rows = computed(() => [primary.value, ...additional.value])

function rowInvalid(value: string): boolean {
  return props.type === 'tel' && !isValidPhone(value)
}

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
