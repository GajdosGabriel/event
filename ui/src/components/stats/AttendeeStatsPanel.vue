<template>
  <div class="panel-card grid gap-5">
    <!-- Dvere: hlavné číslo panela. Kým podujatie beží, je to jediné,
         čo organizátora pri vchode naozaj zaujíma. -->
    <section>
      <MeterBar
        :label="t('tickets.stats.arrived')"
        :part="summary.admissions.arrived"
        :whole="summary.admissions.total"
        :rate="rate(summary.admissions.arrived, summary.admissions.total)"
        :unit="t('tickets.stats.seatsUnit')"
        :note="t('tickets.stats.remaining', { n: summary.admissions.remaining })"
        color="#16a34a"
      />
    </section>

    <!-- Objednávky -->
    <section class="grid grid-cols-3 gap-2 text-center">
      <div v-for="tile in orderTiles" :key="tile.key" class="rounded-xl bg-slate-50 px-2 py-2.5">
        <p class="text-lg font-semibold tabular-nums" :class="tile.class">{{ tile.value }}</p>
        <p class="text-[0.7rem] uppercase tracking-wide text-slate-500">{{ tile.label }}</p>
      </div>
    </section>

    <!-- Platby: len keď je čo platiť -->
    <section v-if="summary.payments.paidAmount || summary.payments.pendingAmount" class="grid gap-1.5">
      <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ t('tickets.stats.payments') }}</p>
      <div class="flex items-baseline justify-between gap-2 text-sm">
        <span class="text-slate-600">{{ t('tickets.stats.paid') }}</span>
        <span class="font-semibold tabular-nums text-slate-900">
          {{ formatPrice(summary.payments.paidAmount, summary.payments.currency) }}
        </span>
      </div>
      <div v-if="summary.payments.pendingCount" class="flex items-baseline justify-between gap-2 text-sm">
        <span class="text-slate-600">
          {{ t('tickets.stats.awaitingPayment', { n: summary.payments.pendingCount }) }}
        </span>
        <span class="font-semibold tabular-nums text-amber-700">
          {{ formatPrice(summary.payments.pendingAmount, summary.payments.currency) }}
        </span>
      </div>
    </section>

    <!-- Typy lístkov a workshopy: obsadenosť + koľko z nich už prišlo -->
    <section v-if="summary.types.length" class="grid gap-3">
      <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ t('tickets.stats.types') }}</p>
      <MeterBar
        v-for="type in summary.types"
        :key="type.id"
        :label="type.name"
        :part="type.sold"
        :whole="type.capacity ?? type.sold"
        :rate="type.capacity ? rate(type.sold, type.capacity) : null"
        :unit="t('tickets.stats.seatsUnit')"
        :note="typeNote(type)"
        :color="type.kind === 'workshop' ? '#7c3aed' : '#2a78d6'"
      />
    </section>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import MeterBar from './MeterBar.vue'
import { formatPrice } from '@/utils/money'
import { useI18n } from '@/i18n'
import type { AttendeeSummary } from '@/types'

const props = defineProps<{ summary: AttendeeSummary }>()

const { t } = useI18n()

/** Bez miest nie je čo percentuálne merať — lišta ostane prázdna. */
function rate(part: number, whole: number): number | null {
  return whole > 0 ? (part / whole) * 100 : null
}

const orderTiles = computed(() => [
  {
    key: 'confirmed',
    label: t('tickets.stats.confirmed'),
    value: props.summary.orders.confirmed,
    class: 'text-slate-900',
  },
  {
    key: 'reserved',
    label: t('tickets.stats.reserved'),
    value: props.summary.orders.reserved,
    class: props.summary.orders.reserved ? 'text-amber-700' : 'text-slate-400',
  },
  {
    key: 'cancelled',
    label: t('tickets.stats.cancelled'),
    value: props.summary.orders.cancelled,
    class: props.summary.orders.cancelled ? 'text-red-600' : 'text-slate-400',
  },
])

/** Pod lištou typu: koľko z predaných miest už prešlo dverami (+ čakačka). */
function typeNote(type: AttendeeSummary['types'][number]): string {
  const parts = [t('tickets.stats.typeArrived', { n: type.arrived })]

  if (type.waitlisted > 0) {
    parts.push(t('tickets.stats.typeWaitlisted', { n: type.waitlisted }))
  }

  return parts.join(' · ')
}
</script>
