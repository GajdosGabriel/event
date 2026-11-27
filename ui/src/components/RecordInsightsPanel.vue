<template>
  <div class="show-card">
    <h2 class="mb-3 text-base font-semibold text-slate-800">{{ t('recordInsights.title') }}</h2>
    <p v-if="loading && !data" class="text-sm text-slate-500">{{ t('common.loading') }}</p>
    <p v-else-if="!data" class="text-sm text-slate-400">{{ t('recordInsights.loadFailed') }}</p>
    <dl v-else class="grid gap-1.5 text-sm">
      <div v-for="fact in facts" :key="fact.label" class="flex items-baseline justify-between gap-3">
        <dt class="text-slate-500">{{ fact.label }}</dt>
        <dd class="text-right font-medium text-slate-800">{{ fact.value }}</dd>
      </div>
    </dl>
  </div>

  <div v-if="data" class="show-card">
    <div class="mb-3 flex items-center justify-between gap-2">
      <h2 class="text-base font-semibold text-slate-800">{{ t('recordInsights.history') }}</h2>
      <!-- Denník vie filtrovať len podľa kanála. -->
      <RouterLink v-if="kind === 'canal'" :to="{ path: '/admin/dennik', query: { canal_id: String(recordId) } }" class="text-xs text-blue-600 hover:underline">
        {{ t('recordInsights.historyAll') }}
      </RouterLink>
    </div>
    <p v-if="!data.timeline.length" class="text-sm text-slate-400">{{ t('recordInsights.historyEmpty') }}</p>
    <ol v-else class="grid gap-2">
      <li v-for="entry in timelinePage.items.value" :key="entry.id" class="border-l-2 pl-3"
        :class="entry.level === 'error' ? 'border-red-400' : entry.level === 'warning' ? 'border-amber-400' : entry.type === 'ai' ? 'border-violet-300' : 'border-slate-200'">
        <div class="flex flex-wrap items-center gap-1.5 text-xs text-slate-500">
          <span v-if="entry.type === 'ai'" class="rounded bg-violet-100 px-1 font-semibold text-violet-800">AI</span>
          <span class="font-medium text-slate-700" :title="entry.type === 'log' ? entry.event : entry.type === 'ai' ? entry.model : ''">{{ entryTitle(entry) }}</span>
          <span v-if="entry.type === 'log' && entry.status">· {{ statusLabel(entry.status) }}</span>
          <span v-else-if="entry.type === 'ai'">· {{ usd(entry.costUsd) }}<template v-if="entry.level === 'error'"> · {{ t('recordInsights.failed') }}</template></span>
          <span class="ml-auto">{{ formatDateTime(entry.createdAt) }}</span>
        </div>
        <p v-if="entryText(entry)" class="break-words text-sm text-slate-800">{{ entryText(entry) }}</p>
      </li>
    </ol>
    <AppPaginator :current-page="timelinePage.page.value" :last-page="timelinePage.lastPage.value" @change="timelinePage.setPage" />
  </div>
</template>

<script setup lang="ts">
/**
 * Pravý stĺpec detailu kanála a miesta v administrácii: údaje o zázname
 * (vznik, používanie, AI) a časová os z denníka, AI spotreby a doplnenia.
 */
import { computed, onMounted, ref, watch } from 'vue'
import AppPaginator from '@/components/AppPaginator.vue'
import { getRecordInsights, type InsightsEntry, type InsightsKind, type RecordInsights } from '@/api/recordInsights'
import { useClientPagination } from '@/composables/useClientPagination'
import { useSystemLogLabels } from '@/composables/useSystemLogLabels'
import { fmtDate, fmtTime } from '@/utils/dateFormat'
import { localeTag, useI18n, type MessageKey } from '@/i18n'

/** `version` zvýši rodič, keď sa so záznamom niečo stalo a treba načítať nanovo. */
const props = defineProps<{ kind: InsightsKind; recordId: number; version?: number }>()
const { t: translate } = useI18n()
// Kľúče sa skladajú z kódov z API (operácia AI, zdroj polohy).
const t = (key: string, params?: Record<string, string | number>) => translate(key as MessageKey, params)
const { eventLabel, statusLabel } = useSystemLogLabels()

const data = ref<RecordInsights | null>(null)
const loading = ref(false)
const timelinePage = useClientPagination(computed(() => data.value?.timeline ?? []), 10)

const facts = computed(() => {
  const f = data.value?.facts
  if (!f) return []
  const rows: { label: string; value: string }[] = []
  const add = (key: string, value: string | number | null) => {
    if (value !== null && value !== '') rows.push({ label: t(`recordInsights.${key}`), value: String(value) })
  }

  add('id', `#${props.recordId}`)
  add('createdAt', f.createdAt ? formatDateTime(f.createdAt) : null)
  add('updatedAt', f.updatedAt ? formatDateTime(f.updatedAt) : null)
  add('publishedAt', f.publishedAt ? fmtDate(f.publishedAt) : null)
  add('views', t('recordInsights.viewsValue', { total: f.viewsTotal, month: f.viewsMonth }))
  add('events', f.eventsUpcoming
    ? t('recordInsights.eventsValue', { total: f.eventsTotal, upcoming: f.eventsUpcoming })
    : f.eventsTotal)
  add('firstEventAt', f.firstEventAt ? fmtDate(f.firstEventAt) : null)
  add('lastEventAt', f.lastEventAt ? fmtDate(f.lastEventAt) : null)
  add('followers', f.followers)
  add('canals', f.canalsCount)
  add('files', f.filesCount)
  add('contentReview', f.contentReview
    ? [f.contentReview.score !== null ? `${f.contentReview.score}/100` : '', fmtDate(f.contentReview.reviewedAt)].filter(Boolean).join(' · ')
    : null)
  add('ai', f.ai.calls
    ? [t('recordInsights.aiValue', { calls: f.ai.calls }), usd(f.ai.costUsd), f.ai.failed ? `${t('recordInsights.failed')}: ${f.ai.failed}` : ''].filter(Boolean).join(' · ')
    : t('common.none'))

  return rows
})

function entryTitle(entry: InsightsEntry): string {
  switch (entry.type) {
    case 'log': return eventLabel(entry.event)
    case 'ai': return t(`aiUsage.feature.${entry.feature}`)
    case 'profile': return t('recordInsights.profile')
    case 'coordinates': return t('recordInsights.coordinates')
  }
}

function entryText(entry: InsightsEntry): string {
  switch (entry.type) {
    case 'log':
    case 'ai': return entry.message ?? ''
    case 'profile': return entry.fields.length
      ? t('recordInsights.profileChanged', { fields: entry.fields.join(', ') })
      : t('recordInsights.nothingFound')
    case 'coordinates': return entry.source
      ? t(`canals.lookups.coordinates.sources.${entry.source}`)
      : t('recordInsights.nothingFound')
  }
}

function formatDateTime(d: string) {
  return `${fmtDate(d)} ${fmtTime(d)}`
}

function usd(value: number): string {
  const digits = value > 0 && value < 1 ? 4 : 2
  return `$${value.toLocaleString(localeTag(), { minimumFractionDigits: digits, maximumFractionDigits: digits })}`
}

/** Denník je doplnok — chyba nesmie zhodiť detail. */
async function load() {
  loading.value = true
  try {
    data.value = await getRecordInsights(props.kind, props.recordId)
  } catch {
    data.value = null
  } finally {
    loading.value = false
  }
}

watch(() => [props.recordId, props.version], load)
onMounted(load)
</script>
