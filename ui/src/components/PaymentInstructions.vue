<template>
  <!-- Platobné údaje neuhradenej objednávky — účet organizácie z Accountu.
       Rovnaké údaje idú aj do e-mailu s rekapituláciou rezervácie. -->
  <div class="rounded-xl border border-amber-200 bg-white p-4 text-sm">
    <p class="font-semibold text-slate-900">{{ t('payment.title') }}</p>

    <dl class="mt-2 space-y-1.5">
      <div v-for="row in rows" :key="row.key" class="flex flex-wrap items-center gap-x-2">
        <dt class="text-slate-500">{{ row.label }}:</dt>
        <dd class="font-medium text-slate-900" :class="{ 'font-mono': row.mono }">{{ row.value }}</dd>
        <button
          v-if="row.copy"
          type="button"
          class="text-xs font-medium text-blue-600 hover:underline"
          @click="copy(row.key, row.copy)"
        >
          {{ copied === row.key ? t('payment.copied') : t('payment.copy') }}
        </button>
      </div>
    </dl>

    <p class="mt-3 text-xs text-slate-500">{{ t('payment.after') }}</p>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { t } from '@/i18n'
import { formatPrice } from '@/utils/money'
import type { PaymentInstructionsItem } from '@/types'

const props = defineProps<{ payment: PaymentInstructionsItem }>()

const copied = ref<string | null>(null)

const rows = computed(() => {
  const p = props.payment
  return [
    { key: 'recipient', label: t('payment.recipient'), value: p.recipient },
    // Do schránky bez medzier — tak ho berú bankové aplikácie.
    { key: 'iban', label: t('payment.iban'), value: p.iban, copy: p.iban.replace(/\s+/g, ''), mono: true },
    ...(p.swift ? [{ key: 'swift', label: t('payment.swift'), value: p.swift, mono: true }] : []),
    { key: 'vs', label: t('payment.variableSymbol'), value: p.variableSymbol, copy: p.variableSymbol, mono: true },
    { key: 'amount', label: t('payment.amount'), value: formatPrice(p.amount, p.currency), copy: (p.amount / 100).toFixed(2) },
    { key: 'note', label: t('payment.note'), value: p.note },
  ] as { key: string; label: string; value: string; copy?: string; mono?: boolean }[]
})

async function copy(key: string, value: string) {
  try {
    await navigator.clipboard.writeText(value)
    copied.value = key
    setTimeout(() => { if (copied.value === key) copied.value = null }, 2000)
  } catch {
    // Bez prístupu k schránke ostáva údaj viditeľný na ručné prepísanie.
  }
}
</script>
