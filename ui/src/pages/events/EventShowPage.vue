<template>
  <div class="mx-auto my-5 w-full max-w-[1320px] px-4">
    <p v-if="loading" class="text-slate-600">Načítavam…</p>

    <div v-else-if="error" class="show-not-found">
      <h1>Event nenájdený</h1>
      <RouterLink :to="indexRoute">← Späť na zoznam</RouterLink>
    </div>

    <template v-else-if="event">
      <!-- Hero image -->
      <div v-if="event.imageUrl" class="relative mb-4 h-56 w-full overflow-hidden rounded-2xl sm:h-72">
        <img :src="event.imageUrl" :alt="event.name" class="h-full w-full object-cover" />
        <div class="absolute inset-0 bg-linear-to-t from-black/60 to-transparent" />
        <div class="absolute bottom-0 left-0 p-5">
          <h1 class="text-2xl font-bold text-white drop-shadow sm:text-3xl">{{ event.name }}</h1>
          <p v-if="event.dateRangeLabel" class="mt-1 text-sm text-white/80">{{ event.dateRangeLabel }}</p>
        </div>
      </div>

      <!-- Breadcrumb + akcie -->
      <div class="mb-4 flex flex-wrap items-center gap-2">
        <RouterLink :to="indexRoute" class="action-btn">← Späť</RouterLink>
        <RouterLink v-if="event.permissions.update" :to="editRoute" class="action-btn">Upraviť</RouterLink>
        <button v-else-if="event.permissions.duplicate" type="button" class="action-btn" @click="duplicate">Kopírovať</button>
        <RouterLink v-if="event.permissions.viewTickets" :to="`/dashboard/events/${route.params.id}/tickets`" class="action-btn">Lístky</RouterLink>
        <RouterLink v-if="event.permissions.checkin" :to="`/dashboard/events/${route.params.id}/checkin`" class="action-btn">Check-in</RouterLink>
        <span class="ml-auto rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-wide"
          :class="statusClass(event.status)">{{ event.status }}</span>
      </div>

      <!-- Title (without hero) -->
      <div v-if="!event.imageUrl" class="mb-4">
        <h1 class="text-3xl font-bold text-slate-900">{{ event.name }}</h1>
        <p v-if="event.dateRangeLabel" class="mt-1 text-slate-500">{{ event.dateRangeLabel }}</p>
      </div>

      <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <!-- Ľavý stĺpec -->
        <div class="grid gap-4">
          <!-- Popis -->
          <div class="show-card">
            <div v-if="event.body || event.bodyAi">
              <!-- Toggle between body versions when both exist -->
              <div v-if="event.body && event.bodyAi" class="mb-3 flex gap-1 rounded-lg border border-slate-200 bg-slate-50 p-1 w-fit">
                <button type="button"
                  :class="bodyView === 'original' ? 'bg-white shadow-sm text-slate-900' : 'text-slate-500 hover:text-slate-700'"
                  class="rounded-md px-3 py-1 text-xs font-medium transition-all"
                  @click="bodyView = 'original'">Originál</button>
                <button type="button"
                  :class="bodyView === 'ai' ? 'bg-white shadow-sm text-slate-900' : 'text-slate-500 hover:text-slate-700'"
                  class="rounded-md px-3 py-1 text-xs font-medium transition-all"
                  @click="bodyView = 'ai'">
                  <span class="flex items-center gap-1">
                    <svg class="h-3 w-3 text-violet-500" viewBox="0 0 24 24" fill="currentColor"><path d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    AI verzia
                  </span>
                </button>
              </div>
              <div class="prose prose-slate max-w-none text-slate-700" v-html="renderedBody" />
            </div>
            <p v-else class="text-sm text-slate-400">Bez popisu.</p>
          </div>

          <!-- Workshopy (sub-akcie eventu) -->
          <div v-if="workshops.length" class="show-card">
            <div class="mb-3 flex items-center justify-between gap-2">
              <h2 class="text-base font-semibold text-slate-800">Workshopy ({{ workshops.length }})</h2>
              <RouterLink v-if="event.permissions.viewTickets" :to="`/dashboard/events/${route.params.id}/tickets`"
                class="text-xs text-blue-600 hover:underline">Spravovať →</RouterLink>
            </div>
            <EventWorkshops :workshops="workshops" show-inactive />
          </div>

          <!-- Galéria -->
          <div v-if="event.uploadedImages.length" class="show-card">
            <h2 class="mb-3 text-base font-semibold text-slate-800">Fotografie</h2>
            <ImageGallery fileable-type="event" :fileable-id="Number(route.params.id)" />
          </div>


          <!-- Mapa -->
          <div v-if="event.latitude && event.longitude" class="show-card overflow-hidden p-0">
            <iframe
              :src="`https://www.openstreetmap.org/export/embed.html?bbox=${+event.longitude - 0.005},${+event.latitude - 0.003},${+event.longitude + 0.005},${+event.latitude + 0.003}&layer=mapnik&marker=${event.latitude},${event.longitude}`"
              class="h-64 w-full border-0"
              loading="lazy"
            />
            <div class="px-4 py-2 text-xs text-slate-500">
              GPS: {{ event.latitude }}, {{ event.longitude }} ·
              <a :href="`https://www.google.com/maps?q=${event.latitude},${event.longitude}`"
                target="_blank" class="text-blue-600">Google Maps ↗</a>
            </div>
          </div>

          <!-- Ďalšie eventy tohto kanálu -->
          <div v-if="event.canal && relatedEvents.length" class="show-card">
            <div class="mb-3 flex items-center justify-between gap-2">
              <h2 class="text-base font-semibold text-slate-800">Ďalšie eventy — {{ event.canal.name }}</h2>
              <RouterLink :to="`${prefix}/canals/${event.canal.id}`"
                class="text-xs text-blue-600 hover:underline">Kanál →</RouterLink>
            </div>
            <ul class="grid gap-1.5">
              <li v-for="ev in relatedEvents" :key="ev.id"
                class="flex items-center gap-3 rounded-lg border border-slate-100 bg-slate-50 px-3 py-2">
                <span class="h-2 w-2 shrink-0 rounded-full"
                  :class="ev.status === 'published' ? 'bg-green-500' : ev.status === 'archived' ? 'bg-slate-400' : 'bg-amber-400'" />
                <RouterLink :to="`${prefix}/events/${ev.id}`"
                  class="flex-1 min-w-0 truncate text-sm font-medium text-slate-900 no-underline hover:text-blue-700">
                  {{ ev.name }}
                </RouterLink>
                <span v-if="ev.startAt" class="shrink-0 text-xs text-slate-500">{{ fmt(ev.startAt) }}</span>
                <div class="relative shrink-0 related-event-menu">
                  <button type="button" class="action-btn" @click.stop="openMenuId = openMenuId === ev.id ? null : ev.id">
                    ⋮
                  </button>
                  <div v-if="openMenuId === ev.id"
                    class="absolute right-0 top-full z-10 mt-1 w-32 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-lg">
                    <RouterLink :to="`${prefix}/events/${ev.id}`" class="block px-3 py-2 text-sm text-slate-700 no-underline hover:bg-slate-50"
                      @click="openMenuId = null">Zobraziť</RouterLink>
                    <RouterLink v-if="ev.status !== 'archived'" :to="`${prefix}/events/${ev.id}/edit`" class="block px-3 py-2 text-sm text-slate-700 no-underline hover:bg-slate-50"
                      @click="openMenuId = null">Upraviť</RouterLink>
                    <button v-else type="button" class="block w-full px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50"
                      @click="duplicateRelated(ev.id)">Kopírovať</button>
                  </div>
                </div>
              </li>
            </ul>
          </div>
        </div>

        <!-- Pravý stĺpec -->
        <aside class="grid gap-4 self-start">
          <!-- Termín -->
          <dl class="show-card grid gap-3">
            <div v-if="event.startAt" class="detail-card">
              <dt>Termín</dt>
              <dd><EventDateRange :start-at="event.startAt" :end-at="event.endAt" /></dd>
            </div>
            <div v-if="event.registrationDeadlineAt" class="detail-card border-l-2 border-amber-400 pl-2">
              <dt class="text-amber-700">Registrácia do</dt>
              <dd class="font-semibold text-amber-800">{{ fmtDateTime(event.registrationDeadlineAt) }}</dd>
            </div>
          </dl>

          <!-- Organizátor -->
          <dl v-if="event.canal" class="show-card grid gap-3">
            <div class="detail-card">
              <dt>Organizátor</dt>
              <dd>
                <RouterLink :to="`${prefix}/canals/${event.canal.id}`"
                  class="font-medium text-blue-700 no-underline hover:underline">
                  {{ event.canal.name }}
                </RouterLink>
              </dd>
            </div>
          </dl>

          <!-- Miesto -->
          <dl class="show-card grid gap-3">
            <div v-if="event.venue" class="detail-card">
              <dt>Miesto konania</dt>
              <dd>
                <RouterLink :to="`${prefix}/venues/${event.venue.id}`"
                  class="font-semibold text-blue-700 no-underline hover:underline">
                  {{ event.venue.name }}
                </RouterLink>
                <p v-if="event.venue.street || event.venue.postcode" class="mt-0.5 text-sm text-slate-500">
                  <span v-if="event.venue.street">{{ event.venue.street }}, </span>
                  <span v-if="event.venue.postcode">{{ event.venue.postcode }}</span>
                </p>
                <div class="mt-1 flex flex-wrap gap-2 text-sm">
                  <a v-if="event.venue.phone" :href="`tel:${event.venue.phone}`" class="text-blue-600">{{ event.venue.phone }}</a>
                  <a v-if="event.venue.website" :href="event.venue.website" target="_blank" class="truncate text-blue-600">{{ event.venue.website }}</a>
                </div>
              </dd>
            </div>
            <div v-if="event.locationName" class="detail-card">
              <dt>Popis miesta</dt>
              <dd>{{ event.locationName }}</dd>
            </div>
            <div v-if="event.street" class="detail-card">
              <dt>Adresa</dt>
              <dd>
                {{ event.street }}<br v-if="event.postcode || event.municipality" />
                <span v-if="event.postcode">{{ event.postcode }} </span>
                <span v-if="event.municipality">{{ event.municipality.name }}</span>
                <span v-if="event.country && event.country !== 'Slovakia'" class="block text-xs text-slate-500">{{ event.country }}</span>
              </dd>
            </div>
            <div v-else-if="event.municipality && !event.venue" class="detail-card">
              <dt>Obec</dt>
              <dd>{{ event.municipality.name }}</dd>
            </div>
            <div v-if="!event.locationName && !event.street && !event.municipality && !event.venue" class="text-sm text-slate-400">
              Miesto nie je zadané.
            </div>
          </dl>

          <!-- Kontakt -->
          <dl v-if="event.phone || event.website" class="show-card grid gap-3">
            <div v-if="event.phone" class="detail-card">
              <dt>Telefón</dt>
              <dd><a :href="`tel:${event.phone}`" class="text-blue-700">{{ event.phone }}</a></dd>
            </div>
            <div v-if="event.website" class="detail-card">
              <dt>Odkaz na akciu</dt>
              <dd><a :href="event.website" target="_blank" rel="noopener noreferrer" class="text-blue-700 hover:underline">Zobraziť akciu ↗</a></dd>
            </div>
          </dl>

          <!-- Poslať správu organizátorovi -->
          <div v-if="event.contactable" class="show-card">
            <ContactButton target-type="event" :target-id="event.id" :target-name="event.name" />
          </div>

          <!-- Meta -->
          <dl class="show-card grid gap-3">
            <div class="detail-card">
              <dt>Záznam</dt>
              <dd class="flex flex-wrap gap-x-4 gap-y-1 text-sm">
                <span v-if="event.publishedAt">Publikované {{ fmt(event.publishedAt) }}</span>
                <span v-if="event.createdAt">Vytvorené {{ fmt(event.createdAt) }}</span>
                <span v-if="event.updatedAt">Upravené {{ fmt(event.updatedAt) }}</span>
              </dd>
            </div>
            <div v-if="event.deletedAt" class="detail-card bg-red-50">
              <dt class="text-red-600">Zmazané</dt>
              <dd>{{ fmt(event.deletedAt) }}</dd>
            </div>
          </dl>
        </aside>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { showEvent, duplicateEvent } from '@/api/events'
import { listCanalEvents, type CanalEventItem } from '@/api/canals'
import { indexTicketTypes } from '@/api/ticketTypes'
import type { EventItem, TicketTypeItem } from '@/types'
import ImageGallery from '@/components/ImageGallery.vue'
import EventDateRange from '@/components/EventDateRange.vue'
import EventWorkshops from '@/components/EventWorkshops.vue'
import ContactButton from '@/components/ContactButton.vue'
import { useToast } from '@/composables/useToast'

const props = defineProps<{ scope?: 'dashboard' | 'admin' }>()
const route = useRoute()
const router = useRouter()
const toast = useToast()

const scope = computed(() => props.scope ?? (route.path.startsWith('/admin') ? 'admin' : 'dashboard'))
const prefix = computed(() => scope.value === 'admin' ? '/admin' : '/dashboard')
const indexRoute = computed(() => `${prefix.value}/events`)
const editRoute = computed(() => `${prefix.value}/events/${route.params.id}/edit`)

const event = ref<EventItem | null>(null)
const loading = ref(false)
const error = ref(false)
const relatedEvents = ref<CanalEventItem[]>([])
const workshops = ref<TicketTypeItem[]>([])
const bodyView = ref<'original' | 'ai'>('ai')
const openMenuId = ref<number | null>(null)

function onDocClick(e: MouseEvent) {
  if (!(e.target as HTMLElement).closest('.related-event-menu')) openMenuId.value = null
}

async function duplicate() {
  if (!event.value) return
  try {
    const copy = await duplicateEvent(event.value.id, scope.value)
    toast.success('Vytvorená kópia. Doplňte nový termín.')
    router.push(`${prefix.value}/events/${copy.id}/edit`)
  } catch { toast.error('Kopírovanie zlyhalo.') }
}

async function duplicateRelated(id: number) {
  openMenuId.value = null
  try {
    const copy = await duplicateEvent(id, scope.value)
    toast.success('Vytvorená kópia. Doplňte nový termín.')
    router.push(`${prefix.value}/events/${copy.id}/edit`)
  } catch { toast.error('Kopírovanie zlyhalo.') }
}

onMounted(() => document.addEventListener('mousedown', onDocClick))
onUnmounted(() => document.removeEventListener('mousedown', onDocClick))

function withBlankLinks(html: string): string {
  const doc = new DOMParser().parseFromString(html, 'text/html')
  doc.querySelectorAll('a').forEach(a => {
    a.setAttribute('target', '_blank')
    a.setAttribute('rel', 'noopener noreferrer')
  })
  return doc.body.innerHTML
}

const renderedBody = computed(() => {
  const html = bodyView.value === 'ai' && event.value?.bodyAi ? event.value.bodyAi : event.value?.body
  return html ? withBlankLinks(html) : ''
})

const DAY_NAMES: Record<number, string> = {
  0: 'Nedeľa', 1: 'Pondelok', 2: 'Utorok', 3: 'Streda',
  4: 'Štvrtok', 5: 'Piatok', 6: 'Sobota',
}

function dayName(d: string) {
  return DAY_NAMES[new Date(d).getDay()] ?? ''
}

function fmt(d: string | null) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('sk-SK', { day: 'numeric', month: 'numeric', year: 'numeric' })
}

function fmtDateTime(d: string) {
  const date = new Date(d)
  return date.toLocaleString('sk-SK', { day: 'numeric', month: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

function statusClass(status: string) {
  return {
    published: 'bg-green-100 text-green-800',
    draft: 'bg-amber-100 text-amber-800',
    archived: 'bg-slate-100 text-slate-600',
    scheduled: 'bg-blue-100 text-blue-800',
    pending_review: 'bg-purple-100 text-purple-800',
    rejected: 'bg-red-100 text-red-800',
  }[status] ?? 'bg-slate-100 text-slate-600'
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

onMounted(async () => {
  const id = Number(route.params.id)
  loading.value = true
  try {
    event.value = await showEvent(scope.value, id)
    document.title = event.value.name

    if (event.value.permissions.viewTickets) {
      try {
        const types = await indexTicketTypes(id)
        workshops.value = types.filter(t => t.kind === 'workshop')
      } catch { /* non-fatal */ }
    }

    if (event.value.canal?.id) {
      try {
        const all = await listCanalEvents(scope.value, event.value.canal.id)
        relatedEvents.value = all.filter(e => e.id !== id).slice(0, 10)
      } catch { /* non-fatal */ }
    }
  } catch {
    error.value = true
  } finally {
    loading.value = false
  }
})
</script>
