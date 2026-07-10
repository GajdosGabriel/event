<template>
  <div class="mx-auto my-5 w-full max-w-md px-4">
    <EventTicketsTabs :event-id="eventId" />

    <h1 class="mb-2 text-2xl font-semibold text-slate-900">Check-in — skenovanie QR</h1>

    <div v-if="stats" class="mb-4 rounded-xl bg-slate-100 px-4 py-3 text-sm font-medium text-slate-700">
      Prišlo: <strong>{{ stats.arrived }}</strong> / {{ stats.total }}
      <span class="text-slate-400">· zostáva {{ stats.remaining }}</span>
    </div>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-black">
      <video ref="videoEl" class="aspect-square w-full object-cover" muted playsinline />
    </div>

    <p v-if="cameraError" class="mt-3 text-sm text-red-600">
      Kameru sa nepodarilo spustiť: {{ cameraError }}
    </p>

    <div v-if="result" class="mt-4 rounded-xl p-4 text-sm" :class="resultClass">
      <p class="font-semibold">{{ resultTitle }}</p>
      <p v-if="result.admission">
        {{ result.admission.attendeeName || result.admission.holderName }}
        <span v-if="result.admission.ticketType" class="text-xs opacity-70">
          · {{ result.admission.ticketType.kind === 'workshop' ? 'Workshop: ' : '' }}{{ result.admission.ticketType.name }}
        </span>
      </p>
    </div>

    <form class="mt-6 flex gap-2" @submit.prevent="submitManual">
      <input v-model.trim="manualToken" type="text" placeholder="Alebo zadaj kód ručne…"
        class="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
      <button type="submit" class="action-btn">Overiť</button>
    </form>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRoute } from 'vue-router'
import QrScanner from 'qr-scanner'
import QrScannerWorkerPath from 'qr-scanner/qr-scanner-worker.min.js?url'
import { checkinTicket, checkinStats } from '@/api/tickets'
import EventTicketsTabs from '@/components/EventTicketsTabs.vue'
import type { CheckinStats, TicketCheckinResult } from '@/types'

QrScanner.WORKER_PATH = QrScannerWorkerPath

const route = useRoute()
const eventId = Number(route.params.id)

const videoEl = ref<HTMLVideoElement | null>(null)
const cameraError = ref<string | null>(null)
const result = ref<TicketCheckinResult | null>(null)
const manualToken = ref('')
const stats = ref<CheckinStats | null>(null)

let scanner: QrScanner | null = null
let processing = false

function extractToken(scanned: string): string {
  return scanned.startsWith('TICKET:') ? scanned.slice('TICKET:'.length) : scanned
}

async function loadStats() {
  try {
    stats.value = await checkinStats(eventId)
  } catch {
    // ignore
  }
}

async function handleToken(token: string) {
  if (processing || !token) return
  processing = true
  try {
    result.value = await checkinTicket(token)
    if (result.value.status === 'checked_in') await loadStats()
  } catch {
    result.value = { status: 'invalid', reason: null, admission: null }
  } finally {
    setTimeout(() => { processing = false }, 1500)
  }
}

async function submitManual() {
  if (!manualToken.value) return
  await handleToken(extractToken(manualToken.value))
  manualToken.value = ''
}

const resultTitle = computed(() => {
  switch (result.value?.status) {
    case 'checked_in': return '✅ Vstup potvrdený'
    case 'already_checked_in': return `⚠️ Lístok už bol použitý ${result.value.admission?.checkedInAt ? 'o ' + new Date(result.value.admission.checkedInAt).toLocaleTimeString('sk-SK') : ''}`
    default: return '❌ Neplatný lístok'
  }
})

const resultClass = computed(() => {
  switch (result.value?.status) {
    case 'checked_in': return 'bg-green-50 text-green-800'
    case 'already_checked_in': return 'bg-amber-50 text-amber-800'
    default: return 'bg-red-50 text-red-800'
  }
})

onMounted(async () => {
  loadStats()
  if (!videoEl.value) return
  try {
    scanner = new QrScanner(videoEl.value, (r) => handleToken(extractToken(r.data)), {
      highlightScanRegion: true,
      highlightCodeOutline: true,
    })
    await scanner.start()
  } catch (e: unknown) {
    cameraError.value = e instanceof Error ? e.message : 'neznáma chyba'
  }
})

onUnmounted(() => {
  scanner?.stop()
  scanner?.destroy()
})
</script>
