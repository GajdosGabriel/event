<template>
  <div class="mx-auto my-5 w-full max-w-[1320px] px-4">
    <EventTicketsTabs :event-id="eventId" />

    <!-- Skener je úzky stĺpec (mieri na mobil), ale hlavička s kartami drží
         rovnakú šírku ako ostatné stránky sekcie, nech pri prepínaní neposkakuje.
         Tím kanála ide vedľa: kto smie skenovať, sa rieši práve pri skeneri. -->
    <div class="grid gap-4 lg:grid-cols-[minmax(0,28rem)_22rem]">
      <div>
        <h1 class="mb-2 text-2xl font-semibold text-slate-900">{{ t('checkin.title') }}</h1>

        <p v-if="typesLoading" class="text-slate-500">{{ t('common.loading') }}</p>

        <!-- Bez typu lístka nevznikne žiadna vstupenka, takže niet čo skenovať —
             namiesto mŕtvej kamery ukážeme, čo treba spraviť najskôr. -->
        <section v-else-if="!hasTypes" class="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center">
          <p class="font-semibold text-slate-800">{{ t('checkin.noTypesTitle') }}</p>
          <p class="mt-2 text-sm text-slate-600">{{ t('checkin.noTypesLead') }}</p>
          <RouterLink :to="{ name: 'dashboard-events-tickets-create', params: { id: eventId } }" class="btn btn-primary mt-4">
            {{ t('checkin.noTypesCta') }}
          </RouterLink>
        </section>

        <template v-else>
          <div v-if="stats" class="mb-4 rounded-xl bg-slate-100 px-4 py-3 text-sm font-medium text-slate-700">
            {{ t('checkin.arrived') }} <strong>{{ stats.arrived }}</strong> / {{ stats.total }}
            <span class="text-slate-400">{{ t('checkin.remaining', { n: stats.remaining }) }}</span>
          </div>

          <div class="overflow-hidden rounded-2xl border border-slate-200 bg-black">
            <video ref="videoEl" class="aspect-square w-full object-cover" muted playsinline />
          </div>

          <p v-if="cameraError" class="mt-3 text-sm text-red-600">
            {{ t('checkin.cameraFailed', { error: cameraError }) }}
          </p>

          <div v-if="result" class="mt-4 rounded-xl p-4 text-sm" :class="resultClass">
            <p class="font-semibold">{{ resultTitle }}</p>
            <p v-if="result.admission">
              {{ result.admission.attendeeName || result.admission.holderName }}
              <span v-if="result.admission.ticketType" class="text-xs opacity-70">
                · {{ result.admission.ticketType.kind === 'workshop' ? t('checkin.workshopPrefix') : '' }}{{ result.admission.ticketType.name }}
              </span>
            </p>
          </div>

          <form class="mt-6 flex gap-2" @submit.prevent="submitManual">
            <input v-model.trim="manualToken" type="text" :placeholder="t('checkin.manualPlaceholder')"
              class="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
            <button type="submit" class="action-btn">{{ t('checkin.verify') }}</button>
          </form>
        </template>
      </div>

      <!-- Kto smie skenovať, sa nastavuje rolou v tíme kanála — preto je tím
           priamo tu, nie len ako odkaz preč zo stránky. Meniť sa dá v úprave
           kanála, kam vedie odkaz v paneli. -->
      <aside v-if="canal" class="grid gap-3 self-start">
        <p class="text-xs text-slate-500">{{ t('checkin.whoCanScan') }}</p>
        <CanalTeamPanel
          :canal-id="canal.id"
          readonly
          :manage-to="`/dashboard/canals/${canal.id}/edit#team`"
        />
      </aside>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, nextTick, onMounted, onUnmounted } from 'vue'
import { useRoute } from 'vue-router'
import QrScanner from 'qr-scanner'
import QrScannerWorkerPath from 'qr-scanner/qr-scanner-worker.min.js?url'
import { checkinTicket, checkinStats } from '@/api/tickets'
import { showEvent } from '@/api/events'
import { indexTicketTypes } from '@/api/ticketTypes'
import { currentLocale, t } from '@/i18n'
import CanalTeamPanel from '@/components/CanalTeamPanel.vue'
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
const typesLoading = ref(true)
const hasTypes = ref(false)
const canal = ref<{ id: number; name: string } | null>(null)

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
    case 'checked_in':
      return t('checkin.ok')
    case 'already_checked_in': {
      const at = result.value.admission?.checkedInAt
      return at
        ? t('checkin.usedAt', { time: new Date(at).toLocaleTimeString(currentLocale()) })
        : t('checkin.used')
    }
    default:
      return t('checkin.invalid')
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
  showEvent('dashboard', eventId)
    .then((e) => { if (e.canalId) canal.value = { id: e.canalId, name: e.canalName } })
    .catch(() => {})

  try {
    hasTypes.value = (await indexTicketTypes(eventId)).length > 0
  } catch {
    // Keď sa zoznam nepodarí načítať, radšej ponúkneme skener než prázdny stav.
    hasTypes.value = true
  } finally {
    typesLoading.value = false
  }
  if (!hasTypes.value) return

  loadStats()
  await nextTick()
  if (!videoEl.value) return
  try {
    scanner = new QrScanner(videoEl.value, (r) => handleToken(extractToken(r.data)), {
      highlightScanRegion: true,
      highlightCodeOutline: true,
    })
    await scanner.start()
  } catch (e: unknown) {
    cameraError.value = e instanceof Error ? e.message : t('checkin.cameraUnknown')
  }
})

onUnmounted(() => {
  scanner?.stop()
  scanner?.destroy()
})
</script>
