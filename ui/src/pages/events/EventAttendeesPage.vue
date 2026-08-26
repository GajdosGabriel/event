<template>
  <div class="mx-auto my-5 w-full max-w-[1320px] px-4">
    <EventTicketsTabs :event-id="eventId" />

    <div class="mb-4">
      <h1 class="text-2xl font-semibold text-slate-900">{{ eventName || t('tickets.attendees.title') }}</h1>
      <p v-if="eventName" class="text-sm text-slate-500">{{ t('tickets.attendees.title') }}</p>
    </div>

    <!-- Zoznam je užší, vpravo stojí prehľad: pri vchode sa organizátor pozerá
         hlavne naň a tabuľku len prehľadáva. -->
    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_20rem]">
      <div class="min-w-0">
        <div class="mb-4 flex flex-wrap items-start gap-2">
          <ResourceFilterBar
            v-model:search="filters.search"
            v-model:status="filters.status"
            :status-options="statusOptions"
            :sort-options="[]"
            :extra-active="extraActive"
            collapsible
            history-key="event-attendees"
            class="flex-1"
            @change="load(1)"
            @reset="resetExtraFilters"
          >
            <template #filters>
              <select v-model="filters.ticketTypeId" class="form-input w-auto"
                :title="t('filters.attendees.typeTitle')" @change="load(1)">
                <option value="">{{ t('filters.attendees.allTypes') }}</option>
                <option v-for="type in ticketTypes" :key="type.id" :value="String(type.id)">{{ type.name }}</option>
              </select>

              <select v-model="filters.checkin" class="form-input w-auto"
                :title="t('filters.attendees.checkinTitle')" @change="load(1)">
                <option value="">{{ t('filters.attendees.allCheckin') }}</option>
                <option value="arrived">{{ t('filters.attendees.checkinArrived') }}</option>
                <option value="pending">{{ t('filters.attendees.checkinPending') }}</option>
              </select>

              <select v-model="filters.payment" class="form-input w-auto"
                :title="t('filters.attendees.paymentTitle')" @change="load(1)">
                <option value="">{{ t('filters.attendees.allPayments') }}</option>
                <option v-for="opt in paymentOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
              </select>
            </template>
          </ResourceFilterBar>

          <button type="button" class="btn btn-secondary" :disabled="exporting" @click="onExport">
            {{ exporting ? t('tickets.attendees.exporting') : t('tickets.attendees.export') }}
          </button>
          <button type="button" class="btn btn-secondary" @click="openBulk">{{ t('tickets.attendees.bulk') }}</button>
        </div>

        <!-- Hromadný e-mail účastníkom -->
        <div v-if="bulk.show" class="fixed inset-0 z-50 grid place-items-center bg-slate-900/40 p-4" @click.self="bulk.show = false">
          <form class="w-full max-w-lg rounded-2xl bg-white p-5 shadow-xl" @submit.prevent="sendBulk">
            <h2 class="mb-1 text-lg font-semibold text-slate-900">{{ t('tickets.attendees.bulkTitle') }}</h2>
            <p class="mb-4 text-sm text-slate-500">
              {{ t('tickets.attendees.bulkLead', { recipients: plural('tickets.attendees.counts.recipients', bulk.recipients) }) }}
            </p>

            <FormField v-model="bulk.subject" :label="t('tickets.attendees.subject')" required maxlength="150" />
            <FormField v-model="bulk.body" type="textarea" :label="t('tickets.attendees.body')" required rows="7" maxlength="5000" class="mt-3" />

            <p v-if="bulk.error" class="mt-2 text-sm text-red-600">{{ bulk.error }}</p>

            <div class="mt-4 flex justify-end gap-2">
              <button type="button" class="btn btn-secondary" @click="bulk.show = false">{{ t('tickets.attendees.cancel') }}</button>
              <button type="submit" class="btn btn-primary" :disabled="bulk.sending || !bulk.recipients">
                {{ bulk.sending ? t('tickets.attendees.sending') : t('tickets.attendees.send') }}
              </button>
            </div>
          </form>
        </div>

        <p v-if="loading" class="text-slate-500">{{ t('tickets.attendees.loading') }}</p>
        <p v-else-if="error" class="text-red-600">{{ error }}</p>
        <p v-else-if="!tickets.length" class="text-slate-400">{{ t('tickets.attendees.empty') }}</p>

        <div v-else class="overflow-x-auto rounded-2xl border border-slate-200 bg-white">
          <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
              <tr>
                <th class="px-4 py-3">
                  <button type="button" class="th-sort" @click="toggleSort('id')">
                    {{ t('tickets.attendees.colId') }}
                    <span :class="sortArrow('id') ? 'text-slate-600' : 'text-slate-300'">{{ sortArrow('id') || '▾' }}</span>
                  </button>
                </th>
                <th class="px-4 py-3">
                  <button type="button" class="th-sort" @click="toggleSort('surname')">
                    {{ t('tickets.attendees.colName') }}
                    <span :class="sortArrow('surname') ? 'text-slate-600' : 'text-slate-300'">{{ sortArrow('surname') || '▾' }}</span>
                  </button>
                </th>
                <th class="px-4 py-3">{{ t('tickets.attendees.colTickets') }}</th>
                <th class="px-4 py-3">{{ t('tickets.attendees.colCheckin') }}</th>
                <th class="px-4 py-3">{{ t('tickets.attendees.colStatus') }}</th>
                <th class="px-4 py-3">{{ t('tickets.attendees.colPayment') }}</th>
                <th class="px-4 py-3"></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <template v-for="ticket in tickets" :key="ticket.id">
                <tr class="cursor-pointer hover:bg-slate-50" @click="toggle(ticket.id!)">
                  <td class="whitespace-nowrap px-4 py-3 text-slate-400">
                    {{ expanded === ticket.id ? '▾' : '▸' }}
                    <span class="tabular-nums">{{ ticket.id }}</span>
                  </td>
                  <td class="px-4 py-3 font-medium text-slate-900">{{ surnameFirst(ticket.holderName) }}</td>
                  <td class="px-4 py-3 text-slate-600">{{ ticket.admissionsTotal }}</td>
                  <td class="px-4 py-3">
                    <span class="rounded-full px-2 py-0.5 text-xs font-medium"
                      :class="ticket.checkedInCount >= ticket.admissionsTotal && ticket.admissionsTotal > 0
                        ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600'">
                      {{ ticket.checkedInCount }} / {{ ticket.admissionsTotal }}
                    </span>
                  </td>
                  <td class="px-4 py-3">
                    <span class="rounded-full px-2 py-0.5 text-xs font-medium"
                      :class="ticket.status === 'cancelled' ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-700'">
                      {{ ticket.statusLabel }}
                    </span>
                  </td>
                  <td class="px-4 py-3 text-slate-600">{{ ticket.paymentStatusLabel }}</td>
                  <td class="px-4 py-3 text-right whitespace-nowrap" @click.stop>
                    <div v-if="ticket.permissions?.update" class="flex justify-end">
                      <RowActions>
                        <template v-if="ticket.status !== 'cancelled'">
                          <button type="button" class="row-menu-item" @click="onResend(ticket)">{{ t('tickets.attendees.resend') }}</button>
                          <button v-if="ticket.status === 'reserved'" type="button"
                            class="row-menu-item" @click="onConfirm(ticket)">{{ t('tickets.attendees.confirmOrder') }}</button>
                          <button v-if="ticket.paymentStatus === 'pending' || ticket.paymentStatus === 'failed'" type="button"
                            class="row-menu-item" @click="onMarkPaid(ticket)">{{ t('tickets.attendees.markPaid') }}</button>
                          <button type="button" class="row-menu-item row-menu-item-danger"
                            @click="onCancelOrder(ticket)">{{ t('tickets.attendees.cancelOrder') }}</button>
                        </template>
                        <template v-else>
                          <button type="button" class="row-menu-item" @click="onRestoreOrder(ticket)">{{ t('tickets.attendees.restoreOrder') }}</button>
                          <button type="button" class="row-menu-item row-menu-item-danger"
                            @click="onDeleteOrder(ticket)">{{ t('tickets.attendees.deleteOrder') }}</button>
                        </template>
                      </RowActions>
                    </div>
                  </td>
                </tr>

                <!-- Rozbalené vstupenky objednávky -->
                <tr v-if="expanded === ticket.id" :key="`${ticket.id}-adm`">
                  <td colspan="7" class="bg-slate-50 px-4 py-3">
                    <div class="space-y-2">
                      <div v-for="(adm, i) in ticket.admissions" :key="adm.uuid"
                        class="flex flex-wrap items-center gap-3 rounded-lg border border-slate-200 bg-white px-3 py-2">
                        <span class="text-sm font-medium text-slate-800">
                          {{ adm.attendeeName ? surnameFirst(adm.attendeeName) : t('tickets.attendees.seat', { n: i + 1 }) }}
                        </span>
                        <span v-if="adm.ticketType" class="text-xs"
                          :class="adm.ticketType.kind === 'workshop' ? 'rounded-full bg-violet-100 px-2 py-0.5 font-medium text-violet-700' : 'text-slate-500'">
                          {{ adm.ticketType.name }}
                        </span>
                        <span v-if="adm.status === 'cancelled'" class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700">{{ t('tickets.attendees.cancelled') }}</span>
                        <span v-else-if="adm.status === 'waitlisted'" class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">{{ t('tickets.attendees.waitlisted') }}</span>
                        <span v-else-if="adm.isCheckedIn" class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700">
                          {{ t('tickets.attendees.checkedInAt', { time: formatDateTime(adm.checkedInAt) }) }}
                        </span>
                        <span v-else class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500">{{ t('tickets.attendees.awaiting') }}</span>

                        <div class="ml-auto flex gap-1">
                          <button v-if="ticket.permissions?.checkin && adm.status === 'valid' && !adm.isCheckedIn" type="button"
                            class="action-btn" @click="onCheckin(adm.id!)">{{ t('tickets.attendees.checkin') }}</button>
                          <button v-if="ticket.permissions?.checkin && adm.isCheckedIn" type="button"
                            class="action-btn" @click="onUndo(adm.id!)">{{ t('tickets.attendees.undo') }}</button>
                          <button v-if="ticket.permissions?.update && adm.status === 'valid'" type="button"
                            class="action-btn text-red-600" @click="onCancelAdmission(adm.id!)">{{ t('tickets.attendees.cancelSeat') }}</button>
                          <template v-if="ticket.permissions?.update && adm.status === 'cancelled'">
                            <button type="button" class="action-btn" @click="onRestoreAdmission(adm.id!)">{{ t('tickets.attendees.restoreSeat') }}</button>
                            <button type="button" class="action-btn text-red-600" @click="onDeleteAdmission(adm.id!)">{{ t('tickets.attendees.deleteSeat') }}</button>
                          </template>
                        </div>
                      </div>
                    </div>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>

        <div v-if="meta && meta.last_page > 1" class="mt-4 flex items-center gap-2">
          <button type="button" class="action-btn" :disabled="page <= 1" @click="changePage(page - 1)">{{ t('tickets.attendees.prev') }}</button>
          <span class="text-sm text-slate-500">{{ page }} / {{ meta.last_page }}</span>
          <button type="button" class="action-btn" :disabled="page >= meta.last_page" @click="changePage(page + 1)">{{ t('tickets.attendees.next') }}</button>
        </div>
      </div>

      <!-- Bočný panel: prehľad pri vchode a doplnkové nastavenia podujatia —
           zamykanie workshopov aj pripomienka sa týkajú účastníkov, preto stoja
           tu. Pri dlhom zozname typov lístkov sa panel roluje sám, nech je
           tlačidlo Uložiť vždy dosiahnuteľné. -->
      <aside class="grid gap-3 self-start lg:sticky lg:top-4 lg:max-h-[calc(100vh-2rem)] lg:overflow-y-auto">
        <AttendeeStatsPanel v-if="summary" :summary="summary" />
        <RouterLink :to="{ name: 'dashboard-events-checkin', params: { id: eventId } }" class="btn btn-secondary">
          {{ t('tickets.stats.openScanner') }}
        </RouterLink>
        <EventExtraSettingsPanel :event-id="eventId" />
      </aside>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, reactive, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import {
  indexEventTickets,
  attendeeStats,
  cancelTicket,
  cancelAdmission,
  restoreTicket,
  restoreAdmission,
  deleteTicket,
  deleteAdmission,
  confirmTicket,
  markTicketPaid,
  checkinAdmissionManual,
  undoCheckin,
  resendTicket,
  exportAttendees,
  attendeeRecipientCount,
  emailAttendees,
} from '@/api/tickets'
import { indexTicketTypes } from '@/api/ticketTypes'
import { showEvent } from '@/api/events'
import { useToast } from '@/composables/useToast'
import { provideFormValidation } from '@/composables/useFormValidation'
import AttendeeStatsPanel from '@/components/stats/AttendeeStatsPanel.vue'
import EventExtraSettingsPanel from '@/components/EventExtraSettingsPanel.vue'
import EventTicketsTabs from '@/components/EventTicketsTabs.vue'
import FormField from '@/components/FormField.vue'
import ResourceFilterBar, { type FilterOption } from '@/components/ResourceFilterBar.vue'
import RowActions from '@/components/RowActions.vue'
import { surnameFirst } from '@/utils/userDisplay'
import type { AttendeeSummary, PaginatedResponse, TicketItem, TicketTypeItem } from '@/types'
import { currentLocale, useI18n } from '@/i18n'

const { t, plural } = useI18n()

const route = useRoute()
const toast = useToast()
const eventId = Number(route.params.id)

const tickets = ref<TicketItem[]>([])
const ticketTypes = ref<TicketTypeItem[]>([])
const summary = ref<AttendeeSummary | null>(null)
const eventName = ref('')
const meta = ref<PaginatedResponse<TicketItem>['meta'] | null>(null)
const loading = ref(false)
const error = ref<string | null>(null)
const page = ref(1)
const expanded = ref<number | null>(null)

const filters = reactive({
  search: '',
  status: '',
  payment: '',
  ticketTypeId: '',
  checkin: '',
  sort: 'newest',
})

const statusOptions = computed<FilterOption[]>(() => [
  { value: 'confirmed', label: t('tickets.statuses.confirmed') },
  { value: 'reserved', label: t('tickets.statuses.reserved') },
  { value: 'cancelled', label: t('tickets.statuses.cancelled') },
])

const paymentOptions = computed<FilterOption[]>(() => [
  { value: 'none', label: t('tickets.payments.none') },
  { value: 'pending', label: t('tickets.payments.pending') },
  { value: 'paid', label: t('tickets.payments.paid') },
  { value: 'failed', label: t('tickets.payments.failed') },
  { value: 'refunded', label: t('tickets.payments.refunded') },
])

/**
 * Radenie nie je vo filtri, ale klikom v hlavičke tabuľky: ID = najnovšie /
 * najstaršie, meno = podľa priezviska (podľa neho sa v zozname hľadá).
 */
function toggleSort(column: 'id' | 'surname') {
  if (column === 'id') {
    filters.sort = filters.sort === 'newest' ? 'oldest' : 'newest'
  } else {
    filters.sort = filters.sort === 'surname' ? 'surname_desc' : 'surname'
  }

  load(1)
}

/** Šípka v hlavičke; prázdny reťazec = podľa tohto stĺpca sa práve neradí. */
function sortArrow(column: 'id' | 'surname'): string {
  if (column === 'id') {
    return filters.sort === 'newest' ? '▼' : filters.sort === 'oldest' ? '▲' : ''
  }

  return filters.sort === 'surname' ? '▲' : filters.sort === 'surname_desc' ? '▼' : ''
}

/** Filtre zo slotu si lišta sama nespočíta — musí o nich vedieť od stránky. */
const extraActive = computed(() =>
  [filters.payment, filters.ticketTypeId, filters.checkin].filter(Boolean).length)

function resetExtraFilters() {
  filters.payment = ''
  filters.ticketTypeId = ''
  filters.checkin = ''
  filters.sort = 'newest'
}

function formatDateTime(d: string | null) {
  if (!d) return ''
  return new Date(d).toLocaleString(currentLocale(), { day: 'numeric', month: 'numeric', hour: '2-digit', minute: '2-digit' })
}

function toggle(id: number) {
  expanded.value = expanded.value === id ? null : id
}

async function load(targetPage = 1) {
  loading.value = true
  error.value = null
  try {
    const result = await indexEventTickets(eventId, {
      search: filters.search || undefined,
      status: filters.status || undefined,
      payment: filters.payment || undefined,
      ticket_type_id: filters.ticketTypeId ? Number(filters.ticketTypeId) : undefined,
      checkin: filters.checkin || undefined,
      sort: filters.sort !== 'newest' ? filters.sort : undefined,
      page: targetPage,
    })
    tickets.value = result.data
    meta.value = result.meta
    page.value = targetPage
  } catch {
    error.value = t('tickets.attendees.loadFailed')
  } finally {
    loading.value = false
  }
}

/** Zoznam aj prehľad vpravo hovoria o tom istom — obnovujú sa spolu. */
async function reload() {
  await Promise.all([load(page.value), loadSummary()])
}

async function loadSummary() {
  summary.value = await attendeeStats(eventId).catch(() => summary.value)
}

/** Hláška z API (422) je zrozumiteľnejšia než všeobecné „nepodarilo sa". */
function apiMessage(e: unknown, fallback: string): string {
  return (e as { response?: { data?: { message?: string } } })?.response?.data?.message ?? fallback
}

function changePage(target: number) {
  load(target)
}

async function onCancelOrder(ticket: TicketItem) {
  if (!ticket.id || !confirm(t('tickets.attendees.cancelOrderConfirm', { name: ticket.holderName }))) return
  await run(() => cancelTicket(ticket.id!), t('tickets.attendees.cancelDone'))
}

async function onCancelAdmission(admissionId: number) {
  if (!confirm(t('tickets.attendees.cancelSeatConfirm'))) return
  await run(() => cancelAdmission(admissionId), t('tickets.attendees.cancelDone'))
}

/** Obnovenie objednávky — účastník o tom dostane e-mail, preto sa pýtame. */
async function onRestoreOrder(ticket: TicketItem) {
  if (!ticket.id || !confirm(t('tickets.attendees.restoreOrderConfirm', { name: ticket.holderName }))) return
  await run(() => restoreTicket(ticket.id!), t('tickets.attendees.restored'))
}

async function onRestoreAdmission(admissionId: number) {
  if (!confirm(t('tickets.attendees.restoreSeatConfirm'))) return
  await run(() => restoreAdmission(admissionId), t('tickets.attendees.restored'))
}

async function onDeleteOrder(ticket: TicketItem) {
  if (!ticket.id || !confirm(t('tickets.attendees.deleteOrderConfirm', { name: ticket.holderName }))) return
  await run(() => deleteTicket(ticket.id!), t('tickets.attendees.deleted'))
}

async function onDeleteAdmission(admissionId: number) {
  if (!confirm(t('tickets.attendees.deleteSeatConfirm'))) return
  await run(() => deleteAdmission(admissionId), t('tickets.attendees.deleted'))
}

async function onConfirm(ticket: TicketItem) {
  if (!ticket.id) return
  await run(() => confirmTicket(ticket.id!), t('tickets.attendees.confirmed'))
}

async function onMarkPaid(ticket: TicketItem) {
  if (!ticket.id) return
  await run(() => markTicketPaid(ticket.id!), t('tickets.attendees.markedPaid'))
}

/** Spoločný priebeh akcie v riadku: zavolať, oznámiť, načítať zoznam aj prehľad. */
async function run(action: () => Promise<unknown>, success: string) {
  try {
    await action()
    toast.success(success)
  } catch (e) {
    toast.error(apiMessage(e, t('tickets.attendees.actionFailed')))
  }
  await reload()
}

async function onCheckin(admissionId: number) {
  const res = await checkinAdmissionManual(admissionId)
  if (res.status === 'checked_in') toast.success(t('tickets.attendees.checkedIn'))
  else if (res.status === 'already_checked_in') toast.error(t('tickets.attendees.alreadyCheckedIn'))
  else toast.error(t('tickets.attendees.checkinFailed'))
  await reload()
}

async function onUndo(admissionId: number) {
  await undoCheckin(admissionId)
  toast.success(t('tickets.attendees.undone'))
  await reload()
}

const exporting = ref(false)

async function onExport() {
  exporting.value = true
  try {
    await exportAttendees(eventId)
  } catch {
    toast.error(t('tickets.attendees.exportFailed'))
  } finally {
    exporting.value = false
  }
}

const validation = provideFormValidation()

const bulk = reactive({
  show: false,
  sending: false,
  error: null as string | null,
  recipients: 0,
  subject: '',
  body: '',
})

async function openBulk() {
  bulk.show = true
  bulk.error = null
  validation.reset()
  bulk.subject = eventName.value ? t('tickets.attendees.subjectPrefill', { event: eventName.value }) : ''
  bulk.body = ''
  bulk.recipients = await attendeeRecipientCount(eventId).catch(() => 0)
}

async function sendBulk() {
  validation.markValidated()
  bulk.error = null
  bulk.sending = true
  try {
    const count = await emailAttendees(eventId, { subject: bulk.subject, body: bulk.body })
    bulk.show = false
    toast.success(t('tickets.attendees.sent', {
      recipients: plural('tickets.attendees.counts.recipients', count),
    }))
  } catch (e: unknown) {
    bulk.error = apiMessage(e, t('tickets.attendees.sendFailed'))
  } finally {
    bulk.sending = false
  }
}

async function onResend(ticket: TicketItem) {
  if (!ticket.id) return
  try {
    await resendTicket(ticket.id)
    toast.success(t('tickets.attendees.resent'))
  } catch {
    toast.error(t('tickets.attendees.sendFailed'))
  }
}

onMounted(() => {
  load(1)
  loadSummary()
  indexTicketTypes(eventId).then((types) => { ticketTypes.value = types }).catch(() => {})
  showEvent('dashboard', eventId).then((e) => { eventName.value = e.name }).catch(() => {})
})
</script>
