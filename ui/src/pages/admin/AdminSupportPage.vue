<template>
  <div class="grid gap-4">
    <div>
      <h1 class="text-2xl font-semibold text-slate-900">{{ t('support.title') }}</h1>
      <p class="max-w-3xl text-sm text-slate-600">{{ t('support.admin.intro') }}</p>
    </div>

    <div class="flex flex-wrap items-center gap-2">
      <!-- „Čaká na podporu" je predvolený filter — to je práca na dnes -->
      <button v-for="f in FILTERS" :key="f" type="button" class="btn"
        :class="status === f ? 'btn-primary' : 'btn-secondary'" @click="setStatus(f)">
        {{ t(`support.admin.filters.${f}`) }}
        <span v-if="f !== 'all' && counts[f] !== undefined" class="rounded-full px-1.5 text-[11px] font-semibold"
          :class="f === 'open' && counts.open ? 'bg-red-100 text-red-700' : 'bg-slate-100 text-slate-500'">{{ counts[f] }}</span>
      </button>
      <div class="ml-auto flex">
        <SearchField v-model="search" :placeholder="t('support.admin.search')" size="sm"
          history-key="admin-support" :debounce="300" @search="load(1)" />
      </div>
    </div>

    <p v-if="loading && !tickets.length" class="text-slate-500">{{ t('support.loading') }}</p>
    <p v-else-if="error" class="text-red-600">{{ error }}</p>

    <div v-else class="grid gap-4 lg:grid-cols-[minmax(0,24rem)_minmax(0,1fr)]">
      <div class="grid content-start gap-3" :class="{ 'hidden lg:grid': activeId }">
        <p v-if="!tickets.length" class="panel-card text-sm text-slate-500">
          {{ status === 'open' ? t('support.admin.empty') : t('support.admin.emptyAll') }}
        </p>
        <ul v-else class="divide-y divide-slate-100 overflow-hidden rounded-2xl border border-slate-200 bg-white">
          <li v-for="ticket in tickets" :key="ticket.id">
            <RouterLink :to="{ path: `/admin/podpora/${ticket.id}`, query: route.query }" class="ticket-row"
              :class="{ active: activeId === ticket.id }">
              <span class="flex items-center gap-2">
                <span v-if="ticket.unread" class="size-2 shrink-0 rounded-full bg-amber-500" :aria-label="t('support.newReply')" />
                <span class="font-mono text-xs text-slate-400">{{ ticket.reference }}</span>
                <span class="truncate text-slate-900" :class="ticket.unread ? 'font-semibold' : 'font-medium'">{{ ticket.subject }}</span>
              </span>
              <span class="mt-0.5 block truncate text-xs text-slate-500">
                {{ ticket.userName }}<template v-if="ticket.canal"> · {{ ticket.canal.name }}</template> · {{ ticket.category.label }}
              </span>
              <span class="mt-1 flex items-center gap-2">
                <SupportStatusBadge :status="ticket.status" />
                <span class="truncate text-xs text-slate-500">
                  <span v-if="ticket.lastFromStaff" class="font-medium text-teal-700">{{ t('support.staffPrefix') }}</span>
                  {{ ticket.excerpt }}
                </span>
                <span class="ml-auto shrink-0 text-[11px] text-slate-400">{{ formatDate(ticket.lastActivityAt) }}</span>
              </span>
            </RouterLink>
          </li>
        </ul>
        <AppPaginator v-if="lastPage > 1" :current-page="page" :last-page="lastPage" @change="load" />
      </div>

      <div class="min-w-0" :class="{ 'hidden lg:block': !activeId }">
        <RouterLink v-if="activeId" :to="{ path: '/admin/podpora', query: route.query }"
          class="mb-3 inline-block text-sm text-slate-500 no-underline hover:text-slate-800 lg:hidden">
          ← {{ t('support.back') }}
        </RouterLink>
        <p v-if="threadError" class="panel-card text-sm text-red-600">{{ threadError }}</p>
        <SupportThread v-else-if="thread" :ticket="thread" details @updated="onUpdated" />
        <p v-else-if="activeId" class="text-slate-500">{{ t('support.loading') }}</p>
        <p v-else class="rounded-2xl border border-dashed border-slate-200 p-6 text-slate-400">{{ t('support.pick') }}</p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import AppPaginator from '@/components/AppPaginator.vue'
import SearchField from '@/components/SearchField.vue'
import SupportStatusBadge from '@/components/support/SupportStatusBadge.vue'
import SupportThread from '@/components/support/SupportThread.vue'
import { indexSupportTickets, showSupportTicket, type SupportStatus, type SupportTicket } from '@/api/support'
import { currentLocale, useI18n } from '@/i18n'

/** Admin → Podpora: schránka všetkých vlákien, odpovedá sa v mene „Podpora". */
const FILTERS = ['open', 'answered', 'closed', 'all'] as const
type Filter = typeof FILTERS[number]

const { t } = useI18n()
const route = useRoute()

const tickets = ref<SupportTicket[]>([])
const counts = ref<Partial<Record<SupportStatus, number>>>({})
const loading = ref(false)
const error = ref<string | null>(null)
const status = ref<Filter>(FILTERS.includes(route.query.status as Filter) ? route.query.status as Filter : 'open')
const search = ref('')
const page = ref(1)
const lastPage = ref(1)
const thread = ref<SupportTicket | null>(null)
const threadError = ref<string | null>(null)

const activeId = computed(() => (route.params.id ? Number(route.params.id) : null))

function formatDate(value: string) {
  return new Date(value).toLocaleDateString(currentLocale(), { day: 'numeric', month: 'numeric' })
}

async function load(targetPage = 1) {
  loading.value = true
  error.value = null
  try {
    const result = await indexSupportTickets({
      all: true,
      page: targetPage,
      status: status.value === 'all' ? undefined : status.value,
      search: search.value.trim() || undefined,
    })
    tickets.value = result.data
    counts.value = result.meta.counts ?? {}
    page.value = result.meta.current_page
    lastPage.value = result.meta.last_page
  } catch {
    error.value = t('support.loadFailed')
  } finally {
    loading.value = false
  }
}

function setStatus(value: Filter) {
  status.value = value
  load(1)
}

async function openThread(id: number | null) {
  thread.value = null
  threadError.value = null
  if (!id) return
  try {
    thread.value = await showSupportTicket(id)
    const row = tickets.value.find((ticket) => ticket.id === id)
    if (row) row.unread = false
  } catch (e: unknown) {
    const code = (e as { response?: { status?: number } })?.response?.status
    threadError.value = code === 404 ? t('support.notFound') : t('support.loadFailed')
  }
}

function onUpdated(ticket: SupportTicket) {
  thread.value = ticket
  const index = tickets.value.findIndex((row) => row.id === ticket.id)
  if (index >= 0) tickets.value[index] = { ...ticket, unread: false }
  // Odpoveď presúva vlákno medzi stavmi — počty vo filtroch musia sedieť.
  load(page.value)
}

watch(activeId, openThread)

onMounted(async () => {
  await load(1)
  if (activeId.value) await openThread(activeId.value)
})
</script>

<style scoped>
@reference "tailwindcss";

.ticket-row {
  @apply block border-l-2 border-transparent px-4 py-3 no-underline hover:bg-slate-50;
}
.ticket-row.active { @apply border-l-amber-500 bg-slate-50; }
</style>
