<template>
  <div class="grid gap-4">
    <div>
      <h1 class="text-2xl font-semibold text-slate-900">{{ t('adminQuestions.title') }}</h1>
      <p class="max-w-3xl text-sm text-slate-600">{{ t('adminQuestions.intro') }}</p>
    </div>

    <div v-if="page" class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-5">
      <div v-for="card in cards" :key="card.label" class="panel-card">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ card.label }}</p>
        <p class="mt-1 text-2xl font-semibold text-slate-900">{{ card.value }}</p>
      </div>
    </div>

    <form class="panel-card flex flex-wrap items-end gap-3 text-sm" @submit.prevent="apply()">
      <label class="grid gap-1">
        <span class="text-xs text-slate-500">{{ t('adminQuestions.filter.status') }}</span>
        <select v-model="filters.status" class="form-input h-9 w-auto" @change="apply()">
          <option value="">{{ t('adminQuestions.filter.all') }}</option>
          <option v-for="status in statuses" :key="status" :value="status">{{ t(`adminQuestions.status.${status}`) }}</option>
        </select>
      </label>
      <label class="grid gap-1">
        <span class="text-xs text-slate-500">{{ t('adminQuestions.filter.visibility') }}</span>
        <select v-model="filters.visibility" class="form-input h-9 w-auto" @change="apply()">
          <option value="">{{ t('adminQuestions.filter.all') }}</option>
          <option value="public">{{ t('adminQuestions.visibility.public') }}</option>
          <option value="private">{{ t('adminQuestions.visibility.private') }}</option>
        </select>
      </label>
      <label class="grid gap-1">
        <span class="text-xs text-slate-500">{{ t('adminQuestions.filter.answered') }}</span>
        <select v-model="filters.answered" class="form-input h-9 w-auto" @change="apply()">
          <option value="">{{ t('adminQuestions.filter.all') }}</option>
          <option value="yes">{{ t('adminQuestions.filter.answeredYes') }}</option>
          <option value="no">{{ t('adminQuestions.filter.answeredNo') }}</option>
        </select>
      </label>
      <label class="grid gap-1">
        <span class="text-xs text-slate-500">{{ t('adminQuestions.filter.from') }}</span>
        <input v-model="filters.date_from" type="date" class="form-input h-9 w-auto" @change="apply()">
      </label>
      <label class="grid gap-1">
        <span class="text-xs text-slate-500">{{ t('adminQuestions.filter.to') }}</span>
        <input v-model="filters.date_to" type="date" class="form-input h-9 w-auto" @change="apply()">
      </label>
      <button v-if="anyFilter" type="button" class="text-xs text-slate-500 underline" @click="reset()">
        {{ t('adminQuestions.filter.reset') }}
      </button>

      <div class="ml-auto flex">
        <SearchField v-model="filters.search" :placeholder="t('adminQuestions.filter.searchPlaceholder')"
          :label="t('adminQuestions.filter.search')" size="sm" history-key="admin-questions" @search="apply()" />
      </div>
    </form>

    <p v-if="loading && !page" class="text-slate-600">{{ t('adminQuestions.loading') }}</p>

    <div v-else-if="page" class="panel-card">
      <div class="mb-3 flex items-baseline justify-between gap-3">
        <h2 class="font-semibold text-slate-900">{{ t('adminQuestions.list') }}</h2>
        <span class="text-xs text-slate-500">{{ t('adminQuestions.total', { n: page.meta.total }) }}</span>
      </div>

      <ul class="divide-y divide-slate-100" :class="{ 'opacity-60': loading }">
        <li v-for="row in page.data" :key="row.id" class="grid gap-1 py-3">
          <div class="flex flex-wrap items-center gap-2 text-xs">
            <span class="rounded-full px-2 py-0.5 font-semibold" :class="statusClass[row.status]">{{ row.statusLabel }}</span>
            <span v-if="row.visibility === 'private'" class="rounded-full bg-violet-100 px-2 py-0.5 font-medium text-violet-800">{{ row.visibilityLabel }}</span>
            <span v-if="row.answeredAt" class="rounded-full bg-green-100 px-2 py-0.5 font-medium text-green-800">{{ t('adminQuestions.answered') }}</span>
            <span v-if="row.upvotesCount > 0" class="text-slate-600">▲ {{ row.upvotesCount }}</span>
            <span v-if="row.wantsEmail" class="text-slate-600" :title="t('adminQuestions.wantsEmailHint')">✉ {{ t('adminQuestions.wantsEmail') }}</span>
            <span class="text-slate-500" :title="row.createdAt ?? ''">{{ formatDateTime(row.createdAt) }}</span>
          </div>

          <p class="whitespace-pre-line break-words text-sm text-slate-900">{{ row.body }}</p>

          <p v-if="row.answerBody" class="whitespace-pre-line break-words border-l-2 border-green-300 pl-3 text-sm text-slate-700">
            {{ row.answerBody }}
          </p>

          <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500">
            <span>✍ {{ row.authorName || t('adminQuestions.anonymous') }}</span>
            <RouterLink v-if="row.event" :to="`/admin/events/${row.event.id}`" class="underline decoration-dotted hover:text-slate-900">
              📅 {{ row.event.name }}<template v-if="row.workshop"> · {{ row.workshop }}</template>
            </RouterLink>
            <RouterLink v-if="row.userId" :to="`/admin/users/${row.userId}`" class="underline decoration-dotted hover:text-slate-900">
              👤 {{ t('adminQuestions.account') }}
            </RouterLink>
          </div>
        </li>
        <li v-if="page.data.length === 0" class="py-4 text-slate-500">{{ t('adminQuestions.empty') }}</li>
      </ul>

      <AppPaginator :current-page="page.meta.currentPage" :last-page="page.meta.lastPage" @change="go" />
    </div>
  </div>
</template>

<script setup lang="ts">
import AppPaginator from '@/components/AppPaginator.vue'
import SearchField from '@/components/SearchField.vue'
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { fetchAdminQuestions, type AdminQuestionPage, type AdminQuestionParams, type AdminQuestionRow } from '@/api/adminOverview'
import { useToast } from '@/composables/useToast'
import { localeTag, useI18n } from '@/i18n'

const { t } = useI18n()
const toast = useToast()
const route = useRoute()
const router = useRouter()

const statuses: AdminQuestionRow['status'][] = ['pending', 'published', 'hidden']

const statusClass: Record<AdminQuestionRow['status'], string> = {
  pending: 'bg-amber-100 text-amber-800',
  published: 'bg-sky-100 text-sky-800',
  hidden: 'bg-slate-100 text-slate-500',
}

type FilterKey = Exclude<keyof AdminQuestionParams, 'page'>
const filterKeys: FilterKey[] = ['search', 'status', 'visibility', 'answered', 'date_from', 'date_to']

// Filtre žijú v adrese, aby sa dal výber poslať odkazom.
const filters = reactive<Record<FilterKey, string>>(
  Object.fromEntries(filterKeys.map(key => [key, ''])) as Record<FilterKey, string>,
)
const page = ref<AdminQuestionPage | null>(null)
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
    page.value = await fetchAdminQuestions({
      ...(filters as AdminQuestionParams),
      page: Number(route.query.page) || undefined,
    })
  } catch {
    toast.error(t('adminQuestions.loadFailed'))
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

function reset() {
  for (const key of filterKeys) filters[key] = ''
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
  return [
    { label: t('adminQuestions.card.total'), value: s.total },
    { label: t('adminQuestions.card.day'), value: s.day },
    { label: t('adminQuestions.card.week'), value: s.week },
    { label: t('adminQuestions.card.pending'), value: s.pending },
    { label: t('adminQuestions.card.unanswered'), value: s.unanswered },
  ]
})

function formatDateTime(value: string | null): string {
  if (!value) return '—'
  const date = new Date(value)
  return Number.isNaN(date.getTime())
    ? '—'
    : date.toLocaleString(localeTag(), { day: 'numeric', month: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}
</script>
