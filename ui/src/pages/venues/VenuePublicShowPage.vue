<template>
  <div>
    <!-- Hero -->
    <div v-if="venue?.imageUrl" class="relative h-64 w-full overflow-hidden md:h-80">
      <img :src="venue.imageUrl" :alt="venue.name" class="h-full w-full object-cover" />
      <div class="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent" />
      <div class="absolute bottom-0 left-0 right-0 px-6 pb-6 text-white">
        <div class="mx-auto max-w-[1200px]">
          <h1 class="text-3xl font-bold leading-tight md:text-4xl">{{ venue.name }}</h1>
        </div>
      </div>
    </div>

    <div class="mx-auto w-full max-w-[1200px] px-4 py-8">
      <div v-if="loading" class="flex items-center gap-2 text-slate-500">
        <span class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-slate-300 border-t-blue-600" />
        Načítavam…
      </div>
      <div v-else-if="error" class="rounded-xl border border-red-200 bg-red-50 p-6 text-center">
        <p class="mb-2 text-lg font-semibold text-red-700">Miesto sa nepodarilo načítať</p>
        <RouterLink to="/" class="text-sm text-blue-600 hover:underline">← Späť</RouterLink>
      </div>

      <template v-else-if="venue">
        <div v-if="!venue.imageUrl" class="mb-6">
          <RouterLink to="/" class="mb-3 inline-block text-sm text-blue-600 hover:underline">← Späť</RouterLink>
          <h1 class="text-3xl font-bold text-slate-900 md:text-4xl">{{ venue.name }}</h1>
        </div>
        <RouterLink v-else to="/" class="mb-6 inline-block text-sm text-blue-600 hover:underline">← Späť</RouterLink>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-[1fr_300px]">
          <!-- Main -->
          <div class="space-y-6">
            <div v-if="venue.body" class="rounded-2xl border border-slate-200 bg-white p-6">
              <div class="prose prose-slate max-w-none leading-relaxed text-slate-700" v-html="venue.body" />
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-6">
              <h2 class="mb-4 text-base font-semibold text-slate-800">Fotografie</h2>
              <ImageGallery fileable-type="venue" :fileable-id="venueId" :public="true" />
            </div>

            <!-- Map -->
            <div v-if="hasCoords" class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
              <div class="flex items-center gap-2 px-6 pt-5 pb-3">
                <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 2C8.134 2 5 5.134 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.866-3.134-7-7-7zm0 9a2 2 0 110-4 2 2 0 010 4z"/></svg>
                <h2 class="text-base font-semibold text-slate-800">Mapa</h2>
              </div>
              <iframe
                :src="`https://www.openstreetmap.org/export/embed.html?bbox=${venue.longitude! - 0.01},${venue.latitude! - 0.01},${venue.longitude! + 0.01},${venue.latitude! + 0.01}&layer=mapnik&marker=${venue.latitude},${venue.longitude}`"
                width="100%" height="320" frameborder="0" scrolling="no" class="block"
                title="Mapa miesta"
              />
              <div class="px-6 py-3">
                <a
                  :href="`https://www.google.com/maps/search/?api=1&query=${venue.latitude},${venue.longitude}`"
                  target="_blank"
                  class="inline-flex items-center gap-1 text-xs text-blue-600 hover:underline"
                >
                  Otvoriť v Google Maps
                  <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6M15 3h6m0 0v6m0-6L10 14"/></svg>
                </a>
              </div>
            </div>

            <!-- Eventy -->
            <div class="rounded-2xl border border-slate-200 bg-white p-6">
              <h2 class="mb-4 text-base font-semibold text-slate-800">Eventy na tomto mieste</h2>
              <p v-if="eventsLoading" class="text-sm text-slate-500">Načítavam…</p>
              <p v-else-if="!events.length" class="text-sm text-slate-400">Žiadne nadchádzajúce eventy.</p>
              <div v-else class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <EventCard
                  v-for="ev in events"
                  :key="ev.id"
                  :id="ev.id"
                  :name="ev.name"
                  :image-url="ev.imageUrl"
                  :date-label="ev.startAt ? formatDate(ev.startAt) : null"
                  :canal-name="ev.canalName"
                />
              </div>
            </div>
          </div>

          <!-- Sidebar -->
          <aside class="space-y-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-5">
              <div class="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-slate-400">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 2C8.134 2 5 5.134 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.866-3.134-7-7-7zm0 9a2 2 0 110-4 2 2 0 010 4z"/></svg>
                Informácie
              </div>
              <dl class="space-y-3 text-sm">
                <div v-if="venue.street">
                  <dt class="text-xs text-slate-400 uppercase tracking-wide">Adresa</dt>
                  <dd class="text-slate-700">
                    {{ venue.street }}<span v-if="venue.postcode">, {{ venue.postcode }}</span>
                  </dd>
                </div>
                <div v-if="venue.capacity">
                  <dt class="text-xs text-slate-400 uppercase tracking-wide">Kapacita</dt>
                  <dd class="text-slate-700">{{ venue.capacity }} osôb</dd>
                </div>
                <div v-if="venue.category">
                  <dt class="text-xs text-slate-400 uppercase tracking-wide">Typ</dt>
                  <dd class="text-slate-700">{{ venue.category }}</dd>
                </div>
              </dl>
            </div>

            <div v-if="venue.phone || venue.website || venue.contactable" class="rounded-2xl border border-slate-200 bg-white p-5">
              <div class="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-slate-400">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                Kontakt
              </div>
              <div class="space-y-2 text-sm">
                <a v-if="venue.phone" :href="`tel:${venue.phone}`" class="flex items-center gap-2 text-slate-700 hover:text-blue-600">
                  <svg class="h-3.5 w-3.5 shrink-0 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                  {{ venue.phone }}
                </a>
                <a v-if="venue.website" :href="venue.website" target="_blank" class="flex items-center gap-2 truncate text-blue-600 hover:underline">
                  <svg class="h-3.5 w-3.5 shrink-0 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 2a10 10 0 100 20A10 10 0 0012 2zm0 0c-2.5 2.5-4 5.9-4 10s1.5 7.5 4 10m0-20c2.5 2.5 4 5.9 4 10s-1.5 7.5-4 10M2 12h20"/></svg>
                  {{ venue.website.replace(/^https?:\/\//, '') }}
                </a>
              </div>
              <ContactButton v-if="venue.contactable" target-type="venue" :target-id="venue.id" :target-name="venue.name"
                :class="{ 'mt-3': venue.phone || venue.website }" />
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
import { showVenuePublic, listVenueEvents, type VenueEventItem } from '@/api/venues'
import type { VenueItem } from '@/types'
import ImageGallery from '@/components/ImageGallery.vue'
import ContactButton from '@/components/ContactButton.vue'
import EventCard from '@/components/EventCard.vue'

const route = useRoute()
const venueId = computed(() => Number(route.params.id))

const venue = ref<VenueItem | null>(null)
const loading = ref(false)
const error = ref(false)
const events = ref<VenueEventItem[]>([])
const eventsLoading = ref(false)

const hasCoords = computed(() => venue.value?.latitude != null && venue.value?.longitude != null)

function formatDate(d: string | null) {
  if (!d) return ''
  return new Date(d).toLocaleDateString('sk-SK', { day: 'numeric', month: 'long', year: 'numeric' })
}

onMounted(async () => {
  loading.value = true
  try {
    venue.value = await showVenuePublic(venueId.value)
    eventsLoading.value = true
    events.value = await listVenueEvents('public', venueId.value)
  }
  catch { error.value = true }
  finally { loading.value = false; eventsLoading.value = false }
})
</script>
