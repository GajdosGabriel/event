<template>
  <div>
    <!-- Hero image -->
    <div v-if="event?.imageUrl" class="relative h-72 w-full overflow-hidden md:h-96">
      <img :src="event.imageUrl" :alt="event.name" class="h-full w-full object-cover" />
      <div class="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent" />
      <div class="absolute bottom-0 left-0 right-0 px-6 pb-6 text-white">
        <div class="mx-auto max-w-[1200px]">
          <p v-if="event.dateRangeLabel" class="mb-1 text-sm font-medium text-white/80">
            {{ event.dateRangeLabel }}
          </p>
          <h1 class="text-3xl font-bold leading-tight md:text-4xl">{{ event.name }}</h1>
        </div>
      </div>
    </div>

    <div class="mx-auto w-full max-w-[1200px] px-4 py-8">
      <!-- Loading / error -->
      <div v-if="loading" class="flex items-center gap-2 text-slate-500">
        <span class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-slate-300 border-t-blue-600" />
        Načítavam…
      </div>
      <div v-else-if="error" class="rounded-xl border border-red-200 bg-red-50 p-6 text-center">
        <p class="mb-2 text-lg font-semibold text-red-700">Event sa nepodarilo načítať</p>
        <RouterLink to="/" class="text-sm text-blue-600 hover:underline">← Späť na prehľad</RouterLink>
      </div>

      <template v-else-if="event">
        <!-- Title (no hero) -->
        <div v-if="!event.imageUrl" class="mb-6">
          <RouterLink to="/" class="mb-3 inline-block text-sm text-blue-600 hover:underline">← Späť</RouterLink>
          <p v-if="event.dateRangeLabel" class="mb-1 text-sm font-medium text-slate-500">{{ event.dateRangeLabel }}</p>
          <h1 class="text-3xl font-bold text-slate-900 md:text-4xl">{{ event.name }}</h1>
        </div>
        <RouterLink v-else to="/" class="mb-6 inline-block text-sm text-blue-600 hover:underline">← Späť</RouterLink>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-[1fr_340px]">
          <!-- Main column -->
          <div class="space-y-6">
            <!-- Body -->
            <div v-if="event.body" class="rounded-2xl border border-slate-200 bg-white p-6">
              <div class="prose prose-slate max-w-none text-slate-700 leading-relaxed" v-html="event.body" />
            </div>

            <!-- Photo gallery -->
            <div class="rounded-2xl border border-slate-200 bg-white p-6">
              <h2 class="mb-4 text-base font-semibold text-slate-800">Fotografie</h2>
              <ImageGallery fileable-type="event" :fileable-id="Number(route.params.id)" :public="true" />
            </div>

            <!-- Map -->
            <div v-if="hasCoords" class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
              <div class="flex items-center gap-2 px-6 pt-5 pb-3">
                <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 2C8.134 2 5 5.134 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.866-3.134-7-7-7zm0 9a2 2 0 110-4 2 2 0 010 4z"/></svg>
                <h2 class="text-base font-semibold text-slate-800">Mapa</h2>
              </div>
              <iframe
                :src="mapUrl"
                width="100%"
                height="320"
                frameborder="0"
                scrolling="no"
                class="block"
                title="Mapa miesta konania"
              />
            </div>
          </div>

          <!-- Sidebar -->
          <aside class="space-y-4">
            <!-- Date & time -->
            <div class="rounded-2xl border border-slate-200 bg-white p-5">
              <div class="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-slate-400">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path stroke-linecap="round" d="M16 2v4M8 2v4M3 10h18"/></svg>
                Termín
              </div>
              <p class="text-base font-semibold text-slate-900">{{ event.dateRangeLabel ?? '—' }}</p>
              <div v-if="event.startAt" class="mt-1 text-sm text-slate-500">
                {{ formatDateTime(event.startAt) }}
                <span v-if="event.endAt && !isSameDay(event.startAt, event.endAt)"> – {{ formatDateTime(event.endAt) }}</span>
              </div>
              <div v-if="event.registrationDeadlineAt" class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-700">
                Registrácia do: <strong>{{ formatDate(event.registrationDeadlineAt) }}</strong>
              </div>
            </div>

            <!-- Venue -->
            <div v-if="event.venue || event.locationName" class="rounded-2xl border border-slate-200 bg-white p-5">
              <div class="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-slate-400">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 2C8.134 2 5 5.134 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.866-3.134-7-7-7zm0 9a2 2 0 110-4 2 2 0 010 4z"/></svg>
                Miesto
              </div>
              <RouterLink v-if="event.venue?.id" :to="`/venues/${event.venue.id}`" class="font-semibold text-slate-900 hover:text-blue-600 no-underline">{{ event.venue.name }}</RouterLink>
              <p v-else class="font-semibold text-slate-900">{{ event.locationName }}</p>
              <p v-if="event.venue?.street" class="mt-0.5 text-sm text-slate-500">
                {{ event.venue.street }}<span v-if="event.venue.postcode">, {{ event.venue.postcode }}</span>
              </p>
              <p v-if="event.municipality" class="mt-0.5 text-sm text-slate-500">
                {{ event.municipality.fullname ?? event.municipality.name }}
              </p>
              <a
                v-if="hasCoords"
                :href="`https://www.google.com/maps/search/?api=1&query=${event.venue!.latitude},${event.venue!.longitude}`"
                target="_blank"
                class="mt-3 inline-flex items-center gap-1 text-xs text-blue-600 hover:underline"
              >
                Otvoriť v Google Maps
                <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6M15 3h6m0 0v6m0-6L10 14"/></svg>
              </a>
            </div>

            <!-- Organizer -->
            <div v-if="event.canal" class="rounded-2xl border border-slate-200 bg-white p-5">
              <div class="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-slate-400">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0"/></svg>
                Organizátor
              </div>
              <RouterLink :to="`/canals/${event.canal.id}`" class="font-semibold text-slate-900 hover:text-blue-600 no-underline">{{ event.canal.name }}</RouterLink>
            </div>

            <!-- Contact -->
            <div v-if="event.phone || event.website || event.venue?.phone" class="rounded-2xl border border-slate-200 bg-white p-5">
              <div class="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-slate-400">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                Kontakt
              </div>
              <div class="space-y-2 text-sm">
                <a v-if="event.phone" :href="`tel:${event.phone}`" class="flex items-center gap-2 text-slate-700 hover:text-blue-600">
                  <svg class="h-3.5 w-3.5 text-slate-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                  {{ event.phone }}
                </a>

                <a v-if="event.website" :href="event.website" target="_blank" class="flex items-center gap-2 truncate text-blue-600 hover:underline">
                  <svg class="h-3.5 w-3.5 text-slate-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 2a10 10 0 100 20A10 10 0 0012 2zm0 0c-2.5 2.5-4 5.9-4 10s1.5 7.5 4 10m0-20c2.5 2.5 4 5.9 4 10s-1.5 7.5-4 10M2 12h20"/></svg>
                  {{ event.website.replace(/^https?:\/\//, '') }}
                </a>
              </div>
            </div>
          </aside>
        </div>
      </template>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { showPublicEvent } from '@/api/events'
import type { EventItem } from '@/types'
import ImageGallery from '@/components/ImageGallery.vue'

const route = useRoute()
const event = ref<EventItem | null>(null)
const loading = ref(false)
const error = ref(false)

const hasCoords = computed(() =>
  !!event.value?.venue?.latitude && !!event.value?.venue?.longitude
)

const mapUrl = computed(() => {
  if (!hasCoords.value) return ''
  const lat = parseFloat(event.value!.venue!.latitude!)
  const lng = parseFloat(event.value!.venue!.longitude!)
  const d = 0.008
  return `https://www.openstreetmap.org/export/embed.html?bbox=${lng - d},${lat - d},${lng + d},${lat + d}&layer=mapnik&marker=${lat},${lng}`
})

function formatDate(d: string) {
  return new Date(d).toLocaleDateString('sk-SK', { day: 'numeric', month: 'long', year: 'numeric' })
}

function formatDateTime(d: string) {
  return new Date(d).toLocaleString('sk-SK', { day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

function isSameDay(a: string, b: string) {
  return new Date(a).toDateString() === new Date(b).toDateString()
}

onMounted(async () => {
  loading.value = true
  try {
    event.value = await showPublicEvent(route.params.id as string)
  } catch {
    error.value = true
  } finally {
    loading.value = false
  }
})
</script>
