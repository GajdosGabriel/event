<template>
  <!-- Verejný výpis podujatí. Používa ho homepage aj každá landing stránka
       (`/podujatia`, `/podujatia/mesto/...`, `/podujatia/tema/...`), aby sa
       filtrovanie, stránkovanie a fasety nemuseli udržiavať dvakrát. -->
  <div>
    <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
      <div>
        <h1 class="mb-1 text-2xl text-slate-900">{{ heading }}</h1>
        <p class="text-slate-500">{{ subheading }}</p>
      </div>

      <div class="flex items-center gap-2">
        <!-- Rozbaľovacie vyhľadávanie podľa názvu -->
        <div class="flex items-center overflow-hidden rounded-lg border border-slate-200 bg-white">
          <button
            type="button"
            class="flex h-9 w-9 shrink-0 items-center justify-center text-slate-500 transition-colors hover:text-slate-800"
            :title="searchOpen ? 'Zavrieť vyhľadávanie' : 'Hľadať'"
            @click="toggleSearch"
          >
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <circle cx="11" cy="11" r="7" />
              <path d="M21 21l-4.3-4.3" stroke-linecap="round" />
            </svg>
          </button>
          <input
            ref="searchInput"
            v-model="search"
            type="search"
            placeholder="Hľadať podľa názvu…"
            class="h-9 border-0 bg-transparent py-0 pr-3 text-sm text-slate-800 outline-none transition-all duration-200"
            :class="searchOpen ? 'w-48 opacity-100' : 'w-0 opacity-0'"
            @input="onSearchInput"
            @keydown.esc="closeSearch"
          />
        </div>

        <div class="inline-flex overflow-hidden rounded-lg border border-slate-200 bg-white text-sm">
          <button
            type="button"
            class="px-3 py-1.5 transition-colors"
            :class="view === 'agenda' ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-50'"
            @click="setView('agenda')"
          >Agenda</button>
          <button
            type="button"
            class="px-3 py-1.5 transition-colors"
            :class="view === 'grid' ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-50'"
            @click="setView('grid')"
          >Mriežka</button>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 items-start gap-4 xl:grid-cols-[minmax(0,1fr)_320px]">
      <div class="min-w-0 space-y-4">
        <OngoingEventsStrip v-if="!search.trim() && !range" :municipality="municipalityFilter" />
        <TagChips />
        <div>
          <p v-if="loading" class="text-slate-600">Načítavam…</p>
          <p v-else-if="error" class="text-red-600">{{ error }}</p>
          <p v-else-if="events.length === 0" class="rounded-xl border border-slate-200 bg-white p-3 text-slate-500">Žiadne eventy.</p>
          <template v-else>
            <!-- Počet výsledkov: na landing stránke je to prvá informácia,
                 ktorú človek aj vyhľadávač hľadá („koľko toho tu je"). -->
            <p class="mb-2 text-sm text-slate-500">{{ resultLabel }}</p>
            <EventAgenda v-if="view === 'agenda'" :events="events" />
            <div v-else class="grid grid-cols-1 gap-3 rounded-xl border border-slate-200 bg-white p-3 sm:grid-cols-2 md:grid-cols-3">
              <EventCard
                v-for="event in events"
                :key="event.id"
                :id="event.id"
                :slug="event.slug"
                :name="event.name"
                :image-url="event.imageUrl"
                :image-url-large="event.imageUrlLarge"
                :date-label="event.dateRangeLabel"
                :canal-name="event.canalName"
                :venue-name="event.venue?.name ?? null"
                :tags="event.tags"
              />
            </div>
          </template>
          <AppPaginator :current-page="page" :last-page="lastPage" @change="loadPage" />
        </div>
      </div>

      <aside>
        <MunicipalityAside scope="public" resource="events" />
      </aside>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, nextTick, onMounted, onBeforeUnmount, watch } from 'vue'
import { useRoute } from 'vue-router'
import { indexEvents } from '@/api/events'
import type { EventItem } from '@/types'
import EventCard from '@/components/EventCard.vue'
import EventAgenda from '@/components/EventAgenda.vue'
import OngoingEventsStrip from '@/components/OngoingEventsStrip.vue'
import AppPaginator from '@/components/AppPaginator.vue'
import MunicipalityAside from '@/components/MunicipalityAside.vue'
import TagChips from '@/components/TagChips.vue'
import { useSettings, type PublicEventsView } from '@/composables/useSettings'

const props = withDefaults(defineProps<{
  heading: string
  subheading: string
  /** Obec z cesty (`/podujatia/mesto/{slug}`) — má prednosť pred `?municipality=`. */
  municipality?: string | number | null
  /** Štítok z cesty (`/podujatia/tema/{slug}`) — má prednosť pred `?tags=`. */
  tags?: string | null
  /** Pomenované časové okno; dnes jediné `weekend`. */
  range?: string | null
}>(), {
  municipality: null,
  tags: null,
  range: null,
})

const route = useRoute()
const { settings, save } = useSettings()

const view = computed(() => settings.value.publicEventsView)

/**
 * Filtre z cesty prebíjajú query — na landing stránke je obec/štítok súčasťou
 * adresy a nemá ju prepísať parameter, ktorý tam ostal z predchádzajúcej
 * navigácie.
 */
const municipalityParam = computed(() => props.municipality ?? route.query.municipality ?? null)
const tagsParam = computed(() => props.tags ?? route.query.tags ?? null)

/** OngoingEventsStrip berie id, nie slug — na landing obce ho preto nemá. */
const municipalityFilter = computed(() => {
  const value = municipalityParam.value
  return value !== null && /^\d+$/.test(String(value)) ? Number(value) : null
})

function setView(next: PublicEventsView) {
  settings.value.publicEventsView = next
  save()
}

const events = ref<EventItem[]>([])
const loading = ref(false)
const error = ref<string | null>(null)
const page = ref(1)
const lastPage = ref(1)
const total = ref(0)

const search = ref('')
const searchOpen = ref(false)
const searchInput = ref<HTMLInputElement | null>(null)
let searchTimer: ReturnType<typeof setTimeout> | undefined

const resultLabel = computed(() => {
  const count = total.value || events.value.length
  if (count === 1) return '1 podujatie'
  if (count >= 2 && count <= 4) return `${count} podujatia`
  return `${count} podujatí`
})

function toggleSearch() {
  searchOpen.value = !searchOpen.value
  if (searchOpen.value) {
    nextTick(() => searchInput.value?.focus())
  } else {
    closeSearch()
  }
}

function closeSearch() {
  searchOpen.value = false
  if (search.value) {
    search.value = ''
    loadPage(1)
  }
}

function onSearchInput() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => loadPage(1), 400)
}

async function loadPage(p = 1) {
  loading.value = true
  error.value = null
  try {
    const params: Record<string, unknown> = { page: p, per_page: settings.value.publicEventsPerPage }
    params['list'] = search.value.trim() ? 'all' : 'upcoming'
    if (municipalityParam.value) params['municipality'] = municipalityParam.value
    if (tagsParam.value) params['tags'] = tagsParam.value
    if (props.range) params['range'] = props.range
    if (search.value.trim()) params['search'] = search.value.trim()
    const res = await indexEvents('public', params)
    events.value = res.data
    page.value = res.meta.current_page
    lastPage.value = res.meta.last_page
    total.value = res.meta.total ?? res.data.length
  } catch {
    error.value = 'Nepodarilo sa načítať eventy.'
  } finally {
    loading.value = false
  }
}

watch(() => [municipalityParam.value, tagsParam.value, props.range], () => loadPage(1))
onMounted(() => loadPage())
onBeforeUnmount(() => clearTimeout(searchTimer))
</script>
