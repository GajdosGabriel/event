<template>
  <div class="mx-auto w-full max-w-[1320px] px-4 pt-6 pb-8">
    <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
      <div>
        <h1 class="mb-1 text-2xl text-slate-900">Eventy</h1>
        <p class="text-slate-500">Prehľad verejných podujatí.</p>
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
        <OngoingEventsStrip v-if="!search.trim()" :municipality="municipalityFilter" />
        <div>
        <p v-if="loading" class="text-slate-600">Načítavam…</p>
        <p v-else-if="error" class="text-red-600">{{ error }}</p>
        <p v-else-if="events.length === 0" class="rounded-xl border border-slate-200 bg-white p-3 text-slate-500">Žiadne eventy.</p>
        <EventAgenda v-else-if="view === 'agenda'" :events="events" />
        <div v-else class="grid grid-cols-1 gap-3 rounded-xl border border-slate-200 bg-white p-3 md:grid-cols-3">
          <EventCard v-for="event in events" :key="event.id" :event="event" />
        </div>
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
import { useSettings, type PublicEventsView } from '@/composables/useSettings'

const route = useRoute()
const { settings, save } = useSettings()

const view = computed(() => settings.value.publicEventsView)
const municipalityFilter = computed(() => route.query.municipality ? Number(route.query.municipality) : null)

function setView(next: PublicEventsView) {
  settings.value.publicEventsView = next
  save()
}
const events = ref<EventItem[]>([])
const loading = ref(false)
const error = ref<string | null>(null)
const page = ref(1)
const lastPage = ref(1)

const search = ref('')
const searchOpen = ref(false)
const searchInput = ref<HTMLInputElement | null>(null)
let searchTimer: ReturnType<typeof setTimeout> | undefined

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
    if (route.query.municipality) params['municipality'] = route.query.municipality
    if (search.value.trim()) params['search'] = search.value.trim()
    const res = await indexEvents('public', params)
    events.value = res.data
    page.value = res.meta.current_page
    lastPage.value = res.meta.last_page
  } catch {
    error.value = 'Nepodarilo sa načítať eventy.'
  } finally {
    loading.value = false
  }
}

watch(() => route.query.municipality, () => loadPage(1))
onMounted(() => loadPage())
onBeforeUnmount(() => clearTimeout(searchTimer))
</script>
