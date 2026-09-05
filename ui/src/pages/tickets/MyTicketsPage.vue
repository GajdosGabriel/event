<template>
  <div class="mx-auto w-full max-w-[900px] px-4 py-6">
    <h1 class="text-2xl font-semibold text-slate-900">{{ t('myTickets.title') }}</h1>
    <p class="mt-1 text-sm text-slate-500">{{ t('myTickets.lead') }}</p>

    <!-- Prepínač zoznamov. Nadchádzajúce sú predvolené: kto sem príde, ide
         najčastejšie po vstupenke na dnes alebo zajtra. -->
    <div class="mt-5 flex gap-2">
      <button
        v-for="option in listOptions"
        :key="option.value"
        class="rounded-full border px-4 py-1.5 text-sm font-medium transition"
        :class="option.value === list
          ? 'border-blue-600 bg-blue-600 text-white'
          : 'border-slate-300 bg-white text-slate-700 hover:border-slate-400'"
        @click="switchList(option.value)"
      >
        {{ option.label }}
      </button>
    </div>

    <p v-if="loading" class="mt-6 text-slate-500">{{ t('common.loading') }}</p>

    <section v-else-if="tickets.length === 0" class="mt-6 rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center">
      <p class="font-semibold text-slate-800">{{ list === 'past' ? t('myTickets.emptyPast') : t('myTickets.emptyUpcoming') }}</p>
      <p class="mt-2 text-sm text-slate-600">{{ t('myTickets.emptyLead') }}</p>
      <RouterLink :to="PUBLIC_EVENTS" class="btn btn-primary mt-4">{{ t('myTickets.browse') }}</RouterLink>
    </section>

    <template v-else>
      <ul class="mt-6 grid gap-3">
        <li
          v-for="ticket in tickets"
          :key="ticket.uuid"
          class="rounded-2xl border border-slate-200 bg-white p-4"
        >
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
              <RouterLink
                v-if="ticket.event"
                :to="publicEventPath(ticket.event)"
                class="text-lg font-semibold text-slate-900 hover:underline"
              >
                {{ ticket.event.name }}
              </RouterLink>
              <p v-else class="text-lg font-semibold text-slate-900">{{ t('myTickets.unknownEvent') }}</p>

              <p v-if="ticket.event?.dateRangeLabel" class="mt-0.5 text-sm text-slate-600">
                {{ ticket.event.dateRangeLabel }}
              </p>
              <p v-if="venueLabel(ticket)" class="text-sm text-slate-500">{{ venueLabel(ticket) }}</p>
            </div>

            <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="statusClass(ticket)">
              {{ ticket.statusLabel }}
            </span>
          </div>

          <p class="mt-3 text-sm text-slate-600">
            {{ plural('myTickets.admissions', ticket.admissionsTotal) }}
            <span v-if="ticket.priceAmount">
              · {{ formatPrice(ticket.priceAmount, ticket.priceCurrency) }} ({{ ticket.paymentStatusLabel }})
            </span>
          </p>

          <div class="mt-4 flex flex-wrap gap-2">
            <!-- Detail s QR je tá istá stránka, na akú vedie odkaz z e-mailu. -->
            <RouterLink :to="`/tickets/${ticket.uuid}`" class="btn btn-primary">
              {{ t('myTickets.open') }}
            </RouterLink>
            <a v-if="ticket.event" :href="calendarUrl(ticket.event.id)" class="action-btn">
              {{ t('myTickets.calendar') }}
            </a>
            <button
              v-if="canCancel(ticket)"
              class="action-btn text-red-700"
              :disabled="cancelling === ticket.uuid"
              @click="cancel(ticket)"
            >
              {{ cancelling === ticket.uuid ? t('myTickets.cancelling') : t('myTickets.cancel') }}
            </button>
          </div>
        </li>
      </ul>

      <AppPaginator :current-page="meta.current_page" :last-page="meta.last_page" @change="goToPage" />
    </template>

    <!-- Odbery sú tu, a nie na vlastnej stránke, lebo sú to tie isté „veci,
         na ktoré čakám" — len bez lístka. -->
    <section v-if="subscriptions.length" class="mt-10">
      <h2 class="text-lg font-semibold text-slate-900">{{ t('myTickets.subscriptions') }}</h2>
      <p class="mt-1 text-sm text-slate-500">{{ t('myTickets.subscriptionsLead') }}</p>

      <ul class="mt-3 grid gap-2">
        <li
          v-for="subscription in subscriptions"
          :key="subscription.id"
          class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3"
        >
          <div class="min-w-0">
            <a
              v-if="subscription.target?.url"
              :href="subscription.target.url"
              class="font-medium text-slate-900 hover:underline"
            >
              {{ subscription.target.name }}
            </a>
            <span v-else class="font-medium text-slate-900">{{ subscription.target?.name ?? '—' }}</span>
            <p v-if="subscription.target?.startAt" class="text-sm text-slate-500">
              {{ fmtDateLong(subscription.target.startAt) }}
            </p>
          </div>

          <button
            class="action-btn"
            :disabled="unsubscribing === subscription.id"
            @click="unsubscribe(subscription)"
          >
            {{ t('myTickets.unsubscribe') }}
          </button>
        </li>
      </ul>
    </section>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useHead } from '@vueuse/head'
import { myTickets, mySubscriptions, cancelMySubscription, type MySubscription } from '@/api/me'
import { cancelOwnRegistration } from '@/api/tickets'
import { BASE_URL } from '@/api'
import type { TicketItem } from '@/types'
import { t, plural } from '@/i18n'
import { useToast } from '@/composables/useToast'
import { publicEventPath, PUBLIC_EVENTS } from '@/utils/publicUrl'
import { fmtDateLong } from '@/utils/dateFormat'
import { formatPrice } from '@/utils/money'
import AppPaginator from '@/components/AppPaginator.vue'

const route = useRoute()
const router = useRouter()
const toast = useToast()

type ListKey = 'upcoming' | 'past'

const list = ref<ListKey>(route.query.list === 'past' ? 'past' : 'upcoming')
const page = ref(Number(route.query.page ?? 1) || 1)

const tickets = ref<TicketItem[]>([])
const meta = ref({ current_page: 1, last_page: 1, per_page: 20, total: 0 })
const subscriptions = ref<MySubscription[]>([])
const loading = ref(true)
const cancelling = ref<string | null>(null)
const unsubscribing = ref<number | null>(null)

const listOptions = computed(() => [
  { value: 'upcoming' as const, label: t('myTickets.upcoming') },
  { value: 'past' as const, label: t('myTickets.past') },
])

useHead({ title: `${t('myTickets.title')} | Event` })

async function load() {
  loading.value = true
  try {
    const response = await myTickets({ list: list.value, page: page.value })
    tickets.value = response.data
    meta.value = response.meta
  } finally {
    loading.value = false
  }
}

async function loadSubscriptions() {
  subscriptions.value = await mySubscriptions()
}

function switchList(value: ListKey) {
  if (list.value === value) return
  list.value = value
  page.value = 1
  syncQuery()
}

function goToPage(next: number) {
  page.value = next
  syncQuery()
}

function syncQuery() {
  router.replace({
    query: {
      ...(list.value === 'past' ? { list: 'past' } : {}),
      ...(page.value > 1 ? { page: String(page.value) } : {}),
    },
  })
}

watch(() => [list.value, page.value], load)

function venueLabel(ticket: TicketItem): string | null {
  const event = ticket.event
  if (!event) return null
  return event.venue?.name ?? event.locationName ?? null
}

function statusClass(ticket: TicketItem): string {
  if (ticket.status === 'cancelled') return 'bg-red-100 text-red-700'
  if (ticket.status === 'reserved') return 'bg-amber-100 text-amber-800'
  return 'bg-emerald-100 text-emerald-700'
}

/**
 * Zrušiť sa dá len to, čo ešte len bude a nie je zrušené. Samotné pravidlo
 * („má platné hlavné miesto") drží API — tu ide len o to, aby sa nepýtalo
 * tlačidlo, ktoré nemá čo robiť.
 */
function canCancel(ticket: TicketItem): boolean {
  return list.value === 'upcoming' && ticket.status !== 'cancelled' && Boolean(ticket.event?.id)
}

function calendarUrl(eventId: number): string {
  return `${BASE_URL}/events/${eventId}/calendar.ics`
}

async function cancel(ticket: TicketItem) {
  if (!ticket.event?.id) return
  if (!window.confirm(t('myTickets.cancelConfirm'))) return

  cancelling.value = ticket.uuid
  try {
    await cancelOwnRegistration(ticket.event.id)
    toast.success(t('myTickets.cancelled'))
    await load()
  } catch (e: unknown) {
    toast.error((e as { response?: { data?: { message?: string } } })?.response?.data?.message ?? t('myTickets.cancelFailed'))
  } finally {
    cancelling.value = null
  }
}

async function unsubscribe(subscription: MySubscription) {
  unsubscribing.value = subscription.id
  try {
    await cancelMySubscription(subscription.id)
    subscriptions.value = subscriptions.value.filter((s) => s.id !== subscription.id)
    toast.success(t('myTickets.unsubscribed'))
  } catch {
    toast.error(t('myTickets.unsubscribeFailed'))
  } finally {
    unsubscribing.value = null
  }
}

load()
loadSubscriptions()
</script>
