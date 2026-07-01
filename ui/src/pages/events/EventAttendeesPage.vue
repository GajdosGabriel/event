<template>
  <div class="mx-auto my-5 w-full max-w-[1320px] px-4">
    <div class="mb-4 flex flex-wrap items-center gap-2">
      <RouterLink :to="`/dashboard/events/${eventId}`" class="action-btn">← Späť na event</RouterLink>
      <RouterLink :to="`/dashboard/events/${eventId}/checkin`" class="action-btn ml-auto">Check-in skener</RouterLink>
    </div>

    <h1 class="mb-4 text-2xl font-semibold text-slate-900">Prihlásení / lístky</h1>

    <input v-model="search" type="search" placeholder="Hľadať podľa mena alebo e-mailu…"
      class="mb-4 w-full max-w-sm rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
      @input="onSearch" />

    <p v-if="loading" class="text-slate-500">Načítavam…</p>
    <p v-else-if="error" class="text-red-600">{{ error }}</p>
    <p v-else-if="!tickets.length" class="text-slate-400">Zatiaľ žiadni prihlásení.</p>

    <div v-else class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
      <table class="w-full text-sm">
        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
          <tr>
            <th class="px-4 py-3">Meno</th>
            <th class="px-4 py-3">E-mail</th>
            <th class="px-4 py-3">Stav</th>
            <th class="px-4 py-3">Platba</th>
            <th class="px-4 py-3">Vstup</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="ticket in tickets" :key="ticket.id">
            <td class="px-4 py-3 font-medium text-slate-900">{{ ticket.holderName }}</td>
            <td class="px-4 py-3 text-slate-600">{{ ticket.holderEmail }}</td>
            <td class="px-4 py-3">
              <span class="rounded-full px-2 py-0.5 text-xs font-medium"
                :class="ticket.status === 'cancelled' ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-700'">
                {{ ticket.statusLabel }}
              </span>
            </td>
            <td class="px-4 py-3 text-slate-600">{{ ticket.paymentStatusLabel }}</td>
            <td class="px-4 py-3">
              <span v-if="ticket.isCheckedIn" class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700">
                Áno {{ formatDateTime(ticket.checkedInAt) }}
              </span>
              <span v-else class="text-xs text-slate-400">Nie</span>
            </td>
            <td class="px-4 py-3 text-right">
              <button v-if="ticket.permissions?.update && ticket.status !== 'cancelled'" type="button"
                class="action-btn" @click="onCancel(ticket)">Zrušiť</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="meta && meta.last_page > 1" class="mt-4 flex items-center gap-2">
      <button type="button" class="action-btn" :disabled="page <= 1" @click="changePage(page - 1)">← Predch.</button>
      <span class="text-sm text-slate-500">{{ page }} / {{ meta.last_page }}</span>
      <button type="button" class="action-btn" :disabled="page >= meta.last_page" @click="changePage(page + 1)">Ďalej →</button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { cancelTicket, indexEventTickets } from '@/api/tickets'
import type { PaginatedResponse, TicketItem } from '@/types'

const route = useRoute()
const eventId = Number(route.params.id)

const tickets = ref<TicketItem[]>([])
const meta = ref<PaginatedResponse<TicketItem>['meta'] | null>(null)
const loading = ref(false)
const error = ref<string | null>(null)
const search = ref('')
const page = ref(1)

let searchTimeout: ReturnType<typeof setTimeout> | undefined

function formatDateTime(d: string | null) {
  if (!d) return ''
  return new Date(d).toLocaleString('sk-SK', { day: 'numeric', month: 'numeric', hour: '2-digit', minute: '2-digit' })
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
    error.value = 'Zoznam sa nepodarilo načítať.'
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

async function onCancel(ticket: TicketItem) {
  if (!ticket.id || !confirm(`Naozaj zrušiť lístok pre ${ticket.holderName}?`)) return
  await cancelTicket(ticket.id)
  await load(page.value)
}

onMounted(() => load(1))
</script>
