<template>
  <div class="mx-auto my-5 w-full max-w-[1320px] px-4">
    <p v-if="loading" class="text-slate-600">Načítavam…</p>
    <div v-else-if="error" class="show-not-found">
      <h1>Miesto nenájdené</h1>
      <RouterLink :to="indexRoute">← Späť</RouterLink>
    </div>

    <template v-else-if="venue">
      <!-- Breadcrumb + akcie -->
      <div class="mb-4 flex flex-wrap items-center gap-2">
        <RouterLink :to="indexRoute" class="action-btn">← Späť</RouterLink>
        <RouterLink v-if="venue.permissions.update" :to="editRoute" class="action-btn">Upraviť</RouterLink>
        <span class="ml-auto rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-wide"
          :class="statusClass(venue.status)">{{ venue.status }}</span>
      </div>

      <!-- Hero obrázok -->
      <div v-if="venue.imageUrl" class="mb-4 h-52 w-full overflow-hidden rounded-2xl">
        <img :src="venue.imageUrl" :alt="venue.name" class="h-full w-full object-cover" />
      </div>

      <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <!-- Ľavý stĺpec -->
        <div class="grid gap-4">
          <!-- Hlavné info -->
          <div class="show-card">
            <div class="mb-2 flex flex-wrap items-start gap-3">
              <div class="flex-1 min-w-0">
                <h1 class="text-3xl font-bold text-slate-900">{{ venue.name }}</h1>
                <p v-if="venue.category" class="mt-1 text-sm font-medium text-teal-700">{{ venue.category }}</p>
              </div>
            </div>
            <div v-if="venue.body" class="prose prose-slate mt-4 max-w-none text-slate-700" v-html="venue.body" />
          </div>

          <!-- Mapa -->
          <div v-if="venue.latitude && venue.longitude" class="show-card overflow-hidden p-0">
            <iframe
              :src="`https://www.openstreetmap.org/export/embed.html?bbox=${venue.longitude - 0.005},${venue.latitude - 0.003},${venue.longitude + 0.005},${venue.latitude + 0.003}&layer=mapnik&marker=${venue.latitude},${venue.longitude}`"
              class="h-72 w-full border-0"
              loading="lazy"
            />
            <div class="px-4 py-2 text-xs text-slate-500">
              GPS: {{ venue.latitude }}, {{ venue.longitude }} ·
              <a :href="`https://www.google.com/maps?q=${venue.latitude},${venue.longitude}`" target="_blank" class="text-blue-600">Google Maps ↗</a>
            </div>
          </div>

          <!-- Galéria -->
          <div v-if="files.length" class="show-card">
            <h2 class="mb-3 text-base font-semibold text-slate-800">Galéria</h2>
            <div class="grid grid-cols-3 gap-2 sm:grid-cols-4">
              <a v-for="f in files" :key="f.id" :href="f.url" target="_blank"
                class="block aspect-square overflow-hidden rounded-lg border border-slate-200">
                <img :src="f.thumbUrl ?? f.url" :alt="f.name" class="h-full w-full object-cover hover:scale-105 transition-transform" />
              </a>
            </div>
          </div>

          <!-- Otváracie hodiny -->
          <div v-if="openingHoursRows.length" class="show-card">
            <h2 class="mb-3 text-base font-semibold text-slate-800">Otváracie hodiny</h2>
            <dl class="grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
              <template v-for="row in openingHoursRows" :key="row.day">
                <dt class="font-medium text-slate-600">{{ row.day }}</dt>
                <dd class="text-slate-900">{{ row.hours }}</dd>
              </template>
            </dl>
          </div>

          <!-- Eventy na tomto mieste -->
          <div class="show-card">
            <div class="mb-3 flex items-center justify-between gap-2">
              <h2 class="text-base font-semibold text-slate-800">Eventy na tomto mieste</h2>
              <RouterLink :to="`${prefix}/events`" class="text-xs text-blue-600 hover:underline">Všetky eventy →</RouterLink>
            </div>
            <p v-if="eventsLoading" class="text-sm text-slate-500">Načítavam…</p>
            <p v-else-if="!events.length" class="text-sm text-slate-400">Žiadne eventy.</p>
            <ul v-else class="grid gap-1.5">
              <li v-for="ev in events" :key="ev.id"
                class="flex items-center gap-3 rounded-lg border border-slate-100 bg-slate-50 px-3 py-2">
                <span class="w-2 h-2 rounded-full shrink-0"
                  :class="ev.status === 'published' ? 'bg-green-500' : ev.status === 'archived' ? 'bg-slate-400' : 'bg-amber-400'" />
                <div class="flex-1 min-w-0">
                  <RouterLink :to="`${prefix}/events/${ev.id}`"
                    class="block truncate text-sm font-medium text-slate-900 no-underline hover:text-blue-700">
                    {{ ev.name }}
                  </RouterLink>
                  <span v-if="ev.canalName" class="mt-0.5 inline-flex items-center rounded-full bg-teal-50 px-2 py-0.5 text-xs font-medium text-teal-700 ring-1 ring-inset ring-teal-200">{{ ev.canalName }}</span>
                </div>
                <span v-if="ev.startAt" class="shrink-0 text-xs text-slate-500">{{ formatDate(ev.startAt) }}</span>
                <RouterLink :to="`${prefix}/events/${ev.id}/edit`" class="action-btn shrink-0">Upraviť</RouterLink>
              </li>
            </ul>
          </div>
        </div>

        <!-- Pravý stĺpec -->
        <aside class="grid gap-4 self-start">
          <dl class="show-card grid gap-3">
            <!-- Adresa -->
            <div v-if="venue.street || venue.municipality" class="detail-card">
              <dt>Adresa</dt>
              <dd>
                <span v-if="venue.street">{{ venue.street }}<br/></span>
                <span v-if="venue.postcode">{{ venue.postcode }} </span>
                <span v-if="venue.municipality">{{ venue.municipality.name }}</span>
                <span v-if="venue.country && venue.country !== 'Slovakia'" class="block text-slate-500 text-xs">{{ venue.country }}</span>
              </dd>
            </div>

            <!-- Kontakt -->
            <div v-if="venue.phone" class="detail-card">
              <dt>Telefón</dt>
              <dd><a :href="`tel:${venue.phone}`" class="text-blue-700">{{ venue.phone }}</a></dd>
            </div>
            <div v-if="venue.website" class="detail-card">
              <dt>Web</dt>
              <dd><a :href="venue.website" target="_blank" class="break-all text-blue-700">{{ venue.website }}</a></dd>
            </div>

            <!-- Kapacita -->
            <div v-if="venue.capacity" class="detail-card">
              <dt>Kapacita</dt>
              <dd>{{ venue.capacity }} osôb</dd>
            </div>

            <!-- Kanály -->
            <div v-if="venue.canalsList.length" class="detail-card">
              <dt>Kanály</dt>
              <dd class="grid gap-1 mt-1">
                <RouterLink
                  v-for="c in venue.canalsList" :key="c.id"
                  :to="`${prefix}/canals/${c.id}`"
                  class="flex items-center gap-1.5 text-sm text-blue-700 no-underline hover:underline">
                  <span v-if="c.isOwner" class="text-xs text-teal-600 font-semibold">[vlastník]</span>
                  {{ c.name }}
                </RouterLink>
              </dd>
            </div>

            <!-- Meta -->
            <div class="detail-card">
              <dt>Vytvorené</dt>
              <dd>{{ formatDate(venue.createdAt) }}</dd>
            </div>
            <div class="detail-card">
              <dt>Upravené</dt>
              <dd>{{ formatDate(venue.updatedAt) }}</dd>
            </div>
            <div v-if="venue.deletedAt" class="detail-card bg-red-50">
              <dt class="text-red-600">Zmazané</dt>
              <dd>{{ formatDate(venue.deletedAt) }}</dd>
            </div>
          </dl>
        </aside>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { showVenue, listVenueEvents, type VenueEventItem } from '@/api/venues'
import { listFiles, type FileItem } from '@/api/files'
import type { VenueItem } from '@/types'

const props = defineProps<{ scope?: 'dashboard' | 'admin' }>()
const route = useRoute()
const scope = computed(() => props.scope ?? (route.path.startsWith('/admin') ? 'admin' : 'dashboard'))
const prefix = computed(() => scope.value === 'admin' ? '/admin' : '/dashboard')
const indexRoute = computed(() => `${prefix.value}/venues`)
const editRoute = computed(() => `${prefix.value}/venues/${route.params.id}/edit`)

const venue = ref<VenueItem | null>(null)
const loading = ref(false)
const error = ref(false)
const files = ref<FileItem[]>([])
const events = ref<VenueEventItem[]>([])
const eventsLoading = ref(false)

const openingHoursRows = computed(() => {
  const oh = venue.value?.openingHours
  if (!oh || typeof oh !== 'object') return []
  const dayNames: Record<string, string> = {
    monday: 'Pondelok', tuesday: 'Utorok', wednesday: 'Streda',
    thursday: 'Štvrtok', friday: 'Piatok', saturday: 'Sobota', sunday: 'Nedeľa',
  }
  return Object.entries(oh as Record<string, string>)
    .map(([day, hours]) => ({ day: dayNames[day] ?? day, hours }))
    .filter(r => r.hours)
})

function statusClass(status: string) {
  return {
    published: 'bg-green-100 text-green-800',
    draft: 'bg-amber-100 text-amber-800',
    archived: 'bg-slate-100 text-slate-600',
  }[status] ?? 'bg-slate-100 text-slate-600'
}

function formatDate(d: string | null) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('sk-SK', { day: 'numeric', month: 'numeric', year: 'numeric' })
}

onMounted(async () => {
  const id = Number(route.params.id)
  loading.value = true
  try {
    venue.value = await showVenue(scope.value, id)
    document.title = venue.value.name

    // Načítaj galériu a eventy paralelne
    eventsLoading.value = true
    const [filesRes, eventsRes] = await Promise.allSettled([
      listFiles({ fileable_type: 'venue', fileable_id: id }),
      listVenueEvents(scope.value, id),
    ])
    if (filesRes.status === 'fulfilled') files.value = filesRes.value.filter(f => !f.deletedAt)
    if (eventsRes.status === 'fulfilled') events.value = eventsRes.value
  } catch {
    error.value = true
  } finally {
    loading.value = false
    eventsLoading.value = false
  }
})
</script>
