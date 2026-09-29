<template>
  <div class="grid gap-4">
    <div>
      <h1 class="text-2xl font-semibold text-slate-900">{{ t('adminTickets.title') }}</h1>
      <p class="max-w-3xl text-sm text-slate-600">{{ t('adminTickets.intro') }}</p>
    </div>

    <div v-if="page" class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-5">
      <div v-for="card in cards" :key="card.label" class="panel-card">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ card.label }}</p>
        <p class="mt-1 text-2xl font-semibold text-slate-900">{{ card.value }}</p>
      </div>
    </div>

    <form class="panel-card flex flex-wrap items-end gap-3 text-sm" @submit.prevent="apply()">
      <label class="grid gap-1">
        <span class="text-xs text-slate-500">{{ t('adminTickets.filter.status') }}</span>
        <select v-model="filters.status" class="form-input h-9 w-auto" @change="apply()">
          <option value="">{{ t('adminTickets.filter.all') }}</option>
          <option v-for="status in statuses" :key="status" :value="status">{{ t(`adminTickets.status.${status}`) }}</option>
        </select>
      </label>
      <label class="grid gap-1">
        <span class="text-xs text-slate-500">{{ t('adminTickets.filter.payment') }}</span>
        <select v-model="filters.payment" class="form-input h-9 w-auto" @change="apply()">
          <option value="">{{ t('adminTickets.filter.all') }}</option>
          <option v-for="payment in payments" :key="payment" :value="payment">{{ t(`adminTickets.payment.${payment}`) }}</option>
        </select>
      </label>
      <label class="grid gap-1">
        <span class="text-xs text-slate-500">{{ t('adminTickets.filter.price') }}</span>
        <select v-model="filters.price" class="form-input h-9 w-auto" @change="apply()">
          <option value="">{{ t('adminTickets.filter.all') }}</option>
          <option value="free">{{ t('adminTickets.filter.free') }}</option>
          <option value="paid">{{ t('adminTickets.filter.paid') }}</option>
        </select>
      </label>
      <label class="grid gap-1">
        <span class="text-xs text-slate-500">{{ t('adminTickets.filter.from') }}</span>
        <input v-model="filters.date_from" type="date" class="form-input h-9 w-auto" @change="apply()">
      </label>
      <label class="grid gap-1">
        <span class="text-xs text-slate-500">{{ t('adminTickets.filter.to') }}</span>
        <input v-model="filters.date_to" type="date" class="form-input h-9 w-auto" @change="apply()">
      </label>
      <button v-if="anyFilter" type="button" class="text-xs text-slate-500 underline" @click="reset()">
        {{ t('adminTickets.filter.reset') }}
      </button>

      <div class="ml-auto flex">
        <SearchField v-model="filters.search" :placeholder="t('adminTickets.filter.searchPlaceholder')"
          :label="t('adminTickets.filter.search')" size="sm" history-key="admin-tickets" @search="apply()" />
      </div>
    </form>

    <p v-if="loading && !page" class="text-slate-600">{{ t('adminTickets.loading') }}</p>

    <div v-else-if="page" class="panel-card">
      <div class="mb-3 flex items-baseline justify-between gap-3">
        <h2 class="font-semibold text-slate-900">{{ t('adminTickets.list') }}</h2>
        <span class="text-xs text-slate-500">{{ t('adminTickets.total', { n: page.meta.total }) }}</span>
      </div>

      <ul class="divide-y divide-slate-100" :class="{ 'opacity-60': loading }">
        <li v-for="row in page.data" :key="row.id" class="grid gap-1 py-3">
          <div class="flex flex-wrap items-center gap-2 text-xs">
            <span class="rounded-full px-2 py-0.5 font-semibold" :class="statusClass[row.status]">{{ row.statusLabel }}</span>
            <span class="rounded-full px-2 py-0.5 font-medium" :class="paymentClass[row.paymentStatus]">{{ row.paymentStatusLabel }}</span>
            <span class="font-semibold text-slate-700">{{ formatPriceOrFree(row.priceAmount, row.priceCurrency) }}</span>
            <span class="text-slate-500" :title="row.createdAt ?? ''">{{ formatDateTime(row.createdAt) }}</span>
          </div>

          <p class="text-sm text-slate-900">
            <span class="font-semibold">{{ row.holderName }}</span>
            <span class="text-slate-500"> · {{ row.holderEmail }}</span>
            <span v-if="row.holderPhone" class="text-slate-500"> · {{ row.holderPhone }}</span>
          </p>

          <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500">
            <RouterLink v-if="row.event" :to="`/admin/events/${row.event.id}`" class="underline decoration-dotted hover:text-slate-900">
              📅 {{ row.event.name }}<template v-if="row.event.startAt"> ({{ formatDateTime(row.event.startAt) }})</template>
            </RouterLink>
            <span>🎟 {{ t('adminTickets.admissions', { n: row.admissionsTotal, checked: row.checkedInCount }) }}</span>
            <RouterLink v-if="row.userId" :to="`/admin/users/${row.userId}`" class="underline decoration-dotted hover:text-slate-900">
              👤 {{ t('adminTickets.account') }}
            </RouterLink>
          </div>
        </li>
        <li v-if="page.data.length === 0" class="py-4 text-slate-500">{{ t('adminTickets.empty') }}</li>
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
import { fetchAdminTickets, type AdminTicketPage, type AdminTicketParams, type AdminTicketRow } from '@/api/adminOverview'
import { useToast } from '@/composables/useToast'
import { localeTag, useI18n } from '@/i18n'
import { formatPriceOrFree } from '@/utils/money'

const { t } = useI18n()
const toast = useToast()
const route = useRoute()
const router = useRouter()

const statuses: AdminTicketRow['status'][] = ['reserved', 'confirmed', 'cancelled']
const payments: AdminTicketRow['paymentStatus'][] = ['none', 'pending', 'paid', 'failed', 'refunded']

const statusClass: Record<AdminTicketRow['status'], string> = {
  reserved: 'bg-amber-100 text-amber-800',
  confirmed: 'bg-green-100 text-green-800',
  cancelled: 'bg-slate-100 text-slate-500 line-through',
}

const paymentClass: Record<AdminTicketRow['paymentStatus'], string> = {
  none: 'bg-slate-100 text-slate-700',
  pending: 'bg-amber-100 text-amber-800',
  paid: 'bg-green-100 text-green-800',
  failed: 'bg-red-100 text-red-700',
  refunded: 'bg-sky-100 text-sky-800',
}

type FilterKey = Exclude<keyof AdminTicketParams, 'page'>
const filterKeys: FilterKey[] = ['search', 'status', 'payment', 'price', 'date_from', 'date_to']

// Filtre žijú v adrese, aby sa dal výber poslať odkazom.
const filters = reactive<Record<FilterKey, string>>(
  Object.fromEntries(filterKeys.map(key => [key, ''])) as Record<FilterKey, string>,
)
const page = ref<AdminTicketPage | null>(null)
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
    page.value = await fetchAdminTickets({
      ...(filters as AdminTicketParams),
      page: Number(route.query.page) || undefined,
    })
  } catch {
    toast.error(t('adminTickets.loadFailed'))
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
    { label: t('adminTickets.card.total'), value: s.total },
    { label: t('adminTickets.card.day'), value: s.day },
    { label: t('adminTickets.card.week'), value: s.week },
    { label: t('adminTickets.card.paid'), value: s.paid },
    { label: t('adminTickets.card.cancelled'), value: s.cancelled },
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
