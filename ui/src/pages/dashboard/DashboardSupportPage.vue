<template>
  <div class="mx-auto my-5 w-full max-w-[1320px] px-4">
    <div class="mb-4">
      <h1 class="text-2xl font-semibold text-slate-900">{{ t('messages.title') }}</h1>
      <p class="text-sm text-slate-500">{{ t('support.lead') }}</p>
    </div>

    <MessagesTabs active="support" :inbox-unread="counts.inbox" :support-unread="counts.support" />

    <p v-if="loading && !tickets.length" class="text-slate-500">{{ t('support.loading') }}</p>
    <p v-else-if="error" class="text-red-600">{{ error }}</p>

    <div v-else class="grid gap-4 lg:grid-cols-[minmax(0,22rem)_minmax(0,1fr)]">
      <!-- Zoznam mojich vlákien; na mobile sa pri otvorenom detaile skryje -->
      <div class="grid content-start gap-3" :class="{ 'hidden lg:grid': mode !== 'list' }">
        <button type="button" class="new-ticket" :class="{ active: mode === 'new' }" @click="startNew">
          <span class="grid size-9 shrink-0 place-items-center rounded-full bg-teal-600 text-white" aria-hidden="true">
            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M12 5v14M5 12h14" /></svg>
          </span>
          <span>
            <span class="block text-sm font-semibold text-slate-900">{{ t('support.newTicket') }}</span>
            <span class="block text-xs text-slate-500">{{ t('support.newTicketHint') }}</span>
          </span>
        </button>

        <p v-if="!tickets.length" class="rounded-2xl border border-dashed border-slate-200 p-4 text-sm text-slate-400">
          {{ t('support.empty') }}
        </p>

        <template v-else>
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ t('support.myTickets') }}</p>
          <ul class="divide-y divide-slate-100 overflow-hidden rounded-2xl border border-slate-200 bg-white">
            <li v-for="ticket in tickets" :key="ticket.id">
              <RouterLink :to="`/dashboard/spravy/podpora/${ticket.id}`" class="ticket-row"
                :class="{ active: activeId === ticket.id }">
                <span class="flex items-center gap-2">
                  <span v-if="ticket.unread" class="size-2 shrink-0 rounded-full bg-teal-500" :aria-label="t('support.newReply')" />
                  <span class="truncate text-slate-900" :class="ticket.unread ? 'font-semibold' : 'font-medium'">{{ ticket.subject }}</span>
                  <span class="ml-auto shrink-0 text-xs text-slate-400">{{ formatDate(ticket.lastActivityAt) }}</span>
                </span>
                <span class="mt-1 flex items-center gap-2">
                  <SupportStatusBadge :status="ticket.status" />
                  <span class="truncate text-xs text-slate-500">
                    <span v-if="ticket.lastFromStaff" class="font-medium text-teal-700">{{ t('support.staffPrefix') }}</span>
                    {{ ticket.excerpt }}
                  </span>
                </span>
              </RouterLink>
            </li>
          </ul>
          <AppPaginator v-if="lastPage > 1" :current-page="page" :last-page="lastPage" @change="load" />
        </template>
      </div>

      <!-- Pravý panel: formulár, vlákno alebo „ako to funguje" -->
      <div class="min-w-0" :class="{ 'hidden lg:block': mode === 'list' }">
        <RouterLink v-if="mode !== 'list'" to="/dashboard/spravy/podpora" class="mb-3 inline-block text-sm text-slate-500 no-underline hover:text-slate-800 lg:hidden">
          ← {{ t('support.back') }}
        </RouterLink>

        <SupportTicketForm v-if="mode === 'new'" :categories="categories"
          :from-page="fromPage" :initial-category="initialCategory"
          :cancellable="tickets.length > 0" @created="onCreated" @cancel="router.push('/dashboard/spravy/podpora')" />

        <template v-else-if="mode === 'thread'">
          <p v-if="threadError" class="rounded-2xl border border-slate-200 bg-white p-4 text-sm text-red-600">{{ threadError }}</p>
          <template v-else-if="thread">
            <p v-if="justSent" class="mb-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
              {{ t('support.sent') }}
            </p>
            <SupportThread :ticket="thread" @updated="onUpdated" />
          </template>
          <p v-else class="text-slate-500">{{ t('support.loading') }}</p>
        </template>

        <section v-else class="rounded-2xl border border-slate-200 bg-white p-5">
          <p class="font-semibold text-slate-900">{{ t('support.how.title') }}</p>
          <ol class="mt-4 grid gap-4">
            <li v-for="(step, i) in steps" :key="i" class="flex gap-3">
              <span class="grid size-6 shrink-0 place-items-center rounded-full bg-teal-50 text-xs font-bold text-teal-700">{{ i + 1 }}</span>
              <span class="text-sm text-slate-600">{{ step }}</span>
            </li>
          </ol>
          <p class="mt-5 text-sm text-slate-400">{{ t('support.pick') }}</p>
        </section>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppPaginator from '@/components/AppPaginator.vue'
import MessagesTabs from '@/components/support/MessagesTabs.vue'
import SupportStatusBadge from '@/components/support/SupportStatusBadge.vue'
import SupportThread from '@/components/support/SupportThread.vue'
import SupportTicketForm from '@/components/support/SupportTicketForm.vue'
import { unreadCounts } from '@/api/messages'
import {
  indexSupportTickets,
  showSupportTicket,
  type SupportCategory,
  type SupportPage,
  type SupportTicket,
} from '@/api/support'
import { currentLocale, useI18n } from '@/i18n'

/**
 * Správy → Podpora. Adresa nesie stav: `/podpora` (zoznam), `/podpora?new=1`
 * (formulár, voliteľne s `from` a `category`), `/podpora/:id` (vlákno) — odkaz
 * zo zvončeka aj z e-mailu tak vedie priamo do konverzácie.
 */
const { t } = useI18n()
const route = useRoute()
const router = useRouter()

const tickets = ref<SupportTicket[]>([])
const categories = ref<SupportPage['meta']['categories']>([])
const loading = ref(false)
const error = ref<string | null>(null)
const page = ref(1)
const lastPage = ref(1)
const thread = ref<SupportTicket | null>(null)
const threadError = ref<string | null>(null)
const counts = ref({ inbox: 0, support: 0 })

const activeId = computed(() => (route.params.id ? Number(route.params.id) : null))
const mode = computed<'list' | 'new' | 'thread'>(() => {
  if (activeId.value) return 'thread'
  if (route.query.new || (!loading.value && !tickets.value.length && !error.value)) return 'new'
  return 'list'
})
const justSent = computed(() => route.query.sent === '1')
const fromPage = computed(() => (typeof route.query.from === 'string' ? route.query.from : null))
const initialCategory = computed(() => (typeof route.query.category === 'string' ? route.query.category as SupportCategory : null))
const steps = computed(() => [t('support.how.step1'), t('support.how.step2'), t('support.how.step3')])

function formatDate(value: string) {
  return new Date(value).toLocaleDateString(currentLocale(), { day: 'numeric', month: 'numeric' })
}

async function refreshCounts() {
  try {
    counts.value = await unreadCounts()
  } catch {
    /* odznaky nie sú kritické */
  }
}

async function load(targetPage = 1) {
  loading.value = true
  error.value = null
  try {
    const result = await indexSupportTickets({ page: targetPage })
    tickets.value = result.data
    categories.value = result.meta.categories
    page.value = result.meta.current_page
    lastPage.value = result.meta.last_page
  } catch {
    error.value = t('support.loadFailed')
  } finally {
    loading.value = false
  }
}

async function openThread(id: number | null) {
  thread.value = null
  threadError.value = null
  if (!id) return
  try {
    thread.value = await showSupportTicket(id)
    // Backend otvorením vlákno označil za pozreté — zoznam aj odznaky dorovnáme.
    const row = tickets.value.find((ticket) => ticket.id === id)
    if (row) row.unread = false
    await refreshCounts()
  } catch (e: unknown) {
    const status = (e as { response?: { status?: number } })?.response?.status
    threadError.value = status === 404 ? t('support.notFound') : t('support.loadFailed')
  }
}

function startNew() {
  router.push({ path: '/dashboard/spravy/podpora', query: { new: '1' } })
}

function onCreated(ticket: SupportTicket) {
  tickets.value.unshift(ticket)
  router.push({ path: `/dashboard/spravy/podpora/${ticket.id}`, query: { sent: '1' } })
}

function onUpdated(ticket: SupportTicket) {
  thread.value = ticket
  const index = tickets.value.findIndex((row) => row.id === ticket.id)
  if (index >= 0) tickets.value[index] = { ...ticket, unread: false }
}

watch(activeId, openThread)

onMounted(async () => {
  await Promise.all([load(1), refreshCounts()])
  if (activeId.value) await openThread(activeId.value)
})
</script>

<style scoped>
@reference "tailwindcss";

.new-ticket {
  @apply flex w-full cursor-pointer items-center gap-3 rounded-2xl border-2 border-dashed border-slate-300 bg-white p-3 text-left transition-colors hover:border-teal-400 hover:bg-teal-50/40;
}
.new-ticket.active { @apply border-teal-500 bg-teal-50/60; }
.ticket-row {
  @apply block border-l-2 border-transparent px-4 py-3 no-underline hover:bg-slate-50;
}
.ticket-row.active { @apply border-l-teal-500 bg-slate-50; }
</style>
