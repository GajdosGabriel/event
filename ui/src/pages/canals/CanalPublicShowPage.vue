<template>
  <div>
    <!-- Hero -->
    <div v-if="canal?.imageUrl" class="relative h-64 w-full overflow-hidden md:h-80">
      <img :src="canal.imageUrl" :alt="canal.name" class="h-full w-full object-cover" />
      <div class="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent" />
      <div class="absolute bottom-0 left-0 right-0 px-6 pb-6 text-white">
        <div class="mx-auto max-w-[1200px]">
          <h1 class="text-3xl font-bold leading-tight md:text-4xl">{{ canal.name }}</h1>
        </div>
      </div>
    </div>

    <div class="mx-auto w-full max-w-[1200px] px-4 py-8">
      <div v-if="loading" class="flex items-center gap-2 text-slate-500">
        <span class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-slate-300 border-t-blue-600" />
        Načítavam…
      </div>
      <div v-else-if="error" class="rounded-xl border border-red-200 bg-red-50 p-6 text-center">
        <p class="mb-2 text-lg font-semibold text-red-700">Kanál sa nepodarilo načítať</p>
        <RouterLink to="/" class="text-sm text-blue-600 hover:underline">← Späť</RouterLink>
      </div>

      <template v-else-if="canal">
        <div v-if="!canal.imageUrl" class="mb-6">
          <RouterLink to="/" class="mb-3 inline-block text-sm text-blue-600 hover:underline">← Späť</RouterLink>
          <h1 class="text-3xl font-bold text-slate-900 md:text-4xl">{{ canal.name }}</h1>
        </div>
        <RouterLink v-else to="/" class="mb-6 inline-block text-sm text-blue-600 hover:underline">← Späť</RouterLink>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-[1fr_300px]">
          <!-- Main -->
          <div class="space-y-6">
            <div v-if="canal.body" class="rounded-2xl border border-slate-200 bg-white p-6">
              <div class="prose prose-slate max-w-none leading-relaxed text-slate-700" v-html="canal.body" />
            </div>
            <div v-if="!canal.body" class="rounded-2xl border border-slate-200 bg-white p-6 text-slate-500">
              Žiadny opis nie je k dispozícii.
            </div>

            <!-- Mapa -->
            <div v-if="canal.latitude && canal.longitude" class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
              <iframe
                :src="`https://www.openstreetmap.org/export/embed.html?bbox=${canal.longitude - 0.005},${canal.latitude - 0.003},${canal.longitude + 0.005},${canal.latitude + 0.003}&layer=mapnik&marker=${canal.latitude},${canal.longitude}`"
                width="100%" height="320" frameborder="0" scrolling="no" class="block" title="Mapa" loading="lazy"
              />
              <div class="px-6 py-2 text-xs text-slate-500">
                <a :href="`https://www.google.com/maps?q=${canal.latitude},${canal.longitude}`" target="_blank" class="text-blue-600 hover:underline">Otvoriť v Google Maps ↗</a>
              </div>
            </div>

            <!-- Eventy -->
            <div class="rounded-2xl border border-slate-200 bg-white p-6">
              <h2 class="mb-4 text-base font-semibold text-slate-800">Eventy organizátora</h2>
              <p v-if="eventsLoading" class="text-sm text-slate-500">Načítavam…</p>
              <p v-else-if="!events.length" class="text-sm text-slate-400">Žiadne publikované eventy.</p>
              <div v-else class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <EventCard
                  v-for="ev in events"
                  :key="ev.id"
                  :id="ev.id"
                  :name="ev.name"
                  :image-url="ev.imageUrl"
                  :date-label="ev.startAt ? formatDate(ev.startAt) : null"
                  :canal-name="canal.name"
                />
              </div>
            </div>

            <!-- Miesta -->
            <div v-if="canal.venuesList.length" class="rounded-2xl border border-slate-200 bg-white p-6">
              <h2 class="mb-4 text-base font-semibold text-slate-800">Miesta</h2>
              <ul class="grid gap-2">
                <li v-for="v in canal.venuesList" :key="v.id"
                  class="flex items-center gap-3 rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
                  <RouterLink :to="`/venues/${v.id}`"
                    class="min-w-0 flex-1 truncate text-sm font-medium text-slate-900 no-underline hover:text-blue-700">
                    {{ v.name }}
                  </RouterLink>
                </li>
              </ul>
            </div>
          </div>

          <!-- Sidebar -->
          <aside class="space-y-4">
            <div v-if="canal.phone || canal.website || canal.contactable" class="rounded-2xl border border-slate-200 bg-white p-5">
              <div class="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-slate-400">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                Kontakt
              </div>
              <div class="space-y-2 text-sm">
                <a v-if="canal.phone" :href="`tel:${canal.phone}`" class="flex items-center gap-2 text-slate-700 hover:text-blue-600">
                  <svg class="h-3.5 w-3.5 shrink-0 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                  {{ canal.phone }}
                </a>
                <a v-if="canal.website" :href="canal.website" target="_blank" class="flex items-center gap-2 truncate text-blue-600 hover:underline">
                  <svg class="h-3.5 w-3.5 shrink-0 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 2a10 10 0 100 20A10 10 0 0012 2zm0 0c-2.5 2.5-4 5.9-4 10s1.5 7.5 4 10m0-20c2.5 2.5 4 5.9 4 10s-1.5 7.5-4 10M2 12h20"/></svg>
                  {{ canal.website.replace(/^https?:\/\//, '') }}
                </a>
              </div>
              <ContactButton v-if="canal.contactable" target-type="canal" :target-id="canal.id" :target-name="canal.name"
                :class="{ 'mt-3': canal.phone || canal.website }" />
            </div>

            <div v-if="canal.municipality" class="rounded-2xl border border-slate-200 bg-white p-5">
              <div class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Pôsobí v</div>
              <p class="text-sm text-slate-700">{{ canal.municipality.name }}</p>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5">
              <div class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Organizátor od</div>
              <p class="text-sm text-slate-700">{{ formatDate(canal.createdAt) }}</p>
            </div>
          </aside>
        </div>
      </template>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { showCanalPublic, listCanalEvents, type CanalEventItem } from '@/api/canals'
import type { CanalItem } from '@/types'
import ContactButton from '@/components/ContactButton.vue'
import EventCard from '@/components/EventCard.vue'

const route = useRoute()
const canal = ref<CanalItem | null>(null)
const loading = ref(false)
const error = ref(false)
const events = ref<CanalEventItem[]>([])
const eventsLoading = ref(false)

function formatDate(d: string) { return new Date(d).toLocaleDateString('sk-SK') }

onMounted(async () => {
  const id = Number(route.params.id)
  loading.value = true
  try {
    canal.value = await showCanalPublic(id)
    eventsLoading.value = true
    events.value = await listCanalEvents('public', id)
  }
  catch { error.value = true }
  finally { loading.value = false; eventsLoading.value = false }
})
</script>
