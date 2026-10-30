<template>
  <div v-if="!forbidden" class="min-w-0">
    <!-- Akcie nad celým zoznamom: stoja na jednom mieste na stránke Lístky aj Prihlásení. -->
    <div class="mb-3 flex flex-wrap gap-2">
      <button v-if="canManage" type="button" class="btn btn-primary" @click="openAdd()">{{ t('tickets.workspace.add') }}</button>
      <button type="button" class="btn btn-secondary" @click="openMessage()">{{ t('tickets.workspace.write') }}</button>
      <button type="button" class="btn btn-secondary" :disabled="exporting" @click="onExport">
        {{ exporting ? t('tickets.attendees.exporting') : t('tickets.attendees.export') }}
      </button>
      <button type="button" class="btn btn-secondary" @click="openHistory">{{ t('tickets.workspace.history') }}</button>
    </div>

    <div class="mb-4 flex flex-wrap items-start gap-2">
      <ResourceFilterBar
        v-model:search="filters.search"
        v-model:status="filters.status"
        :status-options="statusOptions"
        :sort-options="[]"
        :extra-active="extraActive"
        history-key="event-attendees"
        class="flex-1"
        @change="load(1)"
        @reset="resetExtraFilters"
      >
        <template #filters>
          <!-- Pri jedinom type lístka by filter nič nefiltroval. -->
          <select v-if="ticketTypes.length > 1" v-model="filters.ticketTypeId" class="form-input w-auto"
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
    </div>

    <!-- Chceli sa prihlásiť, ale vstupenku nedostali — organizátor sa im má ozvať. -->
    <section v-if="interests.length" class="mb-4 rounded-2xl border border-amber-200 bg-amber-50 p-4">
      <h3 class="text-sm font-semibold text-amber-950">{{ t('tickets.attendees.interestsTitle', { n: interests.length }) }}</h3>
      <p class="mb-3 text-xs text-amber-900">{{ t('tickets.attendees.interestsLead') }}</p>
      <ul class="divide-y divide-amber-200">
        <li v-for="interest in interests" :key="interest.id" class="flex flex-wrap items-center gap-x-3 gap-y-1 py-2 text-sm">
          <span class="font-medium text-slate-900">{{ surnameFirst(interest.name) || interest.email }}</span>
          <a :href="`mailto:${interest.email}`" class="text-blue-700 hover:underline">{{ interest.email }}</a>
          <span class="text-xs text-slate-500">{{ formatDateTime(interest.createdAt) }}</span>
          <div class="ml-auto flex gap-1">
            <button v-if="canManage" type="button" class="action-btn" @click="openAdd(interest)">{{ t('tickets.workspace.interestAdd') }}</button>
            <button type="button" class="action-btn" @click="onDeleteInterest(interest)">{{ t('tickets.attendees.interestDone') }}</button>
          </div>
        </li>
      </ul>
    </section>

    <!-- Hromadné akcie sa ukážu až pri výbere, inak by zavadzali. -->
    <div v-if="selected.length" class="mb-3 flex flex-wrap items-center gap-2 rounded-xl bg-blue-50 px-3 py-2 text-sm">
      <span class="font-medium text-blue-900">{{ t('tickets.workspace.selected', { n: selected.length }) }}</span>
      <button type="button" class="action-btn" @click="openMessage({ ticketIds: selected })">{{ t('tickets.workspace.write') }}</button>
      <template v-if="canManage">
        <button type="button" class="action-btn" @click="onBulk('confirm')">{{ t('tickets.attendees.confirmOrder') }}</button>
        <button type="button" class="action-btn" @click="onBulk('mark_paid')">{{ t('tickets.attendees.markPaid') }}</button>
        <button type="button" class="action-btn text-red-600" @click="openCancel(selected)">{{ t('tickets.attendees.cancelOrder') }}</button>
      </template>
      <button type="button" class="action-btn ml-auto" @click="selected = []">{{ t('tickets.workspace.clearSelection') }}</button>
    </div>

    <p v-if="loading" class="text-slate-500">{{ t('tickets.attendees.loading') }}</p>
    <p v-else-if="error" class="text-red-600">{{ error }}</p>
    <p v-else-if="!tickets.length" class="text-slate-400">{{ t('tickets.attendees.empty') }}</p>

    <div v-else class="overflow-x-auto rounded-2xl border border-slate-200 bg-white">
      <table class="w-full text-sm">
        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
          <tr>
            <th class="w-8 px-3 py-3">
              <input type="checkbox" :checked="allOnPageSelected" :aria-label="t('tickets.workspace.selectAll')" @change="toggleAll">
            </th>
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
            <th class="px-4 py-3">{{ t('tickets.attendees.colRegistered') }}</th>
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
              <td class="px-3 py-3" @click.stop>
                <input v-model="selected" type="checkbox" :value="ticket.id" :aria-label="surnameFirst(ticket.holderName)">
              </td>
              <td class="whitespace-nowrap px-4 py-3 text-slate-400">
                {{ expanded === ticket.id ? '▾' : '▸' }}
                <span class="tabular-nums">{{ ticket.id }}</span>
              </td>
              <td class="px-4 py-3">
                <span class="cell-name font-medium text-slate-900" :title="surnameFirst(ticket.holderName)">{{ surnameFirst(ticket.holderName) || ticket.holderEmail }}</span>
                <!-- Kontakt priamo v riadku — organizátor sa môže ozvať bez rozbaľovania. -->
                <span class="block text-xs font-normal text-slate-500" @click.stop>
                  <a v-if="ticket.holderEmail" :href="`mailto:${ticket.holderEmail}`" class="text-blue-700 hover:underline">{{ ticket.holderEmail }}</a>
                  <template v-if="ticket.holderPhone">
                    · <a :href="`tel:${ticket.holderPhone}`" class="text-blue-700 hover:underline">{{ ticket.holderPhone }}</a>
                  </template>
                </span>
              </td>
              <td class="whitespace-nowrap px-4 py-3 text-slate-600">{{ formatDateTime(ticket.createdAt) }}</td>
              <td class="px-4 py-3 text-slate-600">{{ ticket.admissionsTotal }}</td>
              <td class="px-4 py-3">
                <span class="rounded-full px-2 py-0.5 text-xs font-medium"
                  :class="ticket.checkedInCount >= ticket.admissionsTotal && ticket.admissionsTotal > 0
                    ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600'">
                  {{ ticket.checkedInCount }} / {{ ticket.admissionsTotal }}
                </span>
              </td>
              <td class="px-4 py-3">
                <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="statusClass(ticket)">
                  {{ ticket.statusLabel }}
                </span>
              </td>
              <td class="px-4 py-3">
                <span class="whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-medium" :class="paymentClass(ticket)">
                  {{ ticket.paymentStatusLabel }}<template v-if="ticket.priceAmount"> · {{ formatPrice(ticket.priceAmount, ticket.priceCurrency) }}</template>
                </span>
              </td>
              <td class="px-4 py-3 text-right whitespace-nowrap" @click.stop>
                <div class="flex justify-end">
                  <RowActions>
                    <button type="button" class="row-menu-item" @click="openMessage({ ticketIds: [ticket.id!], name: ticket.holderName })">{{ t('tickets.workspace.write') }}</button>
                    <template v-if="ticket.permissions?.update">
                      <button type="button" class="row-menu-item" @click="openContact(ticket)">{{ t('tickets.workspace.editContact') }}</button>
                      <template v-if="ticket.status !== 'cancelled'">
                        <button type="button" class="row-menu-item" @click="onResend(ticket)">{{ t('tickets.attendees.resend') }}</button>
                        <button v-if="ticket.status === 'reserved'" type="button"
                          class="row-menu-item" @click="onConfirm(ticket)">{{ t('tickets.attendees.confirmOrder') }}</button>
                        <button v-if="ticket.paymentStatus === 'pending' || ticket.paymentStatus === 'failed'" type="button"
                          class="row-menu-item" @click="onMarkPaid(ticket)">{{ t('tickets.attendees.markPaid') }}</button>
                      </template>
                      <button v-if="ticket.paymentStatus === 'paid'" type="button"
                        class="row-menu-item" @click="onMarkRefunded(ticket)">{{ t('tickets.workspace.markRefunded') }}</button>
                      <button v-if="ticket.status !== 'cancelled'" type="button" class="row-menu-item row-menu-item-danger"
                        @click="openCancel([ticket.id!], ticket.holderName)">{{ t('tickets.attendees.cancelOrder') }}</button>
                      <template v-else>
                        <button type="button" class="row-menu-item" @click="onRestoreOrder(ticket)">{{ t('tickets.attendees.restoreOrder') }}</button>
                        <button type="button" class="row-menu-item row-menu-item-danger"
                          @click="onDeleteOrder(ticket)">{{ t('tickets.attendees.deleteOrder') }}</button>
                      </template>
                    </template>
                  </RowActions>
                </div>
              </td>
            </tr>

            <!-- Rozbalené vstupenky objednávky -->
            <tr v-if="expanded === ticket.id" :key="`${ticket.id}-adm`">
              <td colspan="9" class="bg-slate-50 px-4 py-3">
                <div class="space-y-2">
                  <div v-for="(adm, i) in ticket.admissions" :key="adm.uuid"
                    class="flex flex-wrap items-center gap-3 rounded-lg border border-slate-200 bg-white px-3 py-2">
                    <span class="text-sm font-medium text-slate-800">
                      {{ seatName(ticket, adm) ? surnameFirst(seatName(ticket, adm)) : t('tickets.attendees.seat', { n: i + 1 }) }}
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

                  <!-- Odpovede dotazníka (strava, ubytovanie…) — organizátor ich môže opraviť. -->
                  <TicketAnswersPanel
                    v-if="questions.length || ticket.answers?.length"
                    class="rounded-lg border border-slate-200 bg-white px-3 py-2"
                    :ticket="ticket"
                    :questions="questions"
                    organizer
                    :save="payload => updateDashboardTicketAnswers(ticket.id!, payload)"
                    @saved="onAnswersSaved"
                  />
                </div>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>

    <AppPaginator v-if="meta" :current-page="page" :last-page="meta.last_page" @change="load" />

    <!-- Správa: všetkým, časti, vybraným alebo jednému človeku. -->
    <div v-if="message.show" class="fixed inset-0 z-50 grid place-items-center overflow-y-auto bg-slate-900/40 p-4" @click.self="message.show = false">
      <form class="w-full max-w-lg rounded-2xl bg-white p-5 shadow-xl" @submit.prevent="sendMessage">
        <h2 class="mb-4 text-lg font-semibold text-slate-900">{{ t('tickets.attendees.bulkTitle') }}</h2>

        <label class="mb-3 block text-sm font-medium text-slate-700">
          {{ t('tickets.workspace.to') }}
          <p v-if="message.ticketIds.length" class="mt-1 font-normal text-slate-600">
            {{ message.name || t('tickets.workspace.selected', { n: message.ticketIds.length }) }}
          </p>
          <select v-else v-model="message.scope" class="form-input mt-1 w-full" @change="refreshRecipients">
            <option value="all">{{ t('tickets.workspace.toAll') }}</option>
            <option value="unpaid">{{ t('tickets.workspace.toUnpaid') }}</option>
            <template v-if="ticketTypes.length > 1">
              <option v-for="type in ticketTypes" :key="type.id" :value="`type:${type.id}`">{{ t('tickets.workspace.toType', { name: type.name }) }}</option>
            </template>
          </select>
        </label>

        <!-- Hotové znenia pre situácie, kde sa píše pod tlakom: presun, zmena miesta, zrušenie. -->
        <label class="mb-3 block text-sm font-medium text-slate-700">
          {{ t('tickets.workspace.template') }}
          <select v-model="message.template" class="form-input mt-1 w-full" @change="applyTemplate">
            <option value="">{{ t('tickets.workspace.templateNone') }}</option>
            <option value="moved">{{ t('tickets.workspace.templates.moved.label') }}</option>
            <option value="venue">{{ t('tickets.workspace.templates.venue.label') }}</option>
            <option value="cancelled">{{ t('tickets.workspace.templates.cancelled.label') }}</option>
          </select>
        </label>

        <FormField v-model="message.subject" :label="t('tickets.attendees.subject')" required maxlength="150" />
        <FormField v-model="message.body" type="textarea" :label="t('tickets.attendees.body')" required rows="7" maxlength="5000" class="mt-3" />

        <p class="mt-3 text-sm text-slate-500">
          {{ t('tickets.attendees.bulkLead', { recipients: plural('tickets.attendees.counts.recipients', message.recipients) }) }}
        </p>
        <p v-if="message.error" class="mt-2 text-sm text-red-600">{{ message.error }}</p>

        <div class="mt-4 flex justify-end gap-2">
          <button type="button" class="btn btn-secondary" @click="message.show = false">{{ t('tickets.attendees.cancel') }}</button>
          <button type="submit" class="btn btn-primary" :disabled="message.sending || !message.recipients">
            {{ message.sending ? t('tickets.attendees.sending') : t('tickets.attendees.send') }}
          </button>
        </div>
      </form>
    </div>

    <!-- Zrušenie: e-mail je predvolene zapnutý, človek inak príde s neplatným lístkom. -->
    <div v-if="cancelDialog.show" class="fixed inset-0 z-50 grid place-items-center bg-slate-900/40 p-4" @click.self="cancelDialog.show = false">
      <form class="w-full max-w-lg rounded-2xl bg-white p-5 shadow-xl" @submit.prevent="confirmCancel">
        <h2 class="mb-1 text-lg font-semibold text-slate-900">{{ t('tickets.workspace.cancelTitle') }}</h2>
        <p class="mb-4 text-sm text-slate-500">{{ cancelDialog.name || t('tickets.workspace.selected', { n: cancelDialog.ids.length }) }}</p>

        <label class="mb-3 flex items-center gap-2 text-sm text-slate-700">
          <input v-model="cancelDialog.notify" type="checkbox">
          {{ t('tickets.workspace.cancelNotify') }}
        </label>
        <FormField v-if="cancelDialog.notify" v-model="cancelDialog.message" type="textarea"
          :label="t('tickets.workspace.cancelReason')" rows="4" maxlength="2000" />
        <p class="mt-3 text-xs text-slate-500">{{ t('tickets.workspace.cancelHint') }}</p>

        <div class="mt-4 flex justify-end gap-2">
          <button type="button" class="btn btn-secondary" @click="cancelDialog.show = false">{{ t('tickets.attendees.cancel') }}</button>
          <button type="submit" class="btn btn-primary" :disabled="cancelDialog.sending">{{ t('tickets.workspace.cancelConfirm') }}</button>
        </div>
      </form>
    </div>

    <!-- Oprava kontaktu -->
    <div v-if="contact.show" class="fixed inset-0 z-50 grid place-items-center bg-slate-900/40 p-4" @click.self="contact.show = false">
      <form class="w-full max-w-lg rounded-2xl bg-white p-5 shadow-xl" @submit.prevent="saveContact">
        <h2 class="mb-4 text-lg font-semibold text-slate-900">{{ t('tickets.workspace.editContact') }}</h2>
        <FormField v-model="contact.name" :label="t('tickets.request.holderName')" required trim maxlength="250" />
        <FormField v-model="contact.email" type="email" :label="t('tickets.request.holderEmail')" required trim maxlength="190" class="mt-3" />
        <FormField v-model="contact.phone" type="tel" :label="t('tickets.request.holderPhone')" trim maxlength="30" class="mt-3" />
        <p v-if="contact.error" class="mt-2 text-sm text-red-600">{{ contact.error }}</p>
        <div class="mt-4 flex justify-end gap-2">
          <button type="button" class="btn btn-secondary" @click="contact.show = false">{{ t('tickets.attendees.cancel') }}</button>
          <button type="submit" class="btn btn-primary" :disabled="contact.saving">{{ t('tickets.workspace.save') }}</button>
        </div>
      </form>
    </div>

    <!-- Ručné pridanie účastníka -->
    <div v-if="add.show" class="fixed inset-0 z-50 grid place-items-center overflow-y-auto bg-slate-900/40 p-4" @click.self="add.show = false">
      <form class="w-full max-w-lg rounded-2xl bg-white p-5 shadow-xl" @submit.prevent="saveAdd">
        <h2 class="mb-4 text-lg font-semibold text-slate-900">{{ t('tickets.workspace.add') }}</h2>
        <FormField v-model="add.name" :label="t('tickets.request.holderName')" required trim maxlength="250" />
        <FormField v-model="add.email" type="email" :label="t('tickets.request.holderEmail')" required trim maxlength="190" class="mt-3" />
        <FormField v-model="add.phone" type="tel" :label="t('tickets.request.holderPhone')" trim maxlength="30" class="mt-3" />
        <div class="mt-3 grid grid-cols-[1fr_6rem] gap-2">
          <label class="block text-sm font-medium text-slate-700">
            {{ t('tickets.workspace.addType') }}
            <select v-model="add.typeId" class="form-input mt-1 w-full" required>
              <option v-for="type in ticketTypes" :key="type.id" :value="type.id">{{ type.name }}</option>
            </select>
          </label>
          <label class="block text-sm font-medium text-slate-700">
            {{ t('tickets.workspace.addQuantity') }}
            <input v-model.number="add.quantity" type="number" min="1" max="50" class="form-input mt-1 w-full" required>
          </label>
        </div>
        <label class="mt-3 flex items-center gap-2 text-sm text-slate-700">
          <input v-model="add.sendEmail" type="checkbox">
          {{ t('tickets.workspace.addSendEmail') }}
        </label>
        <p v-if="add.error" class="mt-2 text-sm text-red-600">{{ add.error }}</p>
        <div class="mt-4 flex justify-end gap-2">
          <button type="button" class="btn btn-secondary" @click="add.show = false">{{ t('tickets.attendees.cancel') }}</button>
          <button type="submit" class="btn btn-primary" :disabled="add.saving || !add.typeId">{{ t('tickets.workspace.save') }}</button>
        </div>
      </form>
    </div>

    <!-- História správ -->
    <div v-if="history.show" class="fixed inset-0 z-50 grid place-items-center overflow-y-auto bg-slate-900/40 p-4" @click.self="history.show = false">
      <div class="w-full max-w-2xl rounded-2xl bg-white p-5 shadow-xl">
        <div class="mb-3 flex items-center justify-between gap-2">
          <h2 class="text-lg font-semibold text-slate-900">{{ t('tickets.workspace.history') }}</h2>
          <button type="button" class="btn btn-secondary" @click="history.show = false">{{ t('tickets.workspace.close') }}</button>
        </div>
        <p v-if="!history.items.length" class="text-sm text-slate-400">{{ t('tickets.workspace.historyEmpty') }}</p>
        <ul class="divide-y divide-slate-100">
          <li v-for="item in history.items" :key="item.id" class="py-3 text-sm">
            <p class="font-medium text-slate-900">{{ item.subject }}</p>
            <p class="text-xs text-slate-500">
              {{ formatDateTime(item.createdAt) }}
              <template v-if="item.sender"> · {{ item.sender }}</template>
              · <span :title="item.recipients.join(', ')">{{ plural('tickets.attendees.counts.recipients', item.recipients.length) }}</span>
            </p>
            <p v-if="item.body" class="mt-1 whitespace-pre-line text-slate-600">{{ item.body }}</p>
          </li>
        </ul>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import AppPaginator from '@/components/AppPaginator.vue'
import FormField from '@/components/FormField.vue'
import ResourceFilterBar, { type FilterOption } from '@/components/ResourceFilterBar.vue'
import RowActions from '@/components/RowActions.vue'
import TicketAnswersPanel from '@/components/TicketAnswersPanel.vue'
import {
  addEventAttendee,
  attendeeMessages,
  attendeeRecipientCount,
  bulkTicketAction,
  cancelAdmission,
  cancelTicket,
  checkinAdmissionManual,
  confirmTicket,
  deleteAdmission,
  deleteEventInterest,
  deleteTicket,
  emailAttendees,
  exportAttendees,
  indexEventInterests,
  indexEventTickets,
  markTicketPaid,
  markTicketRefunded,
  resendTicket,
  restoreAdmission,
  restoreTicket,
  undoCheckin,
  updateDashboardTicketAnswers,
  updateTicketContact,
  type AttendeeScope,
} from '@/api/tickets'
import { indexQuestions } from '@/api/questionnaire'
import { indexTicketTypes } from '@/api/ticketTypes'
import { provideFormValidation } from '@/composables/useFormValidation'
import { useToast } from '@/composables/useToast'
import { currentLocale, useI18n } from '@/i18n'
import type { EventInterestItem, EventMessageItem, PaginatedResponse, QuestionItem, TicketItem, TicketTypeItem } from '@/types'
import { formatPrice } from '@/utils/money'
import { seatName, surnameFirst } from '@/utils/userDisplay'

const props = defineProps<{ eventId: number; eventName?: string }>()
/** Zoznam sa zmenil — stránka si obnoví svoje počty a prehľad. */
const emit = defineEmits<{ changed: [] }>()

const { t, plural } = useI18n()
const route = useRoute()
const toast = useToast()
const validation = provideFormValidation()

const tickets = ref<TicketItem[]>([])
const ticketTypes = ref<TicketTypeItem[]>([])
const meta = ref<PaginatedResponse<TicketItem>['meta'] | null>(null)
const loading = ref(false)
const error = ref<string | null>(null)
/** Bez práva `ticket.view` sa pracovná časť neukáže vôbec, typy lístkov ostanú. */
const forbidden = ref(false)
const page = ref(1)
const expanded = ref<number | null>(null)
const questions = ref<QuestionItem[]>([])
const interests = ref<EventInterestItem[]>([])
const selected = ref<number[]>([])

const canManage = computed(() => tickets.value.length === 0 || tickets.value.some(ticket => ticket.permissions?.update))

const filters = reactive({
  search: '',
  status: '',
  payment: '',
  // Odkaz z počtu pri type lístka otvorí zoznam rovno vyfiltrovaný.
  ticketTypeId: typeof route.query.type === 'string' ? route.query.type : '',
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

/** Stav objednávky: potvrdená modrou, rezervácia (čaká) jantárovou, zrušená červenou. */
function statusClass(ticket: TicketItem): string {
  if (ticket.status === 'cancelled') return 'bg-red-100 text-red-700'
  if (ticket.status === 'reserved') return 'bg-amber-100 text-amber-800'
  return 'bg-blue-100 text-blue-700'
}

/** Platba: uhradená zelenou, čakajúca jantárovou, zlyhaná červenou, ostatné sivou. */
function paymentClass(ticket: TicketItem): string {
  switch (ticket.paymentStatus) {
    case 'paid': return 'bg-emerald-100 text-emerald-700'
    case 'pending': return 'bg-amber-100 text-amber-800'
    case 'failed': return 'bg-red-100 text-red-700'
    default: return 'bg-slate-100 text-slate-600'
  }
}

function toggle(id: number) {
  expanded.value = expanded.value === id ? null : id
}

const allOnPageSelected = computed(() =>
  tickets.value.length > 0 && tickets.value.every(ticket => selected.value.includes(ticket.id!)))

function toggleAll() {
  selected.value = allOnPageSelected.value ? [] : tickets.value.map(ticket => ticket.id!)
}

async function load(targetPage = 1) {
  loading.value = true
  error.value = null
  try {
    const result = await indexEventTickets(props.eventId, {
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
    // Výber platí pre to, čo organizátor vidí — nech hromadná akcia nezasiahne skrytých.
    selected.value = selected.value.filter(id => result.data.some(ticket => ticket.id === id))
  } catch (e) {
    if ((e as { response?: { status?: number } })?.response?.status === 403) forbidden.value = true
    error.value = t('tickets.attendees.loadFailed')
  } finally {
    loading.value = false
  }
}

async function reload() {
  await load(page.value)
  emit('changed')
}

/** Filter z počtu pri type lístka na stránke Lístky — bez odchodu zo stránky. */
function filterByType(typeId: number | undefined) {
  filters.ticketTypeId = typeId ? String(typeId) : ''
  load(1)
}

function refreshTypes() {
  indexTicketTypes(props.eventId).then((types) => { ticketTypes.value = types }).catch(() => {})
}

defineExpose({ filterByType, refreshTypes, reload: () => load(page.value) })

function onAnswersSaved(updated: TicketItem) {
  tickets.value = tickets.value.map(ticket => (ticket.id === updated.id ? updated : ticket))
  emit('changed')
  toast.success(t('questionnaire.saved'))
}

/** Hláška z API (422) je zrozumiteľnejšia než všeobecné „nepodarilo sa". */
function apiMessage(e: unknown, fallback: string): string {
  return (e as { response?: { data?: { message?: string } } })?.response?.data?.message ?? fallback
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

async function onDeleteInterest(interest: EventInterestItem) {
  if (!confirm(t('tickets.attendees.interestDoneConfirm', { name: interest.name || interest.email }))) return
  try {
    await deleteEventInterest(props.eventId, interest.id)
    interests.value = interests.value.filter(item => item.id !== interest.id)
  } catch (e) {
    toast.error(apiMessage(e, t('tickets.attendees.actionFailed')))
  }
}

async function onConfirm(ticket: TicketItem) {
  if (!ticket.id) return
  await run(() => confirmTicket(ticket.id!), t('tickets.attendees.confirmed'))
}

async function onMarkPaid(ticket: TicketItem) {
  if (!ticket.id) return
  await run(() => markTicketPaid(ticket.id!), t('tickets.attendees.markedPaid'))
}

async function onMarkRefunded(ticket: TicketItem) {
  if (!ticket.id || !confirm(t('tickets.workspace.markRefundedConfirm', { name: ticket.holderName }))) return
  await run(() => markTicketRefunded(ticket.id!), t('tickets.workspace.markedRefunded'))
}

async function onBulk(action: 'confirm' | 'mark_paid') {
  await runBulk({ action, ticket_ids: [...selected.value] })
}

async function runBulk(payload: Parameters<typeof bulkTicketAction>[1]) {
  try {
    const result = await bulkTicketAction(props.eventId, payload)
    toast.success(t('tickets.workspace.bulkDone', { done: result.done, skipped: result.skipped }))
    selected.value = []
  } catch (e) {
    toast.error(apiMessage(e, t('tickets.attendees.actionFailed')))
  }
  await reload()
}

async function onCheckin(admissionId: number) {
  const res = await checkinAdmissionManual(admissionId)
  // Vstup prešiel, ale objednávka nie je uhradená — organizátor vyberie doplatok.
  if (res.status === 'checked_in' && res.paymentDue) {
    toast.show(t('checkin.paymentDue', { amount: formatPrice(res.paymentDue.amount, res.paymentDue.currency) }), 'error', 8000)
  } else if (res.status === 'checked_in') toast.success(t('tickets.attendees.checkedIn'))
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
    await exportAttendees(props.eventId)
  } catch {
    toast.error(t('tickets.attendees.exportFailed'))
  } finally {
    exporting.value = false
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

const message = reactive({
  show: false,
  sending: false,
  error: null as string | null,
  recipients: 0,
  scope: 'all',
  ticketIds: [] as number[],
  name: null as string | null,
  template: '',
  subject: '',
  body: '',
})

function messageScope(): AttendeeScope {
  if (message.ticketIds.length) return { ticket_ids: message.ticketIds }
  if (message.scope === 'unpaid') return { audience: 'unpaid' }
  if (message.scope.startsWith('type:')) return { ticket_type_id: Number(message.scope.slice(5)) }
  return { audience: 'all' }
}

async function refreshRecipients() {
  message.recipients = await attendeeRecipientCount(props.eventId, messageScope()).catch(() => 0)
}

function openMessage(target: { ticketIds?: number[]; name?: string | null } = {}) {
  validation.reset()
  message.show = true
  message.error = null
  message.scope = 'all'
  message.ticketIds = [...(target.ticketIds ?? [])]
  message.name = target.name ?? null
  message.template = ''
  message.subject = props.eventName ? t('tickets.attendees.subjectPrefill', { event: props.eventName }) : ''
  message.body = ''
  refreshRecipients()
}

function applyTemplate() {
  const params = { event: props.eventName ?? '' }

  if (message.template === 'moved') {
    message.subject = t('tickets.workspace.templates.moved.subject', params)
    message.body = t('tickets.workspace.templates.moved.body', params)
  } else if (message.template === 'venue') {
    message.subject = t('tickets.workspace.templates.venue.subject', params)
    message.body = t('tickets.workspace.templates.venue.body', params)
  } else if (message.template === 'cancelled') {
    message.subject = t('tickets.workspace.templates.cancelled.subject', params)
    message.body = t('tickets.workspace.templates.cancelled.body', params)
  }
}

async function sendMessage() {
  validation.markValidated()
  message.error = null
  message.sending = true
  try {
    const count = await emailAttendees(props.eventId, { subject: message.subject, body: message.body, ...messageScope() })
    message.show = false
    toast.success(t('tickets.attendees.sent', {
      recipients: plural('tickets.attendees.counts.recipients', count),
    }))
  } catch (e: unknown) {
    message.error = apiMessage(e, t('tickets.attendees.sendFailed'))
  } finally {
    message.sending = false
  }
}

const cancelDialog = reactive({
  show: false,
  sending: false,
  ids: [] as number[],
  name: null as string | null,
  notify: true,
  message: '',
})

function openCancel(ids: number[], name: string | null = null) {
  validation.reset()
  cancelDialog.show = true
  cancelDialog.ids = [...ids]
  cancelDialog.name = name
  cancelDialog.notify = true
  cancelDialog.message = ''
}

async function confirmCancel() {
  cancelDialog.sending = true
  const options = { notify: cancelDialog.notify, message: cancelDialog.notify ? cancelDialog.message || null : null }
  if (cancelDialog.ids.length === 1) {
    await run(() => cancelTicket(cancelDialog.ids[0], options), t('tickets.attendees.cancelDone'))
    selected.value = selected.value.filter(id => id !== cancelDialog.ids[0])
  } else {
    await runBulk({ action: 'cancel', ticket_ids: cancelDialog.ids, ...options })
  }
  cancelDialog.sending = false
  cancelDialog.show = false
}

const contact = reactive({
  show: false,
  saving: false,
  error: null as string | null,
  id: 0,
  name: '',
  email: '',
  phone: '',
})

function openContact(ticket: TicketItem) {
  validation.reset()
  contact.show = true
  contact.error = null
  contact.id = ticket.id!
  contact.name = ticket.holderName
  contact.email = ticket.holderEmail ?? ''
  contact.phone = ticket.holderPhone ?? ''
}

async function saveContact() {
  validation.markValidated()
  contact.saving = true
  contact.error = null
  try {
    await updateTicketContact(contact.id, { holder_name: contact.name, holder_email: contact.email, holder_phone: contact.phone || null })
    contact.show = false
    toast.success(t('tickets.workspace.contactSaved'))
    await load(page.value)
  } catch (e) {
    contact.error = apiMessage(e, t('tickets.attendees.actionFailed'))
  } finally {
    contact.saving = false
  }
}

const add = reactive({
  show: false,
  saving: false,
  error: null as string | null,
  name: '',
  email: '',
  phone: '',
  typeId: null as number | null,
  quantity: 1,
  sendEmail: true,
  interestId: null as number | null,
})

function openAdd(interest?: EventInterestItem) {
  validation.reset()
  add.show = true
  add.error = null
  add.name = interest?.name ?? ''
  add.email = interest?.email ?? ''
  add.phone = ''
  add.typeId = ticketTypes.value[0]?.id ?? null
  add.quantity = 1
  add.sendEmail = true
  add.interestId = interest?.id ?? null
}

async function saveAdd() {
  validation.markValidated()
  if (!add.typeId) return
  add.saving = true
  add.error = null
  try {
    await addEventAttendee(props.eventId, {
      holder_name: add.name,
      holder_email: add.email,
      holder_phone: add.phone || null,
      ticket_type_id: add.typeId,
      quantity: add.quantity,
      send_email: add.sendEmail,
    })
    if (add.interestId) {
      const done = add.interestId
      await deleteEventInterest(props.eventId, done).catch(() => {})
      interests.value = interests.value.filter(item => item.id !== done)
    }
    add.show = false
    toast.success(t('tickets.workspace.added'))
    await reload()
  } catch (e) {
    add.error = apiMessage(e, t('tickets.attendees.actionFailed'))
  } finally {
    add.saving = false
  }
}

const history = reactive({ show: false, items: [] as EventMessageItem[] })

async function openHistory() {
  history.show = true
  history.items = await attendeeMessages(props.eventId).catch(() => [])
}

onMounted(() => {
  load(1)
  indexEventInterests(props.eventId).then((list) => { interests.value = list }).catch(() => {})
  refreshTypes()
  indexQuestions(props.eventId).then((list) => { questions.value = list }).catch(() => {})
})
</script>
