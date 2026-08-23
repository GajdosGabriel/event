<template>
  <div class="mx-auto my-5 w-full max-w-[1320px] px-4">
    <p v-if="loading" class="text-slate-600">{{ t('common.loading') }}</p>
    <div v-else-if="error" class="show-not-found">
      <h1>{{ t('venues.show.notFound') }}</h1>
      <RouterLink :to="indexRoute">{{ t('common.back') }}</RouterLink>
    </div>

    <template v-else-if="venue">
      <!-- Breadcrumb + akcie. Úpravy, publikovanie aj mazanie sedia v tom istom
           menu ako vo výpise — jedno ovládanie, jedny práva. -->
      <div class="mb-4 flex flex-wrap items-center gap-2">
        <RouterLink :to="indexRoute" class="action-btn">{{ t('common.back') }}</RouterLink>
        <ResourceActionsMenu
          class="ml-auto"
          resource="venue"
          :scope="scope"
          :item="venue"
          :show-view="false"
          @changed="reload"
          @removed="router.push(indexRoute)"
        />
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

          <!-- Poloha -->
          <div class="show-card overflow-hidden p-0">
            <div class="flex flex-wrap items-baseline gap-2 px-4 pt-4">
              <h2 class="text-base font-semibold text-slate-800">{{ t('address.map') }}</h2>
              <!-- Značka môže sedieť na budove aj len na strede obce — bez
                   štítku sa to na mape nedá rozoznať. -->
              <span v-if="coordinatesLabel" class="text-xs" :class="coordinatesApproximate ? 'font-semibold text-amber-700' : 'text-slate-500'">
                {{ coordinatesLabel }}
              </span>
            </div>
            <p v-if="!hasCoordinates" class="px-4 pb-4 pt-2 text-sm text-slate-400">
              {{ t('venues.show.coordinatesMissing') }}
            </p>
            <template v-else-if="venue.latitude != null && venue.longitude != null">
              <iframe
                :src="`https://www.openstreetmap.org/export/embed.html?bbox=${venue.longitude - 0.005},${venue.latitude - 0.003},${venue.longitude + 0.005},${venue.latitude + 0.003}&layer=mapnik&marker=${venue.latitude},${venue.longitude}`"
                class="mt-3 h-72 w-full border-0"
                loading="lazy"
              />
              <div class="px-4 py-2 text-xs text-slate-500">
                GPS: {{ venue.latitude }}, {{ venue.longitude }} ·
                <a :href="`https://www.google.com/maps?q=${venue.latitude},${venue.longitude}`" target="_blank" class="text-blue-600">{{ t('common.googleMaps') }}</a>
              </div>
            </template>
          </div>

          <!-- Galéria -->
          <div v-if="files.length" class="show-card">
            <h2 class="mb-3 text-base font-semibold text-slate-800">{{ t('common.gallery') }}</h2>
            <div class="grid grid-cols-3 gap-2 sm:grid-cols-4">
              <a v-for="f in files" :key="f.id" :href="f.url" target="_blank"
                class="block aspect-square overflow-hidden rounded-lg border border-slate-200">
                <img :src="f.thumbUrl ?? f.url" :alt="f.name" class="h-full w-full object-cover hover:scale-105 transition-transform" />
              </a>
            </div>
          </div>

          <!-- Otváracie hodiny -->
          <div v-if="openingHoursRows.length" class="show-card">
            <h2 class="mb-3 text-base font-semibold text-slate-800">{{ t('common.openingHours') }}</h2>
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
              <h2 class="text-base font-semibold text-slate-800">{{ t('venues.show.events') }}</h2>
              <RouterLink :to="`${prefix}/events`" class="text-xs text-blue-600 hover:underline">{{ t('events.index.all') }}</RouterLink>
            </div>
            <p v-if="eventsLoading" class="text-sm text-slate-500">{{ t('common.loading') }}</p>
            <p v-else-if="!events.length" class="text-sm text-slate-400">{{ t('events.index.empty') }}</p>
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
                <div class="shrink-0">
                  <RowActions>
                    <RouterLink :to="`/events/`" class="row-menu-item">{{ t('common.view') }}</RouterLink>
                    <RouterLink v-if="ev.status !== 'archived'" :to="`/events//edit`" class="row-menu-item">{{ t('common.edit') }}</RouterLink>
                  </RowActions>
                </div>
              </li>
            </ul>
          </div>
        </div>

        <!-- Pravý stĺpec -->
        <aside class="grid gap-4 self-start">
          <dl class="show-card grid gap-3">
            <!-- Adresa -->
            <div v-if="venue.street || venue.municipality" class="detail-card">
              <dt>{{ t('common.address') }}</dt>
              <dd>
                <span v-if="venue.street">{{ venue.street }}<br/></span>
                <span v-if="venue.postcode">{{ venue.postcode }} </span>
                <span v-if="venue.municipality">{{ venue.municipality.name }}</span>
                <span v-if="venue.country && venue.country !== 'Slovakia'" class="block text-slate-500 text-xs">{{ venue.country }}</span>
              </dd>
            </div>

            <!-- Kontakt -->
            <div v-if="venue.phone" class="detail-card">
              <dt>{{ t('common.phone') }}</dt>
              <dd><a :href="`tel:${venue.phone}`" class="text-blue-700">{{ venue.phone }}</a></dd>
            </div>
            <div v-if="venue.website" class="detail-card">
              <dt>{{ t('common.website') }}</dt>
              <dd><a :href="venue.website" target="_blank" class="break-all text-blue-700">{{ venue.website }}</a></dd>
            </div>

            <!-- Kapacita -->
            <div v-if="venue.capacity" class="detail-card">
              <dt>{{ t('common.capacity') }}</dt>
              <dd>{{ t('common.capacityPeople', { n: venue.capacity }) }}</dd>
            </div>

            <!-- Kanály -->
            <div v-if="venue.canalsList.length" class="detail-card">
              <dt>{{ t('canals.index.title') }}</dt>
              <dd class="grid gap-1 mt-1">
                <RouterLink
                  v-for="c in venue.canalsList" :key="c.id"
                  :to="`${prefix}/canals/${c.id}`"
                  class="flex items-center gap-1.5 text-sm text-blue-700 no-underline hover:underline">
                  <span v-if="c.isOwner" class="text-xs text-teal-600 font-semibold">{{ t('common.owner') }}</span>
                  {{ c.name }}
                </RouterLink>
              </dd>
            </div>

            <!-- Meta -->
            <div class="detail-card">
              <dt>{{ t('venues.fields.status') }}</dt>
              <dd>
                <span class="inline-block rounded-full px-2 py-0.5 text-xs font-semibold uppercase tracking-wide"
                  :class="statusClass(venue.status)">{{ statusLabel('venues', venue.status) }}</span>
              </dd>
            </div>
            <div class="detail-card">
              <dt>{{ t('common.createdAt') }}</dt>
              <dd>{{ formatDate(venue.createdAt) }}</dd>
            </div>
            <div class="detail-card">
              <dt>{{ t('common.updatedAt') }}</dt>
              <dd>{{ formatDate(venue.updatedAt) }}</dd>
            </div>
            <div v-if="venue.deletedAt" class="detail-card bg-red-50">
              <dt class="text-red-600">{{ t('common.deletedAt') }}</dt>
              <dd>{{ formatDate(venue.deletedAt) }}</dd>
            </div>
          </dl>

          <div v-if="venue.contactable" class="mt-4">
            <ContactButton target-type="venue" :target-id="venue.id" :target-name="venue.name" />
          </div>
        </aside>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { showVenue, listVenueEvents, type VenueEventItem } from '@/api/venues'
import { listFiles, type FileItem } from '@/api/files'
import ResourceActionsMenu from '@/components/ResourceActionsMenu.vue'
import RowActions from '@/components/RowActions.vue'
import ContactButton from '@/components/ContactButton.vue'
import { fmtDate, weekdayLabel } from '@/utils/dateFormat'
import { useI18n } from '@/i18n'
import type { VenueItem } from '@/types'
import { statusLabel } from '@/utils/statusLabel'

const props = defineProps<{ scope?: 'dashboard' | 'admin' }>()
const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const scope = computed(() => props.scope ?? (route.path.startsWith('/admin') ? 'admin' : 'dashboard'))
const prefix = computed(() => scope.value === 'admin' ? '/admin' : '/dashboard')
const indexRoute = computed(() => `${prefix.value}/venues`)

const venue = ref<VenueItem | null>(null)
const loading = ref(false)
const error = ref(false)
const files = ref<FileItem[]>([])
const events = ref<VenueEventItem[]>([])
const eventsLoading = ref(false)

const hasCoordinates = computed(() => venue.value?.latitude != null && venue.value?.longitude != null)

const coordinatesApproximate = computed(
  () => venue.value?.coordinatesSource === 'municipality' || venue.value?.coordinatesSource === 'ai',
)

const coordinatesLabel = computed(() => {
  if (!hasCoordinates.value) return t('address.coordinates.missing')
  switch (venue.value?.coordinatesSource) {
    case 'venue': return t('address.coordinates.venue')
    case 'address': return t('address.coordinates.address')
    case 'ai': return t('address.coordinates.ai')
    case 'municipality': return t('address.coordinates.municipality')
    case 'manual': return t('address.coordinates.manual')
    // Miesta uložené pred zavedením presnosti zdroj nemajú.
    default: return ''
  }
})

const openingHoursRows = computed(() => {
  const oh = venue.value?.openingHours
  if (!oh || typeof oh !== 'object') return []
  return Object.entries(oh as Record<string, string>)
    .map(([day, hours]) => ({ day: weekdayLabel(day), hours }))
    .filter(r => r.hours)
})

function statusClass(status: string) {
  return {
    published: 'bg-green-100 text-green-800',
    draft: 'bg-amber-100 text-amber-800',
    archived: 'bg-slate-100 text-slate-600',
    blocked: 'bg-red-100 text-red-800',
  }[status] ?? 'bg-slate-100 text-slate-600'
}

function formatDate(d: string | null) {
  return d ? fmtDate(d) : t('common.none')
}

/** Po akcii z menu (publikovanie, obnova) — stav aj práva prídu nanovo. */
async function reload() {
  try {
    venue.value = await showVenue(scope.value, Number(route.params.id))
  } catch {
    error.value = true
  }
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
