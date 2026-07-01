<template>
  <div class="mx-auto w-full max-w-md px-4 py-8">
    <div v-if="loading" class="text-center text-slate-500">Načítavam…</div>
    <div v-else-if="error" class="rounded-xl border border-red-200 bg-red-50 p-6 text-center">
      <p class="mb-2 text-lg font-semibold text-red-700">Lístok sa nenašiel</p>
      <RouterLink to="/" class="text-sm text-blue-600 hover:underline">← Späť na úvod</RouterLink>
    </div>

    <div v-else-if="ticket" class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
      <div class="bg-linear-to-br from-blue-600 to-blue-800 p-6 text-white">
        <p class="text-xs font-semibold uppercase tracking-wider text-blue-100">Lístok</p>
        <h1 class="mt-1 text-2xl font-bold">{{ ticket.event?.name }}</h1>
        <p v-if="ticket.event?.dateRangeLabel" class="mt-1 text-sm text-blue-100">{{ ticket.event.dateRangeLabel }}</p>
      </div>

      <div class="space-y-4 p-6">
        <div>
          <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Meno</p>
          <p class="text-lg font-semibold text-slate-900">{{ ticket.holderName }}</p>
        </div>

        <div class="flex items-center gap-2">
          <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="statusClass">{{ ticket.statusLabel }}</span>
          <span v-if="ticket.isCheckedIn" class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700">
            Potvrdený vstup {{ formatDateTime(ticket.checkedInAt) }}
          </span>
        </div>

        <div v-if="ticket.priceAmount" class="text-sm text-slate-600">
          Cena: <strong>{{ formatPrice(ticket.priceAmount, ticket.priceCurrency) }}</strong> ({{ ticket.paymentStatusLabel }})
        </div>

        <div class="flex flex-col items-center gap-2 rounded-xl bg-slate-50 p-4">
          <img :src="qrUrl" alt="QR kód lístka" class="h-56 w-56" />
          <p class="text-xs text-slate-500">Tento QR kód predložte pri vstupe na akciu.</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { showTicket, ticketQrImageUrl } from '@/api/tickets'
import type { TicketItem } from '@/types'

const route = useRoute()
const ticket = ref<TicketItem | null>(null)
const loading = ref(false)
const error = ref(false)

const qrUrl = computed(() => (ticket.value ? ticketQrImageUrl(ticket.value.uuid) : ''))

const statusClass = computed(() => {
  switch (ticket.value?.status) {
    case 'confirmed': return 'bg-green-100 text-green-700'
    case 'cancelled': return 'bg-red-100 text-red-700'
    default: return 'bg-amber-100 text-amber-700'
  }
})

function formatDateTime(d: string | null) {
  if (!d) return ''
  return new Date(d).toLocaleString('sk-SK', { day: 'numeric', month: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

function formatPrice(amount: number, currency: string | null) {
  return new Intl.NumberFormat('sk-SK', { style: 'currency', currency: currency ?? 'EUR' }).format(amount / 100)
}

onMounted(async () => {
  loading.value = true
  try {
    ticket.value = await showTicket(route.params.uuid as string)
  } catch {
    error.value = true
  } finally {
    loading.value = false
  }
})
</script>
