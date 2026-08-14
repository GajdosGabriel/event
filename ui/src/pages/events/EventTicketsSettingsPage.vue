<template>
  <div class="mx-auto my-5 w-full max-w-[1000px] px-4">
    <EventTicketsTabs :event-id="eventId" />

    <div class="mb-4">
      <h1 class="text-2xl font-semibold text-slate-900">{{ eventName || t('tickets.settings.title') }}</h1>
      <p v-if="eventName" class="text-sm text-slate-500">{{ t('tickets.settings.title') }}</p>
    </div>

    <p v-if="loading" class="text-slate-500">{{ t('tickets.settings.loading') }}</p>
    <p v-else-if="loadError" class="text-red-600">{{ loadError }}</p>

    <template v-else>
      <!-- Nastavenia predaja -->
      <section class="mb-6 rounded-2xl border border-slate-200 bg-white p-5">
        <h2 class="mb-3 text-lg font-semibold text-slate-800">{{ t('tickets.settings.heading') }}</h2>
        <p class="mb-3 text-xs text-slate-500">
          {{ t('tickets.settings.lead') }}
        </p>
        <FormField
          v-model="settings.workshop_lock_on_start"
          type="checkbox"
          :label="t('tickets.settings.workshopLock')"
          :hint="t('tickets.settings.workshopLockHint')"
          class="mb-3"
        />
        <FormField v-model="settings.reminder_hours_before" type="select" :label="t('tickets.settings.reminder')" class="max-w-sm">
          <option :value="null">{{ t('tickets.settings.reminderNone') }}</option>
          <option :value="2">{{ t('tickets.settings.reminder2h') }}</option>
          <option :value="24">{{ t('tickets.settings.reminder24h') }}</option>
          <option :value="48">{{ t('tickets.settings.reminder48h') }}</option>
          <option :value="168">{{ t('tickets.settings.reminder168h') }}</option>
          <template #footer>
            <span class="form-hint">
              {{ t('tickets.settings.reminderHint') }}
              <template v-if="reminderSentAt"> {{ t('tickets.settings.reminderSent', { date: reminderSentAt }) }}</template>
            </span>
          </template>
        </FormField>

        <div class="mt-4">
          <button type="button" class="btn btn-primary" :disabled="savingSettings" @click="saveSettings">
            {{ savingSettings ? t('tickets.settings.saving') : t('tickets.settings.save') }}
          </button>
        </div>
      </section>

      <!-- Typy lístkov -->
      <section class="rounded-2xl border border-slate-200 bg-white p-5">
        <div class="mb-3 flex items-center justify-between">
          <h2 class="text-lg font-semibold text-slate-800">{{ t('tickets.settings.typesTitle') }}</h2>
          <RouterLink :to="{ name: 'dashboard-events-tickets-create', params: { id: eventId } }" class="btn btn-secondary">
            {{ t('tickets.settings.typeNew') }}
          </RouterLink>
        </div>

        <p v-if="!types.length" class="text-sm text-slate-400">{{ t('tickets.settings.typesEmpty') }}</p>

        <div v-else class="overflow-hidden rounded-xl border border-slate-200">
          <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
              <tr>
                <th class="px-4 py-3">{{ t('tickets.settings.colName') }}</th>
                <th class="px-4 py-3">{{ t('tickets.settings.colPrice') }}</th>
                <th class="px-4 py-3">{{ t('tickets.settings.colCapacity') }}</th>
                <th class="px-4 py-3">{{ t('tickets.settings.colSold') }}</th>
                <th class="px-4 py-3">{{ t('tickets.settings.colStatus') }}</th>
                <th class="px-4 py-3"></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="type in types" :key="type.id">
                <td class="px-4 py-3 font-medium text-slate-900">
                  {{ type.name }}
                  <span v-if="type.kind === 'workshop'" class="ml-1 rounded-full bg-violet-100 px-2 py-0.5 text-xs font-medium text-violet-700">
                    {{ t('tickets.settings.workshop') }}<template v-if="type.openToPublic"> · {{ t('tickets.settings.workshopOpen') }}</template>
                  </span>
                  <span v-if="type.requiresAttendeeName" class="ml-1 text-xs font-normal text-slate-400">{{ t('tickets.settings.attendeeNames') }}</span>
                </td>
                <td class="px-4 py-3 text-slate-600">{{ type.priceAmount ? formatPrice(type.priceAmount, type.priceCurrency) : t('tickets.settings.free') }}</td>
                <td class="px-4 py-3 text-slate-600">{{ type.capacity ?? '∞' }}</td>
                <td class="px-4 py-3 text-slate-600">
                  {{ type.soldCount ?? 0 }}
                  <span v-if="type.waitlistCount" class="ml-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">
                    {{ t('tickets.settings.waitlist', { n: type.waitlistCount }) }}
                  </span>
                </td>
                <td class="px-4 py-3">
                  <span class="rounded-full px-2 py-0.5 text-xs font-medium"
                    :class="type.isActive ? 'bg-green-100 text-green-700' : 'bg-slate-200 text-slate-600'">
                    {{ type.isActive ? t('tickets.settings.active') : t('tickets.settings.inactive') }}
                  </span>
                </td>
                <td class="px-4 py-3 text-right whitespace-nowrap">
                  <RouterLink :to="{ name: 'dashboard-events-tickets-edit', params: { id: eventId, typeId: type.id } }" class="action-btn">
                    {{ t('tickets.settings.edit') }}
                  </RouterLink>
                  <button type="button" class="action-btn ml-1 text-red-600" @click="remove(type)">{{ t('tickets.settings.remove') }}</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </template>
  </div>
</template>

<script setup lang="ts">
import { reactive, ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { showEvent } from '@/api/events'
import { indexTicketTypes, deleteTicketType, updateTicketingSettings } from '@/api/ticketTypes'
import { currentLocale, t } from '@/i18n'
import { useToast } from '@/composables/useToast'
import EventTicketsTabs from '@/components/EventTicketsTabs.vue'
import type { TicketTypeItem } from '@/types'
import { formatPrice } from '@/utils/money'

const route = useRoute()
const toast = useToast()
const eventId = Number(route.params.id)

const loading = ref(false)
const loadError = ref<string | null>(null)
const savingSettings = ref(false)
const eventName = ref('')

const settings = reactive({
  workshop_lock_on_start: true,
  reminder_hours_before: null as number | null,
})

const reminderSentAt = ref<string | null>(null)

const types = ref<TicketTypeItem[]>([])

async function loadAll() {
  loading.value = true
  loadError.value = null
  try {
    const ev = await showEvent('dashboard', eventId)
    eventName.value = ev.name
    settings.workshop_lock_on_start = ev.workshopLockOnStart ?? true
    settings.reminder_hours_before = ev.reminderHoursBefore
    reminderSentAt.value = ev.reminderSentAt
      ? new Date(ev.reminderSentAt).toLocaleString(currentLocale(), { day: 'numeric', month: 'numeric', hour: '2-digit', minute: '2-digit' })
      : null
    types.value = await indexTicketTypes(eventId)
  } catch {
    loadError.value = t('tickets.settings.loadFailed')
  } finally {
    loading.value = false
  }
}

async function saveSettings() {
  savingSettings.value = true
  try {
    await updateTicketingSettings(eventId, {
      workshop_lock_on_start: settings.workshop_lock_on_start,
      reminder_hours_before: settings.reminder_hours_before,
    })
    toast.success(t('tickets.settings.saved'))
  } catch {
    toast.error(t('tickets.settings.saveFailed'))
  } finally {
    savingSettings.value = false
  }
}

async function remove(type: TicketTypeItem) {
  if (!type.id || !confirm(t('tickets.settings.removeConfirm', { name: type.name }))) return
  try {
    await deleteTicketType(eventId, type.id)
    types.value = await indexTicketTypes(eventId)
    toast.success(t('tickets.settings.removed'))
  } catch {
    toast.error(t('tickets.settings.removeFailed'))
  }
}

onMounted(loadAll)
</script>
