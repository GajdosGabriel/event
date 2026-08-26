<template>
  <div class="mx-auto my-5 w-full max-w-[1320px] px-4">
    <EventTicketsTabs :event-id="eventId" show-back />

    <div class="mb-4">
      <h1 class="text-2xl font-semibold text-slate-900">{{ eventName || t('tickets.settings.title') }}</h1>
      <p v-if="eventName" class="text-sm text-slate-500">{{ t('tickets.settings.title') }}</p>
    </div>

    <p v-if="loading" class="text-slate-500">{{ t('tickets.settings.loading') }}</p>
    <p v-else-if="loadError" class="text-red-600">{{ loadError }}</p>

    <template v-else>
      <section class="rounded-2xl border border-slate-200 bg-white p-5">
        <div class="mb-1 flex items-center justify-between">
          <h2 class="text-lg font-semibold text-slate-800">{{ t('tickets.settings.typesTitle') }}</h2>
          <RouterLink :to="{ name: 'dashboard-events-tickets-create', params: { id: eventId } }" class="btn btn-secondary">
            {{ t('tickets.settings.typeNew') }}
          </RouterLink>
        </div>

        <p class="mb-3 text-xs text-slate-500">{{ t('tickets.settings.lead') }}</p>

        <p v-if="!types.length" class="text-sm text-slate-400">{{ t('tickets.settings.typesEmpty') }}</p>

        <div v-else class="overflow-x-auto rounded-xl border border-slate-200">
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
                <td class="px-4 py-3">
                  <div class="flex justify-end">
                    <RowActions>
                      <RouterLink :to="{ name: 'dashboard-events-tickets-edit', params: { id: eventId, typeId: type.id } }" class="row-menu-item">
                        {{ t('tickets.settings.edit') }}
                      </RouterLink>
                      <button type="button" class="row-menu-item row-menu-item-danger" @click="remove(type)">{{ t('tickets.settings.remove') }}</button>
                    </RowActions>
                  </div>
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
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { showEvent } from '@/api/events'
import { indexTicketTypes, deleteTicketType } from '@/api/ticketTypes'
import { t } from '@/i18n'
import { useToast } from '@/composables/useToast'
import EventTicketsTabs from '@/components/EventTicketsTabs.vue'
import RowActions from '@/components/RowActions.vue'
import type { TicketTypeItem } from '@/types'
import { formatPrice } from '@/utils/money'

const route = useRoute()
const toast = useToast()
const eventId = Number(route.params.id)

const loading = ref(false)
const loadError = ref<string | null>(null)
const eventName = ref('')

const types = ref<TicketTypeItem[]>([])

async function loadAll() {
  loading.value = true
  loadError.value = null
  try {
    const ev = await showEvent('dashboard', eventId)
    eventName.value = ev.name
    types.value = await indexTicketTypes(eventId)
  } catch {
    loadError.value = t('tickets.settings.loadFailed')
  } finally {
    loading.value = false
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
