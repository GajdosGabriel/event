<template>
  <section class="panel-card">
    <div class="mb-3 flex flex-wrap items-end justify-between gap-3">
      <div>
        <h2 class="text-lg font-semibold text-slate-900">{{ t('stats.activity.title') }}</h2>
        <p class="text-sm text-slate-500">{{ t('stats.activity.subtitle', { days: trend.length, metric: active.label }) }}</p>
      </div>

      <!-- Prepínač namiesto viacerých sérií v jednom grafe: počty podujatí
           a vstupeniek majú úplne iný rád, spoločná os by menšiu z nich
           zarovnala k nule. -->
      <div class="flex flex-wrap gap-1" role="group" :aria-label="t('stats.activity.group')">
        <button
          v-for="option in options"
          :key="option.key"
          type="button"
          class="chart-tab"
          :class="{ 'chart-tab-active': option.key === metric }"
          :aria-pressed="option.key === metric"
          @click="metric = option.key"
        >
          {{ option.label }}
        </button>
      </div>
    </div>

    <p v-if="max === 0" class="rounded-lg bg-slate-50 px-4 py-6 text-center text-sm text-slate-500">
      {{ t('stats.activity.empty', { days: trend.length }) }}
    </p>

    <div v-else class="relative" @mouseleave="activeIndex = null">
      <svg :viewBox="`0 0 ${W} ${H}`" class="block h-auto w-full" role="img" :aria-label="t('stats.activity.byDays', { metric: active.label })">
        <!-- Mriežka ustupuje do pozadia, aby stĺpce zostali hlavným tvarom. -->
        <g>
          <line
            v-for="tick in ticks"
            :key="tick.value"
            :x1="PAD_L"
            :x2="W - PAD_R"
            :y1="tick.y"
            :y2="tick.y"
            :stroke="tick.value === 0 ? '#c3c2b7' : '#e1e0d9'"
            stroke-width="1"
          />
          <text
            v-for="tick in ticks"
            :key="`label-${tick.value}`"
            :x="PAD_L - 6"
            :y="tick.y + 3.5"
            text-anchor="end"
            class="axis-text"
          >{{ tick.value }}</text>
        </g>

        <g>
          <rect
            v-for="(bar, index) in bars"
            :key="bar.date"
            :x="bar.x"
            :y="bar.y"
            :width="bar.width"
            :height="bar.height"
            rx="2"
            :fill="index === activeIndex ? BAR_ACTIVE : BAR"
          />
        </g>

        <text
          v-for="label in dateLabels"
          :key="label.date"
          :x="label.x"
          :y="H - 6"
          text-anchor="middle"
          class="axis-text"
        >{{ label.text }}</text>

        <!-- Zásahové plochy sú širšie než stĺpce, aby sa dali trafiť aj pri
             nulovej hodnote a na dotykovom displeji. -->
        <rect
          v-for="(bar, index) in bars"
          :key="`hit-${bar.date}`"
          :x="bar.slotX"
          y="0"
          :width="bar.slotWidth"
          :height="H - PAD_B"
          fill="transparent"
          @mouseenter="activeIndex = index"
          @focus="activeIndex = index"
        />
      </svg>

      <div
        v-if="activeBar"
        class="chart-tooltip"
        :style="{ left: `${activeBar.centerRatio * 100}%` }"
      >
        <p class="font-semibold">{{ fmtCount(activeBar.value) }} {{ active.unit }}</p>
        <p class="text-slate-300">{{ fmtLongDate(activeBar.date) }}</p>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from '@/i18n'
import type { StatsTrendDay } from '@/types'
import { fmtCount, fmtShortDate } from '@/utils/statsFormat'

const props = defineProps<{ trend: StatsTrendDay[] }>()

const { t, locale } = useI18n()

// Sekvenčné kódovanie: jedna séria, jeden odtieň. Aktívny stĺpec je tmavší
// krok tej istej škály, nie iná farba.
const BAR = '#5598e7'
const BAR_ACTIVE = '#1c5cab'

const W = 720
const H = 200
const PAD_L = 34
const PAD_R = 6
const PAD_T = 10
const PAD_B = 24

type MetricKey = 'views' | 'events' | 'tickets' | 'admissions' | 'checkins'

const METRIC_KEYS: MetricKey[] = ['views', 'events', 'tickets', 'admissions', 'checkins']

// Computed, nie konštanta — popisky aj jednotky sa musia prekresliť pri
// prepnutí jazyka.
const options = computed(() => METRIC_KEYS.map(key => ({
  key,
  label: t(`stats.activity.metrics.${key}`),
  unit: t(`stats.activity.units.${key}`),
})))

const metric = ref<MetricKey>('views')
const activeIndex = ref<number | null>(null)

const active = computed(() => options.value.find(o => o.key === metric.value) ?? options.value[0])
const trend = computed(() => props.trend)
const values = computed(() => trend.value.map(day => day[metric.value]))

/** Horná hranica osi zaokrúhlená nahor, nech popisky nie sú „37, 18,5, 0". */
const max = computed(() => {
  const peak = Math.max(0, ...values.value)
  if (peak === 0) return 0
  const magnitude = 10 ** Math.floor(Math.log10(peak))
  const step = peak / magnitude <= 2 ? magnitude / 2 : magnitude
  return Math.max(step, Math.ceil(peak / step) * step)
})

const plotH = H - PAD_T - PAD_B
const plotW = W - PAD_L - PAD_R

const ticks = computed(() =>
  [0, max.value / 2, max.value].map(value => ({
    value: Number.isInteger(value) ? value : Math.round(value * 10) / 10,
    y: PAD_T + plotH - (max.value === 0 ? 0 : value / max.value) * plotH,
  })),
)

const bars = computed(() => {
  const slot = plotW / Math.max(1, trend.value.length)
  // 2px medzera medzi stĺpcami: susedné plochy sa nesmú dotýkať.
  const width = Math.max(2, slot - 2)

  return trend.value.map((day, index) => {
    const value = day[metric.value]
    const height = max.value === 0 ? 0 : (value / max.value) * plotH
    const slotX = PAD_L + index * slot

    return {
      date: day.date,
      value,
      x: slotX + (slot - width) / 2,
      y: PAD_T + plotH - height,
      width,
      height,
      slotX,
      slotWidth: slot,
      centerRatio: (slotX + slot / 2) / W,
    }
  })
})

const activeBar = computed(() => (activeIndex.value === null ? null : bars.value[activeIndex.value] ?? null))

/** Popisujeme každý piaty deň — 30 dátumov vedľa seba by sa prekrývalo. */
const dateLabels = computed(() =>
  bars.value
    .filter((_, index) => index % 5 === 0 || index === bars.value.length - 1)
    .map(bar => ({ date: bar.date, x: bar.slotX + bar.slotWidth / 2, text: fmtShortDate(bar.date) })),
)

function fmtLongDate(date: string): string {
  return new Date(date).toLocaleDateString(locale.value, { weekday: 'short', day: 'numeric', month: 'long' })
}
</script>

<style scoped>
@reference "tailwindcss";

.chart-tab {
  @apply cursor-pointer rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-600 transition-colors hover:border-slate-300 hover:bg-slate-50;
}
.chart-tab-active { @apply border-slate-900 bg-slate-900 text-white hover:bg-slate-900; }

.axis-text { fill: #898781; font-size: 9px; font-variant-numeric: tabular-nums; }

.chart-tooltip {
  @apply pointer-events-none absolute top-0 -translate-x-1/2 rounded-lg bg-slate-900 px-2.5 py-1.5 text-xs text-white shadow-lg;
  white-space: nowrap;
}
</style>
