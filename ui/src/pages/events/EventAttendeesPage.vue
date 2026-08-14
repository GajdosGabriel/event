<template>
  <div class="mx-auto my-5 w-full max-w-[1320px] px-4">
    <EventTicketsTabs :event-id="eventId" />

    <div class="mb-4">
      <h1 class="text-2xl font-semibold text-slate-900">{{ eventName || t('tickets.attendees.title') }}</h1>
      <p v-if="eventName" class="text-sm text-slate-500">{{ t('tickets.attendees.title') }}</p>
    </div>

    <div class="mb-4 flex flex-wrap items-center gap-2">
      <input v-model="search" type="search" :placeholder="t('filters.attendees.search')"
        class="w-full max-w-sm rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
        @input="onSearch" />
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

    <div v-else class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
      <table class="w-full text-sm">
        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
          <tr>
            <th class="px-4 py-3"></th>
            <th class="px-4 py-3">{{ t('tickets.attendees.colName') }}</th>
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
              <td class="px-4 py-3 text-slate-400">{{ expanded === ticket.id ? '▾' : '▸' }}</td>
              <td class="px-4 py-3 font-medium text-slate-900">{{ ticket.holderName }}</td>
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
                    <button type="button" class="row-menu-item" @click="onResend(ticket)">{{ t('tickets.attendees.resend') }}</button>
                    <button v-if="ticket.status !== 'cancelled'" type="button"
                      class="row-menu-item row-menu-item-danger" @click="onCancelOrder(ticket)">{{ t('tickets.attendees.cancelOrder') }}</button>
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
                      {{ adm.attendeeName || t('tickets.attendees.seat', { n: i + 1 }) }}
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
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import {
  indexEventTickets,
  cancelTicket,
  cancelAdmission,
  checkinAdmissionManual,
  undoCheckin,
  resendTicket,
  exportAttendees,
  attendeeRecipientCount,
  emailAttendees,
} from '@/api/tickets'
import { showEvent } from '@/api/events'
import { useToast } from '@/composables/useToast'
import { provideFormValidation } from '@/composables/useFormValidation'
import EventTicketsTabs from '@/components/EventTicketsTabs.vue'
import FormField from '@/components/FormField.vue'
import RowActions from '@/components/RowActions.vue'
import type { PaginatedResponse, TicketItem } from '@/types'
import { currentLocale, useI18n } from '@/i18n'

const { t, plural } = useI18n()

const route = useRoute()
const toast = useToast()
const eventId = Number(route.params.id)

const tickets = ref<TicketItem[]>([])
const eventName = ref('')
const meta = ref<PaginatedResponse<TicketItem>['meta'] | null>(null)
const loading = ref(false)
const error = ref<string | null>(null)
const search = ref('')
const page = ref(1)
const expanded = ref<number | null>(null)

let searchTimeout: ReturnType<typeof setTimeout> | undefined

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
    const result = await indexEventTickets(eventId, { search: search.value || undefined, page: targetPage })
    tickets.value = result.data
    meta.value = result.meta
    page.value = targetPage
  } catch {
    error.value = t('tickets.attendees.loadFailed')
  } finally {
    loading.value = false
  }
}

function onSearch() {
  if (searchTimeout) clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => load(1), 300)
}

function changePage(target: number) {
  load(target)
}

async function onCancelOrder(ticket: TicketItem) {
  if (!ticket.id || !confirm(t('tickets.attendees.cancelOrderConfirm', { name: ticket.holderName }))) return
  await cancelTicket(ticket.id)
  await load(page.value)
}

async function onCancelAdmission(admissionId: number) {
  if (!confirm(t('tickets.attendees.cancelSeatConfirm'))) return
  await cancelAdmission(admissionId)
  await load(page.value)
}

async function onCheckin(admissionId: number) {
  const res = await checkinAdmissionManual(admissionId)
  if (res.status === 'checked_in') toast.success(t('tickets.attendees.checkedIn'))
  else if (res.status === 'already_checked_in') toast.error(t('tickets.attendees.alreadyCheckedIn'))
  else toast.error(t('tickets.attendees.checkinFailed'))
  await load(page.value)
}

async function onUndo(admissionId: number) {
  await undoCheckin(admissionId)
  toast.success(t('tickets.attendees.undone'))
  await load(page.value)
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
    const resp = (e as { response?: { data?: { message?: string } } })?.response?.data
    bulk.error = resp?.message ?? t('tickets.attendees.sendFailed')
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
  showEvent('dashboard', eventId).then((e) => { eventName.value = e.name }).catch(() => {})
})
</script>
