<template>
  <component
    :is="to ? 'RouterLink' : 'div'"
    :to="to"
    class="stat-tile"
    :class="{ 'stat-tile-link': to }"
  >
    <p class="stat-label">{{ label }}</p>

    <p class="stat-value">{{ fmtMetric(value, format) }}</p>

    <div class="stat-foot">
      <span v-if="changeLabel" class="stat-change" :class="changeClass">
        <svg class="size-3" viewBox="0 0 12 12" fill="currentColor" aria-hidden="true">
          <path v-if="direction === 'up'" d="M6 2l4 6H2z" />
          <path v-else-if="direction === 'down'" d="M6 10L2 4h8z" />
          <path v-else d="M2 5h8v2H2z" />
        </svg>
        {{ changeLabel }}
      </span>
      <span v-else-if="previous !== null" class="stat-hint">{{ t('stats.noComparison') }}</span>
      <span v-if="previous !== null" class="stat-hint">
        {{ t('stats.previous', { value: fmtMetric(previous, format) }) }}
      </span>
    </div>

    <StatSparkline v-if="spark?.length" :values="spark" :color="color" class="mt-2" />
  </component>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import StatSparkline from './StatSparkline.vue'
import { useI18n } from '@/i18n'
import { fmtChange, fmtMetric } from '@/utils/statsFormat'

const { t } = useI18n()

const props = withDefaults(defineProps<{
  label: string
  value: number
  format?: 'number' | 'money'
  previous?: number | null
  change?: number | null
  /** Denné hodnoty pre mikro-krivku pod číslom. */
  spark?: number[]
  color?: string
  to?: string | null
  /** Pri metrikách, kde rast nie je dobrá správa (napr. zrušené vstupenky). */
  invert?: boolean
}>(), {
  format: 'number',
  previous: null,
  change: null,
  spark: () => [],
  color: '#2a78d6',
  to: null,
  invert: false,
})

const changeLabel = computed(() => fmtChange(props.change))

const direction = computed(() => {
  if (props.change === null || props.change === 0) return 'flat'
  return props.change > 0 ? 'up' : 'down'
})

// Smer šípky hovorí, kam sa číslo pohlo; farba hovorí, či je to dobre.
// Pri obrátených metrikách sú tieto dve veci opačné.
const changeClass = computed(() => {
  if (direction.value === 'flat') return 'stat-change-flat'
  const good = props.invert ? direction.value === 'down' : direction.value === 'up'
  return good ? 'stat-change-good' : 'stat-change-bad'
})
</script>

<style scoped>
@reference "tailwindcss";

.stat-tile { @apply flex flex-col rounded-xl border border-slate-200 bg-white p-4 no-underline; }
.stat-tile-link { @apply transition-colors hover:border-slate-300 hover:bg-slate-50; }
.stat-label { @apply text-xs font-semibold uppercase tracking-wide text-slate-500; }
.stat-value { @apply mt-1 text-2xl font-bold leading-tight text-slate-900; }
.stat-foot { @apply mt-1 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs; }
.stat-change { @apply inline-flex items-center gap-1 font-semibold; }
.stat-change-good { @apply text-green-700; }
.stat-change-bad { @apply text-red-700; }
.stat-change-flat { @apply text-slate-500; }
.stat-hint { @apply text-slate-400; }
</style>
