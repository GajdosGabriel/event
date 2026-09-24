<template>
  <div class="grid gap-4">
    <div>
      <h1 class="text-2xl font-semibold text-slate-900">{{ t('systemLog.title') }}</h1>
      <p class="max-w-3xl text-sm text-slate-600">
        {{ t('systemLog.intro') }}
        <template v-if="page">{{ t('systemLog.retention', { days: page.retention.days, errorDays: page.retention.errorDays }) }}</template>
      </p>
    </div>

    <!-- Súhrn za 24 hodín / 7 dní -->
    <div v-if="page" class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-5">
      <div v-for="card in cards" :key="card.label" class="panel-card">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ card.label }}</p>
        <p class="mt-1 text-2xl font-semibold" :class="card.alert ? 'text-red-600' : 'text-slate-900'">{{ card.value }}</p>
        <p class="mt-1 text-xs text-slate-500">{{ card.note }}</p>
      </div>
    </div>

    <!-- Filtre -->
    <form class="panel-card flex flex-wrap items-end gap-3 text-sm" @submit.prevent="apply()">
      <label class="grid gap-1">
        <span class="text-xs text-slate-500">{{ t('systemLog.filter.channel') }}</span>
        <select v-model="filters.channel" class="form-input h-9 w-auto" @change="apply()">
          <option value="">{{ t('systemLog.filter.all') }}</option>
          <option v-for="channel in page?.channels ?? []" :key="channel" :value="channel">{{ channelLabel(channel) }}</option>
        </select>
      </label>
      <label class="grid gap-1">
        <span class="text-xs text-slate-500">{{ t('systemLog.filter.level') }}</span>
        <select v-model="filters.level" class="form-input h-9 w-auto" @change="apply()">
          <option value="">{{ t('systemLog.filter.all') }}</option>
          <option v-for="level in levels" :key="level" :value="level">{{ t(`systemLog.level.${level}`) }}</option>
        </select>
      </label>
      <label class="grid gap-1">
        <span class="text-xs text-slate-500">{{ t('systemLog.filter.status') }}</span>
        <select v-model="filters.status" class="form-input h-9 w-auto" @change="apply()">
          <option value="">{{ t('systemLog.filter.all') }}</option>
          <option v-for="status in statuses" :key="status" :value="status">{{ t(`systemLog.status.${status}`) }}</option>
        </select>
      </label>
      <label class="grid gap-1">
        <span class="text-xs text-slate-500">{{ t('systemLog.filter.from') }}</span>
        <input v-model="filters.date_from" type="date" class="form-input h-9 w-auto" @change="apply()">
      </label>
      <label class="grid gap-1">
        <span class="text-xs text-slate-500">{{ t('systemLog.filter.to') }}</span>
        <input v-model="filters.date_to" type="date" class="form-input h-9 w-auto" @change="apply()">
      </label>
      <button type="submit" class="btn btn-sm btn-primary">{{ t('systemLog.filter.apply') }}</button>

      <button v-if="filters.recipient" type="button" class="btn btn-sm btn-secondary" @click="clear('recipient')">
        {{ filters.recipient }} ✕
      </button>
      <button v-if="filters.user_id" type="button" class="btn btn-sm btn-secondary" @click="clear('user_id')">
        {{ t('systemLog.filter.user', { id: filters.user_id }) }} ✕
      </button>
      <button v-if="anyFilter" type="button" class="text-xs text-slate-500 underline" @click="reset()">
        {{ t('systemLog.filter.reset') }}
      </button>

      <div class="ml-auto flex">
        <SearchField v-model="filters.search" :placeholder="t('systemLog.filter.searchPlaceholder')"
          :label="t('systemLog.filter.search')" size="sm" history-key="admin-system-logs" @search="apply()" />
      </div>
    </form>

    <p v-if="loading && !page" class="text-slate-600">{{ t('systemLog.loading') }}</p>

    <div v-else-if="page" class="panel-card">
      <div class="mb-3 flex items-baseline justify-between gap-3">
        <h2 class="font-semibold text-slate-900">{{ t('systemLog.events') }}</h2>
        <span class="text-xs text-slate-500">{{ t('systemLog.total', { n: page.meta.total }) }}</span>
      </div>

      <ul class="divide-y divide-slate-100" :class="{ 'opacity-60': loading }">
        <li v-for="row in page.data" :key="row.id" class="py-3">
          <div class="flex flex-wrap items-center gap-2 text-xs">
            <span class="rounded-full px-2 py-0.5 font-semibold" :class="levelClass[row.level]">{{ row.event }}</span>
            <span v-if="row.status" class="rounded-full px-2 py-0.5 font-medium" :class="statusClass[row.status]">
              {{ t(`systemLog.status.${row.status}`) }}
            </span>
            <span class="text-slate-500" :title="row.createdAt ?? ''">{{ formatDateTime(row.createdAt) }}</span>
          </div>

          <p class="mt-1 break-words text-sm text-slate-900">{{ row.message || '—' }}</p>

          <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500">
            <button v-if="row.recipient" type="button" class="break-all text-left underline decoration-dotted hover:text-slate-900" :title="t('systemLog.byRecipient')" @click="filterBy('recipient', row.recipient)">
              ✉ {{ row.recipient }}
            </button>
            <RouterLink v-if="row.user" :to="`/admin/users/${row.user.id}`" class="underline decoration-dotted hover:text-slate-900">
              👤 {{ row.user.email ?? `#${row.user.id}` }}
            </RouterLink>
            <span v-if="row.subjectType">🔗 {{ row.subjectType }} #{{ row.subjectId }}</span>
            <span v-if="row.ip">🌐 {{ row.ip }}</span>
          </div>

          <details v-if="row.context" class="mt-2 text-xs">
            <summary class="cursor-pointer text-slate-500 hover:text-slate-900">{{ t('systemLog.details') }}</summary>
            <pre class="mt-1 max-h-72 overflow-auto whitespace-pre-wrap break-all rounded-md bg-slate-50 p-2 text-slate-700">{{ JSON.stringify(row.context, null, 2) }}</pre>
          </details>
        </li>
        <li v-if="page.data.length === 0" class="py-4 text-slate-500">{{ t('systemLog.empty') }}</li>
      </ul>

      <div v-if="page.meta.lastPage > 1" class="mt-4 flex items-center justify-center gap-3 text-sm">
        <button class="btn btn-sm btn-secondary" :disabled="page.meta.currentPage <= 1 || loading" @click="go(page.meta.currentPage - 1)">‹</button>
        <span class="text-slate-600">{{ page.meta.currentPage }} / {{ page.meta.lastPage }}</span>
        <button class="btn btn-sm btn-secondary" :disabled="page.meta.currentPage >= page.meta.lastPage || loading" @click="go(page.meta.currentPage + 1)">›</button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  fetchSystemLogs,
  type SystemLogLevel,
  type SystemLogPage,
  type SystemLogParams,
  type SystemLogStatus,
} from '@/api/systemLogs'
import SearchField from '@/components/SearchField.vue'
import { useToast } from '@/composables/useToast'
import { localeTag, useI18n } from '@/i18n'

const { t } = useI18n()
const toast = useToast()
const route = useRoute()
const router = useRouter()

const levels: SystemLogLevel[] = ['info', 'warning', 'error']
const statuses: SystemLogStatus[] = ['sent', 'failed', 'skipped', 'ok']

const levelClass: Record<SystemLogLevel, string> = {
  info: 'bg-slate-100 text-slate-700',
  warning: 'bg-amber-100 text-amber-800',
  error: 'bg-red-100 text-red-700',
}

const statusClass: Record<SystemLogStatus, string> = {
  sent: 'bg-green-100 text-green-800',
  ok: 'bg-green-100 text-green-800',
  failed: 'bg-red-100 text-red-700',
  skipped: 'bg-amber-100 text-amber-800',
}

type FilterKey = Exclude<keyof SystemLogParams, 'page'>
const filterKeys: FilterKey[] = ['search', 'channel', 'level', 'status', 'recipient', 'user_id', 'date_from', 'date_to']

// Filtre žijú v adrese, aby sa dal výber poslať odkazom (napr. z detailu používateľa).
const filters = reactive<Record<FilterKey, string>>(
  Object.fromEntries(filterKeys.map(key => [key, ''])) as Record<FilterKey, string>,
)
const page = ref<SystemLogPage | null>(null)
const loading = ref(false)

const anyFilter = computed(() => filterKeys.some(key => filters[key] !== ''))

function readQuery() {
  for (const key of filterKeys) {
    const value = route.query[key]
    filters[key] = typeof value === 'string' ? value : ''
  }
}

async function load() {
  loading.value = true
  try {
    const params: SystemLogParams = {
      ...(Object.fromEntries(filterKeys.map(key => [key, filters[key] || undefined])) as SystemLogParams),
      user_id: filters.user_id ? Number(filters.user_id) : undefined,
      page: Number(route.query.page) || undefined,
    }
    page.value = await fetchSystemLogs(params)
  } catch {
    toast.error(t('systemLog.loadFailed'))
  } finally {
    loading.value = false
  }
}

function pushQuery(pageNumber?: number) {
  const query: Record<string, string> = {}
  for (const key of filterKeys) {
    if (filters[key]) query[key] = filters[key]
  }
  if (pageNumber && pageNumber > 1) query.page = String(pageNumber)
  router.replace({ query })
}

function apply() {
  pushQuery()
}

function go(pageNumber: number) {
  pushQuery(pageNumber)
}

function clear(key: FilterKey) {
  filters[key] = ''
  pushQuery()
}

function reset() {
  for (const key of filterKeys) filters[key] = ''
  pushQuery()
}

function filterBy(key: FilterKey, value: string) {
  filters[key] = value
  pushQuery()
}

onMounted(() => {
  readQuery()
  load()
})

watch(() => route.query, () => {
  readQuery()
  load()
})

const cards = computed(() => {
  const s = page.value?.summary
  if (!s) return []
  const week = (n: number) => t('systemLog.card.week', { n })
  return [
    { label: t('systemLog.card.sent'), value: s.sentDay, note: week(s.sentWeek), alert: false },
    { label: t('systemLog.card.failed'), value: s.failedDay, note: week(s.failedWeek), alert: s.failedDay > 0 },
    { label: t('systemLog.card.errors'), value: s.errorsDay, note: week(s.errorsWeek), alert: s.errorsDay > 0 },
    { label: t('systemLog.card.logins'), value: s.loginsDay, note: t('systemLog.card.day'), alert: false },
    { label: t('systemLog.card.authFailed'), value: s.authFailedDay, note: t('systemLog.card.day'), alert: false },
  ]
})

/** Neznámy kanál (nový bez prekladu) sa ukáže surovým kľúčom. */
function channelLabel(key: string): string {
  const translated = t(`systemLog.channel.${key}` as Parameters<typeof t>[0])
  return translated.startsWith('systemLog.channel.') ? key : translated
}

function formatDateTime(value: string | null): string {
  if (!value) return '—'
  const date = new Date(value)
  return Number.isNaN(date.getTime())
    ? '—'
    : date.toLocaleString(localeTag(), { day: 'numeric', month: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit' })
}
</script>
