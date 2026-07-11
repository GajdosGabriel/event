<template>
  <div>
    <!-- Hero image -->
    <div v-if="event?.imageUrl" class="relative h-72 w-full overflow-hidden md:h-96">
      <img :src="event.imageUrl" :alt="event.name" class="h-full w-full object-cover" />
      <div class="absolute inset-0 bg-linear-to-t from-black/60 to-transparent" />
      <div class="absolute bottom-0 left-0 right-0 px-6 pb-6 text-white">
        <div class="mx-auto max-w-300">
          <p v-if="event.dateRangeLabel" class="mb-1 text-sm font-medium text-white/80">{{ event.dateRangeLabel }}</p>
          <h1 class="text-3xl font-bold leading-tight md:text-4xl">{{ event.name }}</h1>
        </div>
      </div>
    </div>

    <div class="mx-auto w-full max-w-300 px-4 py-8">
      <div v-if="loading" class="flex items-center gap-2 text-slate-500">
        <span class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-slate-300 border-t-blue-600" />
        Načítavam…
      </div>
      <div v-else-if="error" class="rounded-xl border border-red-200 bg-red-50 p-6 text-center">
        <p class="mb-2 text-lg font-semibold text-red-700">Event sa nepodarilo načítať</p>
        <RouterLink to="/" class="text-sm text-blue-600 hover:underline">← Späť na prehľad</RouterLink>
      </div>

      <template v-else-if="event">
        <div v-if="!event.imageUrl" class="mb-6">
          <RouterLink to="/" class="mb-3 inline-block text-sm text-blue-600 hover:underline">← Späť</RouterLink>
          <p v-if="event.dateRangeLabel" class="mb-1 text-sm font-medium text-slate-500">{{ event.dateRangeLabel }}</p>
          <h1 class="text-3xl font-bold text-slate-900 md:text-4xl">{{ event.name }}</h1>
        </div>
        <RouterLink v-else to="/" class="mb-6 inline-block text-sm text-blue-600 hover:underline">← Späť</RouterLink>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-[1fr_340px]">
          <!-- Hlavný stĺpec -->
          <div class="space-y-6">
            <!-- Popis -->
            <div v-if="event.body" class="rounded-2xl border border-slate-200 bg-white p-6">
              <div class="prose prose-slate max-w-none leading-relaxed text-slate-700" v-html="event.body" />
            </div>

            <!-- Workshopy (sub-akcie v rámci eventu) -->
            <div v-if="workshops.length" class="rounded-2xl border border-slate-200 bg-white p-6">
              <div class="mb-4 flex items-center gap-2">
                <svg class="h-4 w-4 text-violet-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                </svg>
                <h2 class="text-base font-semibold text-slate-800">Workshopy</h2>
              </div>
              <p class="mb-3 text-sm text-slate-500">
                Sprievodné workshopy v rámci podujatia. Prihlásiť sa na ne môžu účastníci registrovaní na podujatie.
              </p>
              <p v-if="workshopError" class="mb-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ workshopError }}</p>
              <EventWorkshops
                :workshops="workshops"
                joinable
                :authenticated="auth.isAuthenticated"
                :viewer-registered="viewerRegistered"
                :standalone="standaloneWorkshops"
                :locked="workshopChangesLocked"
                :busy-id="workshopBusyId"
                @join="onJoinWorkshop"
                @leave="onLeaveWorkshop"
              />
            </div>

            <!-- Galéria -->
            <div v-if="event.uploadedImages.length" class="rounded-2xl border border-slate-200 bg-white p-6">
              <h2 class="mb-4 text-base font-semibold text-slate-800">Fotografie</h2>
              <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-4">
                <div v-for="(img, idx) in event.uploadedImages" :key="idx"
                  class="group relative aspect-square cursor-zoom-in overflow-hidden rounded-xl bg-slate-100"
                  @click="lightboxIdx = idx">
                  <img :src="img.thumb || img.large" :alt="event.name"
                    class="h-full w-full object-cover transition-transform duration-200 group-hover:scale-105" />
                </div>
              </div>
            </div>

            <!-- Mapa -->
            <div v-if="mapCoords" class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
              <div class="flex items-center gap-2 px-6 pb-3 pt-5">
                <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 2C8.134 2 5 5.134 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.866-3.134-7-7-7zm0 9a2 2 0 110-4 2 2 0 010 4z"/>
                </svg>
                <h2 class="text-base font-semibold text-slate-800">Mapa</h2>
              </div>
              <iframe :src="mapUrl" width="100%" height="320" frameborder="0" scrolling="no" class="block" title="Mapa miesta konania" />
              <div class="px-6 py-2 text-xs text-slate-500">
                <a :href="`https://www.google.com/maps?q=${mapCoords.lat},${mapCoords.lng}`"
                  target="_blank" class="text-blue-600 hover:underline">Otvoriť v Google Maps ↗</a>
              </div>
            </div>

          </div>

          <!-- Sidebar -->
          <aside class="space-y-4">
            <!-- Termín -->
            <div class="rounded-2xl border border-slate-200 bg-white p-5">
              <div class="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-slate-400">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <rect x="3" y="4" width="18" height="18" rx="2"/><path stroke-linecap="round" d="M16 2v4M8 2v4M3 10h18"/>
                </svg>
                Termín
              </div>
              <EventDateRange :start-at="event.startAt" :end-at="event.endAt" />
              <div v-if="event.registrationDeadlineAt" class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-700">
                Registrácia do: <strong>{{ formatDate(event.registrationDeadlineAt) }}</strong>
              </div>
            </div>

            <!-- Lístok / registrácia -->
            <div v-if="event.ticketsEnabled" class="rounded-2xl border border-slate-200 bg-white p-5">
              <div class="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-slate-400">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6M5 5h14a2 2 0 012 2v3a2 2 0 000 4v3a2 2 0 01-2 2H5a2 2 0 01-2-2v-3a2 2 0 000-4V7a2 2 0 012-2z"/>
                </svg>
                Registrácia
              </div>
              <TicketRequestForm
                :event-id="event.id"
                :types="ticketTypes"
                :registration-deadline-at="event.registrationDeadlineAt"
                :end-at="event.endAt"
                :viewer-registered="viewerRegistered"
                @changed="onRegistrationChanged"
              />
            </div>

            <!-- Miesto -->
            <div v-if="event.venue || event.locationName || event.street || event.municipality"
              class="rounded-2xl border border-slate-200 bg-white p-5">
              <div class="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-slate-400">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 2C8.134 2 5 5.134 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.866-3.134-7-7-7zm0 9a2 2 0 110-4 2 2 0 010 4z"/>
                </svg>
                Miesto
              </div>
              <RouterLink v-if="event.venue?.id" :to="`/venues/${event.venue.id}`"
                class="font-semibold text-slate-900 no-underline hover:text-blue-600">{{ event.venue.name }}</RouterLink>
              <p v-else-if="event.locationName" class="font-semibold text-slate-900">{{ event.locationName }}</p>
              <p v-if="event.venue?.street || event.venue?.postcode" class="mt-0.5 text-sm text-slate-500">
                <span v-if="event.venue?.street">{{ event.venue.street }}, </span>
                <span v-if="event.venue?.postcode">{{ event.venue.postcode }}</span>
              </p>
              <p v-else-if="event.street" class="mt-0.5 text-sm text-slate-500">
                {{ event.street }}<span v-if="event.postcode">, {{ event.postcode }}</span>
              </p>
              <p v-if="event.municipality" class="mt-0.5 text-sm text-slate-500">
                {{ event.municipality.fullname ?? event.municipality.name }}
              </p>
              <div class="mt-1 flex flex-wrap gap-2 text-sm">
                <a v-if="event.venue?.phone" :href="`tel:${event.venue.phone}`" class="text-blue-600">{{ event.venue.phone }}</a>
                <a v-if="event.venue?.website" :href="event.venue.website" target="_blank" class="text-blue-600">{{ event.venue.website }}</a>
              </div>
              <template v-if="venueOpeningHours.length">
                <div class="mt-3 border-t border-slate-100 pt-3">
                  <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Otváracie hodiny</p>
                  <dl class="grid grid-cols-2 gap-x-6 gap-y-0.5 text-sm">
                    <template v-for="row in venueOpeningHours" :key="row.day">
                      <dt class="font-medium text-slate-600">{{ row.day }}</dt>
                      <dd class="text-slate-900">{{ row.hours }}</dd>
                    </template>
                  </dl>
                </div>
              </template>
            </div>

            <!-- Organizátor -->
            <div v-if="event.canal" class="rounded-2xl border border-slate-200 bg-white p-5">
              <div class="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-slate-400">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0"/>
                </svg>
                Organizátor
              </div>
              <RouterLink :to="`/canals/${event.canal.id}`"
                class="font-semibold text-slate-900 no-underline hover:text-blue-600">{{ event.canal.name }}</RouterLink>
            </div>

            <!-- Kontakt -->
            <div v-if="event.phone || event.website" class="rounded-2xl border border-slate-200 bg-white p-5">
              <div class="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-slate-400">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                </svg>
                Kontakt
              </div>
              <div class="space-y-2 text-sm">
                <a v-if="event.phone" :href="`tel:${event.phone}`" class="flex items-center gap-2 text-slate-700 hover:text-blue-600">
                  {{ event.phone }}
                </a>
                <a v-if="event.website" :href="event.website" target="_blank" class="flex items-center gap-2 truncate text-blue-600 hover:underline">
                  {{ event.website.replace(/^https?:\/\//, '') }}
                </a>
              </div>
            </div>
          </aside>
        </div>
      </template>
    </div>

    <!-- Lightbox -->
    <Teleport to="body">
      <Transition enter-active-class="transition duration-150" enter-from-class="opacity-0" enter-to-class="opacity-100"
        leave-active-class="transition duration-100" leave-from-class="opacity-100" leave-to-class="opacity-0">
        <div v-if="lightboxIdx !== null" class="fixed inset-0 z-9999 flex items-center justify-center bg-black/85 p-4"
          @click.self="lightboxIdx = null" @keydown.esc.window="lightboxIdx = null"
          @keydown.left.window="lightboxIdx !== null && lightboxIdx > 0 && lightboxIdx--"
          @keydown.right.window="lightboxIdx !== null && event?.uploadedImages && lightboxIdx < event.uploadedImages.length - 1 && lightboxIdx++">
          <button class="absolute right-4 top-4 rounded-full bg-white/10 p-2 text-white hover:bg-white/20" @click="lightboxIdx = null">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>
          <button v-if="lightboxIdx > 0" class="absolute left-4 top-1/2 -translate-y-1/2 rounded-full bg-white/10 p-3 text-white hover:bg-white/20"
            @click="lightboxIdx--">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
            </svg>
          </button>
          <button v-if="event && lightboxIdx < event.uploadedImages.length - 1"
            class="absolute right-4 top-1/2 -translate-y-1/2 rounded-full bg-white/10 p-3 text-white hover:bg-white/20"
            @click="lightboxIdx++">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
            </svg>
          </button>
          <img v-if="event && lightboxIdx !== null"
            :src="event.uploadedImages[lightboxIdx]?.large || event.uploadedImages[lightboxIdx]?.thumb"
            :alt="event.name"
            class="max-h-[90vh] max-w-[90vw] rounded-xl object-contain shadow-2xl" />
          <div v-if="event && event.uploadedImages.length > 1" class="absolute bottom-4 left-1/2 -translate-x-1/2 rounded-full bg-black/50 px-3 py-1 text-xs text-white">
            {{ lightboxIdx !== null ? lightboxIdx + 1 : '' }} / {{ event.uploadedImages.length }}
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useHead } from '@vueuse/head'
import { showPublicEvent } from '@/api/events'
import { publicTicketTypes, joinWorkshop, leaveWorkshop } from '@/api/ticketTypes'
import { useAuthStore } from '@/stores/auth'
import type { EventItem, TicketTypeItem } from '@/types'
import EventDateRange from '@/components/EventDateRange.vue'
import EventWorkshops from '@/components/EventWorkshops.vue'
import TicketRequestForm from '@/components/TicketRequestForm.vue'

const route = useRoute()
const auth = useAuthStore()
const event = ref<EventItem | null>(null)
const ticketTypes = ref<TicketTypeItem[]>([])
const viewerRegistered = ref(false)
const workshopChangesLocked = ref(false)
const workshopBusyId = ref<number | null>(null)
const workshopError = ref<string | null>(null)
const loading = ref(false)
const error = ref(false)
const lightboxIdx = ref<number | null>(null)

const workshops = computed(() => ticketTypes.value.filter(t => t.kind === 'workshop'))

// Podujatie bez hlavného typu vstupenky (len workshopy) → workshop je samostatná
// registrácia a dá sa naň prihlásiť priamo, bez vstupenky na podujatie.
const standaloneWorkshops = computed(
  () => workshops.value.length > 0 && ticketTypes.value.every(t => t.kind === 'workshop'),
)

async function loadTicketTypes(eventId: number) {
  const result = await publicTicketTypes(eventId)
  ticketTypes.value = result.types
  viewerRegistered.value = result.viewerRegistered
  workshopChangesLocked.value = result.workshopChangesLocked
}

/** Po zmene znovu načítame typy — obnoví viewerJoined aj voľné kapacity. */
async function runWorkshopAction(workshop: TicketTypeItem, action: (eventId: number, typeId: number) => Promise<void>) {
  if (!event.value || !workshop.id) return
  workshopBusyId.value = workshop.id
  workshopError.value = null
  try {
    await action(event.value.id, workshop.id)
    await loadTicketTypes(event.value.id)
    event.value = await showPublicEvent(String(event.value.id))
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    workshopError.value = err.response?.data?.message ?? 'Akciu sa nepodarilo dokončiť.'
  } finally {
    workshopBusyId.value = null
  }
}

const onJoinWorkshop = (w: TicketTypeItem) => runWorkshopAction(w, joinWorkshop)
const onLeaveWorkshop = (w: TicketTypeItem) => runWorkshopAction(w, leaveWorkshop)

/** Po zmene registrácie (zrušenie) znovu načítame typy aj event — obnoví
 *  viewerRegistered aj voľné kapacity. */
async function onRegistrationChanged() {
  if (!event.value) return
  await loadTicketTypes(event.value.id)
  event.value = await showPublicEvent(String(event.value.id))
}

const DAY_NAMES: Record<number, string> = {
  0: 'Nedeľa', 1: 'Pondelok', 2: 'Utorok', 3: 'Streda',
  4: 'Štvrtok', 5: 'Piatok', 6: 'Sobota',
}

function dayName(d: string) { return DAY_NAMES[new Date(d).getDay()] ?? '' }

function formatDate(d: string) {
  return new Date(d).toLocaleDateString('sk-SK', { day: 'numeric', month: 'long', year: 'numeric' })
}

function formatDateTime(d: string) {
  return new Date(d).toLocaleString('sk-SK', { day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

function isSameDay(a: string, b: string) {
  return new Date(a).toDateString() === new Date(b).toDateString()
}

const OH_DAYS: Record<string, string> = {
  monday: 'Pondelok', tuesday: 'Utorok', wednesday: 'Streda',
  thursday: 'Štvrtok', friday: 'Piatok', saturday: 'Sobota', sunday: 'Nedeľa',
}

const venueOpeningHours = computed(() => {
  const oh = event.value?.venue?.openingHours
  if (!oh || typeof oh !== 'object' || Array.isArray(oh)) return []
  return Object.entries(oh as Record<string, string | null>)
    .filter(([, hours]) => hours)
    .map(([day, hours]) => ({ day: OH_DAYS[day] ?? day, hours: hours as string }))
})

// Use event's own coords first, fall back to venue coords
const mapCoords = computed(() => {
  const ev = event.value
  if (!ev) return null
  if (ev.latitude && ev.longitude) return { lat: +ev.latitude, lng: +ev.longitude }
  const vLat = ev.venue?.latitude ? parseFloat(ev.venue.latitude) : null
  const vLng = ev.venue?.longitude ? parseFloat(ev.venue.longitude) : null
  if (vLat && vLng) return { lat: vLat, lng: vLng }
  return null
})

const mapUrl = computed(() => {
  if (!mapCoords.value) return ''
  const { lat, lng } = mapCoords.value
  const d = 0.008
  return `https://www.openstreetmap.org/export/embed.html?bbox=${lng - d},${lat - d},${lng + d},${lat + d}&layer=mapnik&marker=${lat},${lng}`
})

useHead(computed(() => {
  const e = event.value
  if (!e) return { title: 'Načítavam…' }
  const title = e.name
  const description = e.body
    ? e.body.replace(/<[^>]+>/g, '').slice(0, 160).trim()
    : e.dateRangeLabel ? `${e.dateRangeLabel}${e.venue ? ` · ${e.venue.name}` : ''}` : title
  const image = e.imageUrl ?? undefined
  const url = window.location.href
  return {
    title: `${title} | Event`,
    meta: [
      { name: 'description', content: description },
      { property: 'og:title', content: title },
      { property: 'og:description', content: description },
      { property: 'og:type', content: 'event' },
      { property: 'og:url', content: url },
      ...(image ? [{ property: 'og:image', content: image }] : []),
      { name: 'twitter:card', content: image ? 'summary_large_image' : 'summary' },
      { name: 'twitter:title', content: title },
      { name: 'twitter:description', content: description },
      ...(image ? [{ name: 'twitter:image', content: image }] : []),
    ],
  }
}))

onMounted(async () => {
  loading.value = true
  try {
    const ev = await showPublicEvent(route.params.id as string)

    // Typy lístkov (vrátane workshopov) načítame tu — používa ich sekcia
    // workshopov aj registračný formulár, aby sa nerobili dva rovnaké requesty.
    try {
      await loadTicketTypes(ev.id)
    } catch { /* non-fatal — formulár ukáže prázdny stav */ }

    event.value = ev
  } catch {
    error.value = true
  } finally {
    loading.value = false
  }
})
</script>
