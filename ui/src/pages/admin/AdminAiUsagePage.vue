<template>
  <div class="grid gap-4">
    <div class="flex flex-wrap items-end justify-between gap-3">
      <div>
        <h1 class="text-2xl font-semibold text-slate-900">{{ t('aiUsage.title') }}</h1>
        <p class="max-w-3xl text-sm text-slate-600">{{ t('aiUsage.intro') }}</p>
      </div>

      <div class="flex flex-wrap gap-2">
        <button
          v-for="option in periods"
          :key="option"
          class="btn btn-sm"
          :class="days === option ? 'btn-primary' : 'btn-secondary'"
          @click="setDays(option)"
        >
          {{ t('aiUsage.days', { n: option }) }}
        </button>
      </div>
    </div>

    <p v-if="loading && !data" class="text-slate-600">{{ t('aiUsage.loading') }}</p>

    <template v-else-if="data">
      <!-- Súčty: zvolené obdobie, tento mesiac, celkovo -->
      <div class="grid gap-3 sm:grid-cols-3">
        <div v-for="card in totalCards" :key="card.label" class="panel-card">
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ card.label }}</p>
          <p class="mt-1 text-2xl font-semibold text-slate-900">{{ usd(card.totals.costUsd) }}</p>
          <p class="mt-1 text-sm text-slate-600">
            {{ tokens(card.totals.promptTokens + card.totals.completionTokens) }} {{ t('aiUsage.tokens') }}
            · {{ card.totals.calls }} {{ t('aiUsage.calls') }}
            <span v-if="card.totals.failed" class="text-red-600">· {{ card.totals.failed }} {{ t('aiUsage.failed') }}</span>
          </p>
        </div>
      </div>

      <!-- Po dňoch — jednoduché stĺpce podľa ceny -->
      <div v-if="data.byDay.length" class="panel-card">
        <h2 class="mb-3 font-semibold text-slate-900">{{ t('aiUsage.byDay') }}</h2>
        <div class="flex h-32 items-end gap-1 overflow-x-auto" role="img" :aria-label="t('aiUsage.byDay')">
          <div
            v-for="day in data.byDay"
            :key="day.day"
            class="min-w-[6px] flex-1 rounded-t bg-amber-400 hover:bg-amber-500"
            :style="{ height: `${Math.max(2, (day.costUsd / maxDayCost) * 100)}%` }"
            :title="`${formatDay(day.day)}: ${usd(day.costUsd)} · ${tokens(day.promptTokens + day.completionTokens)} ${t('aiUsage.tokens')} · ${day.calls} ${t('aiUsage.calls')}`"
          />
        </div>
        <div class="mt-1 flex justify-between text-xs text-slate-500">
          <span>{{ formatDay(data.byDay[0].day) }}</span>
          <span>{{ formatDay(data.byDay[data.byDay.length - 1].day) }}</span>
        </div>
      </div>

      <!-- Podľa operácie -->
      <div class="panel-card">
        <h2 class="mb-3 font-semibold text-slate-900">{{ t('aiUsage.byFeature') }}</h2>
        <UsageTable :rows="data.byFeature" :label="featureLabel" :total-cost="data.totals.period.costUsd" />
      </div>

      <div class="grid gap-4 lg:grid-cols-2">
        <div class="panel-card">
          <h2 class="mb-1 font-semibold text-slate-900">{{ t('aiUsage.byCanal') }}</h2>
          <p class="mb-3 text-xs text-slate-500">{{ t('aiUsage.byCanalNote') }}</p>
          <UsageTable :rows="data.byCanal" :label="canalLabel" :total-cost="data.totals.period.costUsd" />
        </div>

        <div class="panel-card">
          <h2 class="mb-1 font-semibold text-slate-900">{{ t('aiUsage.bySource') }}</h2>
          <p class="mb-3 text-xs text-slate-500">{{ t('aiUsage.bySourceNote') }}</p>
          <UsageTable :rows="data.bySource" :label="sourceLabel" :total-cost="data.totals.period.costUsd" />
        </div>
      </div>

      <div class="panel-card">
        <h2 class="mb-3 font-semibold text-slate-900">{{ t('aiUsage.byUser') }}</h2>
        <UsageTable :rows="data.byUser" :label="userLabel" :total-cost="data.totals.period.costUsd" />
      </div>

      <!-- Posledné volania -->
      <div class="panel-card">
        <h2 class="mb-3 font-semibold text-slate-900">{{ t('aiUsage.recent') }}</h2>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-slate-200 text-left text-xs uppercase text-slate-500">
                <th class="pb-2 pr-4">{{ t('aiUsage.colWhen') }}</th>
                <th class="pb-2 pr-4">{{ t('aiUsage.colFeature') }}</th>
                <th class="pb-2 pr-4">{{ t('aiUsage.colContext') }}</th>
                <th class="pb-2 pr-4 text-right">{{ t('aiUsage.colTokens') }}</th>
                <th class="pb-2 text-right">{{ t('aiUsage.colCost') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in data.recent" :key="row.id" class="border-b border-slate-100 last:border-0">
                <td class="whitespace-nowrap py-2 pr-4 text-slate-600">{{ formatDateTime(row.createdAt) }}</td>
                <td class="py-2 pr-4">
                  <span class="font-medium text-slate-900">{{ featureLabel(row.feature) }}</span>
                  <span v-if="!row.success" class="ml-2 rounded bg-red-100 px-1.5 py-0.5 text-xs text-red-700">{{ t('aiUsage.error') }}</span>
                </td>
                <td class="py-2 pr-4 text-slate-600">
                  <RouterLink v-if="row.canal" :to="`/admin/canals/${row.canal.id}`" class="underline">{{ row.canal.name }}</RouterLink>
                  <span v-if="row.canal && row.user"> · </span>
                  <span v-if="row.user">{{ row.user.name }}</span>
                  <span v-if="!row.canal && !row.user" class="text-slate-400">{{ sourceLabel(row.source) }}</span>
                </td>
                <td class="whitespace-nowrap py-2 pr-4 text-right tabular-nums">
                  {{ tokens(row.promptTokens) }} / {{ tokens(row.completionTokens) }}
                </td>
                <td class="whitespace-nowrap py-2 text-right tabular-nums">{{ usd(row.costUsd) }}</td>
              </tr>
              <tr v-if="data.recent.length === 0">
                <td colspan="5" class="py-4 text-slate-500">{{ t('aiUsage.empty') }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <p class="text-xs text-slate-500">
        {{ t('aiUsage.priceNote') }}
        <a href="https://platform.openai.com/usage" target="_blank" rel="noopener" class="underline">platform.openai.com/usage</a>
      </p>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, defineComponent, h, onMounted, ref, type PropType } from 'vue'
import { fetchAiUsage, type AiUsageGroup, type AiUsageOverview } from '@/api/aiUsage'
import { useToast } from '@/composables/useToast'
import { localeTag, useI18n } from '@/i18n'

const { t } = useI18n()
const toast = useToast()

const periods = [1, 7, 30, 90, 365]
const days = ref(30)
const data = ref<AiUsageOverview | null>(null)
const loading = ref(false)

onMounted(load)

async function load() {
  loading.value = true
  try {
    data.value = await fetchAiUsage(days.value)
  } catch {
    toast.error(t('aiUsage.loadFailed'))
  } finally {
    loading.value = false
  }
}

function setDays(next: number) {
  if (days.value === next) return
  days.value = next
  load()
}

const totalCards = computed(() => (data.value
  ? [
      { label: t('aiUsage.days', { n: days.value }), totals: data.value.totals.period },
      { label: t('aiUsage.thisMonth'), totals: data.value.totals.month },
      { label: t('aiUsage.allTime'), totals: data.value.totals.all },
    ]
  : []))

const maxDayCost = computed(() => Math.max(0.000001, ...(data.value?.byDay ?? []).map(d => d.costUsd)))

// Lacný model stojí zlomky centu — pri dvoch desatinných miestach by všade
// svietilo $0.00.
function usd(value: number): string {
  const digits = value > 0 && value < 1 ? 4 : 2
  return `$${value.toLocaleString(localeTag(), { minimumFractionDigits: digits, maximumFractionDigits: digits })}`
}

function tokens(value: number): string {
  return value.toLocaleString(localeTag())
}

function formatDay(value: string): string {
  const date = new Date(`${value}T00:00:00`)
  return Number.isNaN(date.getTime()) ? value : date.toLocaleDateString(localeTag(), { day: 'numeric', month: 'numeric' })
}

function formatDateTime(value: string | null): string {
  if (!value) return '—'
  const date = new Date(value)
  return Number.isNaN(date.getTime())
    ? '—'
    : date.toLocaleString(localeTag(), { day: 'numeric', month: 'numeric', hour: '2-digit', minute: '2-digit' })
}

/** Neznáma operácia (nová metóda ChatGPT bez prekladu) sa ukáže surovým kľúčom. */
function featureLabel(key: AiUsageGroup['key']): string {
  const k = String(key ?? 'other')
  const translated = t(`aiUsage.feature.${k}` as Parameters<typeof t>[0])
  return translated.startsWith('aiUsage.feature.') ? k : translated
}

function canalLabel(key: AiUsageGroup['key'], row?: AiUsageGroup): string {
  return key === null ? t('aiUsage.noCanal') : (row?.name ?? `#${key}`)
}

function userLabel(key: AiUsageGroup['key'], row?: AiUsageGroup): string {
  return key === null ? t('aiUsage.noUser') : (row?.name ?? `#${key}`)
}

function sourceLabel(key: AiUsageGroup['key'] | undefined): string {
  return key === null || key === undefined || key === '' ? '—' : String(key)
}

/** Tabuľka skupiny: popis, volania, tokeny, cena a podiel na cene obdobia. */
const UsageTable = defineComponent({
  props: {
    rows: { type: Array as PropType<AiUsageGroup[]>, required: true },
    label: { type: Function as PropType<(key: AiUsageGroup['key'], row?: AiUsageGroup) => string>, required: true },
    totalCost: { type: Number, required: true },
  },
  setup(props) {
    return () => h('div', { class: 'overflow-x-auto' }, [
      h('table', { class: 'w-full text-sm' }, [
        h('thead', h('tr', { class: 'border-b border-slate-200 text-left text-xs uppercase text-slate-500' }, [
          h('th', { class: 'pb-2 pr-4' }, t('aiUsage.colName')),
          h('th', { class: 'pb-2 pr-4 text-right' }, t('aiUsage.colCalls')),
          h('th', { class: 'pb-2 pr-4 text-right' }, t('aiUsage.colTokens')),
          h('th', { class: 'pb-2 pr-4 text-right' }, t('aiUsage.colCost')),
          h('th', { class: 'pb-2 text-right' }, '%'),
        ])),
        h('tbody', props.rows.length
          ? props.rows.map(row => h('tr', { class: 'border-b border-slate-100 last:border-0' }, [
              h('td', { class: 'py-2 pr-4 font-medium text-slate-900' }, props.label(row.key, row)),
              h('td', { class: 'py-2 pr-4 text-right tabular-nums' }, [
                String(row.calls),
                row.failed ? h('span', { class: 'ml-1 text-red-600' }, `(${row.failed}×)`) : null,
              ]),
              h('td', { class: 'py-2 pr-4 text-right tabular-nums' }, tokens(row.promptTokens + row.completionTokens)),
              h('td', { class: 'py-2 pr-4 text-right tabular-nums' }, usd(row.costUsd)),
              h('td', { class: 'py-2 text-right tabular-nums text-slate-500' },
                props.totalCost > 0 ? `${Math.round((row.costUsd / props.totalCost) * 100)} %` : '—'),
            ]))
          : [h('tr', h('td', { colspan: 5, class: 'py-4 text-slate-500' }, t('aiUsage.empty')))]),
      ]),
    ])
  },
})
</script>
