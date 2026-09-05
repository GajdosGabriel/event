<template>
  <div class="mx-auto my-5 w-full max-w-[1320px] px-4">
    <EventTicketsTabs :event-id="eventId" />

    <div class="mb-4">
      <h1 class="text-2xl font-semibold text-slate-900">{{ eventName || t('checkin.title') }}</h1>
      <p v-if="eventName" class="text-sm text-slate-500">{{ t('checkin.title') }}</p>
    </div>

    <!-- Rovnaké rozloženie ako zoznam prihlásených: obsah vľavo, panel vpravo.
         Úzky ostáva len samotný skener (mieri na mobil) — stropom je karta, nie
         stĺpec, inak by sa obraz z kamery roztiahol cez pol obrazovky. -->
    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_20rem]">
      <div class="min-w-0 max-w-[28rem]">
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
          <!-- Stav spojenia a fronty. Pri vchode musí byť na prvý pohľad
               jasné, či sken doletel, alebo len čaká — inak obsluha nevie,
               či môže pustiť ďalšieho. -->
          <div
            v-if="!online || queued > 0"
            class="mb-3 flex items-center justify-between gap-3 rounded-xl px-4 py-3 text-sm"
            :class="online ? 'bg-blue-50 text-blue-800' : 'bg-amber-50 text-amber-900'"
          >
            <span class="font-medium">
              {{ online ? t('checkin.queueSending') : t('checkin.offline') }}
            </span>
            <span v-if="queued > 0" class="shrink-0 font-semibold">
              {{ t('checkin.queued', { n: queued }) }}
            </span>
          </div>

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

      <!-- Panel drží to, čo sa rieši práve pri príchode ľudí: kto smie skenovať
           (rola v tíme kanála). Doplnkové nastavenia podujatia sú pri zozname
           účastníkov, ktorých sa týkajú. -->
      <aside class="grid gap-3 self-start lg:sticky lg:top-4 lg:max-h-[calc(100vh-2rem)] lg:overflow-y-auto">
        <template v-if="canal">
          <p class="text-xs text-slate-500">{{ t('checkin.whoCanScan') }}</p>
          <CanalTeamPanel
            :canal-id="canal.id"
            readonly
            :manage-to="`/dashboard/canals/${canal.id}/edit#team`"
          />
        </template>
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
import { enqueue, pending, remove, isSupported as queueSupported } from '@/utils/checkinQueue'
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
const eventName = ref('')

let scanner: QrScanner | null = null
let processing = false

/**
 * Offline režim. `navigator.onLine` je len hrubá nápoveda (hlási aj sieť bez
 * internetu), preto sa nespoliehame len na ňu — do fronty ide aj sken, ktorý
 * zlyhal na sieťovej chybe pri online stave.
 */
const online = ref(navigator.onLine)
const queued = ref(0)
let flushing = false

async function refreshQueue() {
  if (!queueSupported()) return
  queued.value = (await pending(eventId)).length
}

/**
 * Prehrá frontu. Záznam sa maže až po odpovedi servera — check-in je
 * idempotentný, takže druhé odoslanie je neškodné, kým stratený sken znamená
 * človeka, ktorý pri vchode „nebol".
 */
async function flushQueue() {
  if (flushing || !navigator.onLine || !queueSupported()) return

  flushing = true
  try {
    for (const scan of await pending(eventId)) {
      try {
        await checkinTicket(scan.token, scan.scannedAt)
        await remove(scan.id)
      } catch {
        // Spojenie je späť len napoly — zvyšok fronty počká na ďalší pokus.
        break
      }
    }
  } finally {
    flushing = false
    await refreshQueue()
    await loadStats()
  }
}

function onOnline() {
  online.value = true
  flushQueue()
}

function onOffline() {
  online.value = false
}

/**
 * Čerstvé počty naprieč zariadeniami.
 *
 * Pri dverách stoja bežne dvaja ľudia s dvoma telefónmi. Doteraz sa počítadlo
 * obnovilo len po vlastnom skene, takže každé zariadenie ukazovalo iné číslo
 * a ani jedno nebolo pravdivé.
 *
 * Polling, nie websocket: portál žiadny nemá a kvôli jednému číslu sa neoplatí.
 * Interval je zámerne pokojný — údaj je orientačný, nie riadiaci.
 */
const STATS_REFRESH_MS = 20000
let statsTimer: ReturnType<typeof setInterval> | undefined

function startStatsRefresh() {
  stopStatsRefresh()
  statsTimer = setInterval(() => {
    // Skrytá karta (telefón v kešeni medzi príchodmi) nemá čo obnovovať.
    if (document.visibilityState === 'visible' && navigator.onLine) loadStats()
  }, STATS_REFRESH_MS)
}

function stopStatsRefresh() {
  if (statsTimer) clearInterval(statsTimer)
  statsTimer = undefined
}

/** Návrat na kartu = najlepší moment na dotiahnutie čísel. */
function onVisibilityChange() {
  if (document.visibilityState === 'visible' && navigator.onLine) loadStats()
}

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
    if (!navigator.onLine && queueSupported()) {
      await queueScan(token)
      return
    }

    result.value = await checkinTicket(token)
    if (result.value.status === 'checked_in') await loadStats()
  } catch (e: unknown) {
    // Bez odpovede servera nevieme, či bola vstupenka platná — a hádať pri
    // vchode „neplatná" je horšie než ju zaradiť a overiť neskôr. Odmietnutie
    // so stavovým kódom (403, 422) je naopak skutočná odpoveď a do fronty
    // nepatrí.
    const status = (e as { response?: { status?: number } })?.response?.status

    if (status === undefined && queueSupported()) {
      await queueScan(token)
      return
    }

    result.value = { status: 'invalid', reason: null, admission: null }
  } finally {
    setTimeout(() => { processing = false }, 1500)
  }
}

/** Zaradí sken do fronty a povie to obsluhe — s vlastným, nie chybovým stavom. */
async function queueScan(token: string) {
  await enqueue(eventId, token)
  await refreshQueue()
  result.value = { status: 'queued', reason: null, admission: null }
}

async function submitManual() {
  if (!manualToken.value) return
  await handleToken(extractToken(manualToken.value))
  manualToken.value = ''
}

const resultTitle = computed(() => {
  switch (result.value?.status) {
    case 'queued':
      return t('checkin.queuedOk')
    case 'checked_in':
      return t('checkin.ok')
    case 'already_checked_in': {
      const admission = result.value.admission
      const at = admission?.checkedInAt
      const time = at ? new Date(at).toLocaleTimeString(currentLocale()) : null
      // Meno obsluhy je tu to podstatné: pri dvoch zariadeniach na dverách
      // hovorí „pustil ho kolega", nie „niekto to skúša druhýkrát".
      const by = admission?.checkedInBy?.name

      if (time && by) return t('checkin.usedAtBy', { time, name: by })
      if (time) return t('checkin.usedAt', { time })
      return t('checkin.used')
    }
    default:
      return t('checkin.invalid')
  }
})

const resultClass = computed(() => {
  switch (result.value?.status) {
    // Modrá, nie zelená: vstupenka ešte nie je overená, len uložená.
    case 'queued': return 'bg-blue-50 text-blue-800'
    case 'checked_in': return 'bg-green-50 text-green-800'
    case 'already_checked_in': return 'bg-amber-50 text-amber-800'
    default: return 'bg-red-50 text-red-800'
  }
})

onMounted(async () => {
  window.addEventListener('online', onOnline)
  window.addEventListener('offline', onOffline)

  // Fronta mohla ostať z minulého behu — zavretá karta, vybitý telefón.
  refreshQueue().then(flushQueue)

  showEvent('dashboard', eventId)
    .then((e) => {
      eventName.value = e.name
      if (e.canalId) canal.value = { id: e.canalId, name: e.canalName }
    })
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
  startStatsRefresh()
  document.addEventListener('visibilitychange', onVisibilityChange)
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
  stopStatsRefresh()
  document.removeEventListener('visibilitychange', onVisibilityChange)
  window.removeEventListener('online', onOnline)
  window.removeEventListener('offline', onOffline)
  scanner?.stop()
  scanner?.destroy()
})
</script>
