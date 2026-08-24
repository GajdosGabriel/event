<template>
  <!-- Verejný výpis podujatí. Používa ho homepage aj každá landing stránka
       (`/podujatia`, `/podujatia/mesto/...`, `/podujatia/tema/...`), aby sa
       filtrovanie, stránkovanie a fasety nemuseli udržiavať dvakrát. -->
  <div>
    <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
      <div class="min-w-0">
        <component :is="headingLevel" class="mb-1 text-2xl text-slate-900">{{ heading }}</component>
        <p class="text-slate-500">{{ subheading }}</p>
      </div>

      <div class="flex w-full flex-wrap items-center justify-end gap-2 sm:w-auto">
        <!-- Zbalené hľadanie je len ikona, aby sa na telefóne zmestilo do
             jedného riadku s prepínačom pohľadu; po kliknutí sa roztiahne na
             plnú šírku. Stav zostáva v adrese (`?q=`) — výsledok sa dá poslať
             aj založiť a tlačidlo „späť" ho nezahodí. -->
        <div class="relative h-9 min-w-0" :class="searchOpen ? 'flex-1 sm:w-64 sm:flex-none' : 'w-9'">
          <button
            v-if="!searchOpen"
            type="button"
            :aria-label="t('filters.events.searchLabel')"
            :aria-expanded="false"
            aria-controls="event-search"
            class="flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 transition-colors hover:bg-slate-50 hover:text-slate-700"
            @click="openSearch"
          >
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
              <circle cx="11" cy="11" r="7" />
              <path d="M21 21l-4.3-4.3" stroke-linecap="round" />
            </svg>
          </button>

          <template v-else>
            <label for="event-search" class="sr-only">{{ t('filters.events.searchLabel') }}</label>
            <svg
              class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
              fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"
            >
              <circle cx="11" cy="11" r="7" />
              <path d="M21 21l-4.3-4.3" stroke-linecap="round" />
            </svg>
            <input
              id="event-search"
              ref="searchInput"
              v-model="search"
              type="search"
              :placeholder="t('filters.events.search')"
              autocomplete="off"
              class="h-9 w-full rounded-lg border border-slate-200 bg-white pl-9 pr-8 text-sm text-slate-800 outline-none transition-colors focus:border-blue-500"
              @input="onSearchInput"
              @keydown.esc="onSearchEscape"
              @blur="collapseSearch"
            />
            <button
              v-if="search"
              type="button"
              :aria-label="t('filters.events.clearSearch')"
              class="absolute right-2 top-1/2 flex h-5 w-5 -translate-y-1/2 items-center justify-center rounded-full text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-700"
              @click="clearSearch"
            >
              <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" />
              </svg>
            </button>
          </template>
        </div>

        <!-- Prepínač zobrazenia je ten istý prvok ako karty v dashboarde,
             takže aj rovnako vyzerá — `.nav-tab.active` v styles.css. -->
        <div class="nav-tabs" role="group" :aria-label="t('public.list.viewLabel')">
          <button
            type="button"
            class="nav-tab"
            :class="{ active: view === 'agenda' }"
            :aria-pressed="view === 'agenda'"
            @click="setView('agenda')"
          >{{ t('public.list.agenda') }}</button>
          <button
            type="button"
            class="nav-tab"
            :class="{ active: view === 'grid' }"
            :aria-pressed="view === 'grid'"
            @click="setView('grid')"
          >{{ t('public.list.grid') }}</button>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 items-start gap-4 xl:grid-cols-[minmax(0,1fr)_320px]">
      <div ref="listTop" class="min-w-0 scroll-mt-4 space-y-4">
        <OngoingEventsStrip v-if="!search.trim() && !range && !list" :municipality="municipalityFilter" />

        <!-- Časové okná mali vlastné adresy, ale zo zoznamu na ne nič neviedlo —
             „tento víkend" sa dalo nájsť len tak, že o ňom človek už vedel. -->
        <nav :aria-label="t('filters.events.quick')" class="flex flex-wrap gap-1.5">
          <RouterLink
            v-for="shortcut in shortcuts"
            :key="shortcut.to"
            :to="shortcut.to"
            class="chip"
            :class="{ active: shortcut.active }"
            :aria-current="shortcut.active ? 'page' : undefined"
          >
            <span aria-hidden="true">{{ shortcut.emoji }}</span>
            {{ shortcut.label }}
          </RouterLink>
        </nav>

        <TagChips :count="loading ? null : resultLabel" />

        <div>
          <!-- Kostra v tvare výsledku: text „Načítavam…" nechal plochu prázdnu
               a po dobehnutí obsah skočil o celú výšku zoznamu. -->
          <div v-if="loading" class="animate-pulse space-y-2" aria-hidden="true">
            <div v-if="view === 'grid'" class="grid grid-cols-1 gap-3 rounded-xl border border-slate-200 bg-white p-3 sm:grid-cols-2 md:grid-cols-3">
              <div v-for="n in 6" :key="n" class="overflow-hidden rounded-lg border border-slate-200">
                <div class="h-40 w-full bg-slate-200" />
                <div class="space-y-2 p-3">
                  <div class="h-4 w-4/5 rounded bg-slate-200" />
                  <div class="h-3 w-1/2 rounded bg-slate-100" />
                </div>
              </div>
            </div>
            <div v-else class="overflow-hidden rounded-xl border border-slate-200 bg-white">
              <div v-for="n in 5" :key="n" class="flex gap-4 border-b border-dotted border-slate-200 px-4 py-3 last:border-b-0">
                <div class="h-24 w-24 shrink-0 rounded-lg bg-slate-200 sm:h-28 sm:w-28" />
                <div class="min-w-0 flex-1 space-y-2 py-1">
                  <div class="h-4 w-3/5 rounded bg-slate-200" />
                  <div class="h-3 w-2/5 rounded bg-slate-100" />
                  <div class="h-3 w-4/5 rounded bg-slate-100" />
                </div>
              </div>
            </div>
          </div>

          <div v-else-if="error" class="rounded-xl border border-red-200 bg-red-50 p-4">
            <p class="mb-2 text-sm font-medium text-red-700">{{ error }}</p>
            <button
              type="button"
              class="rounded-lg bg-red-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-red-700"
              @click="loadPage(page)"
            >{{ t('common.retry') }}</button>
          </div>

          <!-- Prázdny stav bez východiska bol slepá ulička; teraz vždy ponúka
               krok späť k širšiemu výberu. -->
          <div v-else-if="events.length === 0" class="rounded-xl border border-slate-200 bg-white p-8 text-center">
            <svg class="mx-auto mb-3 h-10 w-10 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
              <rect x="3" y="4" width="18" height="18" rx="2" />
              <path stroke-linecap="round" d="M16 2v4M8 2v4M3 10h18M9 15h6" />
            </svg>
            <p class="mb-1 font-medium text-slate-700">
              {{ search.trim() ? t('public.list.emptySearch', { query: search.trim() }) : t('public.list.empty') }}
            </p>
            <p class="mb-4 text-sm text-slate-500">
              {{ hasActiveFilters ? t('public.list.emptyFiltered') : t('public.list.emptyHint') }}
            </p>
            <div class="flex flex-wrap justify-center gap-2">
              <button
                v-if="search.trim()"
                type="button"
                class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
                @click="clearSearch"
              >{{ t('public.list.clearSearch') }}</button>
              <RouterLink
                v-if="hasRouteFilters"
                :to="PUBLIC_EVENTS"
                class="rounded-lg bg-blue-600 px-3 py-1.5 text-sm font-semibold text-white no-underline hover:bg-blue-700"
              >{{ t('public.list.allEvents') }}</RouterLink>
            </div>
          </div>

          <template v-else>
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

          <AppPaginator :current-page="page" :last-page="lastPage" @change="goToPage" />
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
import { useHead } from '@vueuse/head'
import { indexEvents } from '@/api/events'
import type { EventItem } from '@/types'
import EventCard from '@/components/EventCard.vue'
import EventAgenda from '@/components/EventAgenda.vue'
import OngoingEventsStrip from '@/components/OngoingEventsStrip.vue'
import AppPaginator from '@/components/AppPaginator.vue'
import MunicipalityAside from '@/components/MunicipalityAside.vue'
import TagChips from '@/components/TagChips.vue'
import { useSettings, type PublicEventsView } from '@/composables/useSettings'
import { usePageQuery } from '@/composables/usePageQuery'
import { absoluteUrl, publicArchivePath, publicEventPath, publicWeekendPath, PUBLIC_EVENTS } from '@/utils/publicUrl'
import { useI18n } from '@/i18n'

const props = withDefaults(defineProps<{
  heading: string
  subheading: string
  /** Obec z cesty (`/podujatia/mesto/{slug}`) — má prednosť pred `?municipality=`. */
  municipality?: string | number | null
  /** Štítok z cesty (`/podujatia/tema/{slug}`) — má prednosť pred `?tags=`. */
  tags?: string | null
  /** Pomenované časové okno; dnes jediné `weekend`. */
  range?: string | null
  /**
   * Ktoré okno zoznam pýta od API. Dnes len `past` pre archív — ostatné
   * výpisy nechávajú predvolené `upcoming`.
   */
  list?: string | null
  /**
   * Úroveň nadpisu. Homepage má vlastné `h1` nad hero sekciou, tam je zoznam
   * až druhou úrovňou — dve `h1` na stránke by rozbili osnovu dokumentu.
   */
  headingLevel?: 'h1' | 'h2'
}>(), {
  municipality: null,
  tags: null,
  range: null,
  list: null,
  headingLevel: 'h1',
})

const route = useRoute()
const { settings, save } = useSettings()
const { t, plural } = useI18n()

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
const listTop = ref<HTMLElement | null>(null)

const searchInput = ref<HTMLInputElement | null>(null)
/** Hľadaný výraz žije v adrese (`?q=`), aby sa dal zdieľať a prežil „späť". */
const search = ref(typeof route.query.q === 'string' ? route.query.q : '')
/** Zbalené na ikonu; s výrazom v adrese sa otvára rovno rozbalené. */
const searchOpen = ref(Boolean(search.value))
let searchTimer: ReturnType<typeof setTimeout> | undefined

/** Stránkovanie drží v adrese `?page=` — bez neho „späť" vracalo na prvú stranu. */
const { pageFromQuery, load: loadPage, goToPage: pushPage, replaceQuery } = usePageQuery(fetchPage)

const shortcuts = computed(() => [
  {
    label: t('filters.events.all'),
    emoji: '📅',
    to: PUBLIC_EVENTS,
    active: !props.range && !props.list && !props.tags && !props.municipality,
  },
  {
    label: t('filters.events.weekend'),
    emoji: '🎉',
    to: publicWeekendPath(),
    active: props.range === 'weekend',
  },
  // Archív. Odkaz je vo výpise zámerne: je to jediná cesta na detaily
  // skončených podujatí — pre človeka, ktorý hľadá „čo tu bolo vlani",
  // aj pre crawlera, ktorému by inak tie stránky ostali nedostupné.
  {
    label: t('filters.events.archive'),
    emoji: '🗄️',
    to: publicArchivePath(),
    active: props.list === 'past',
  },
])

/** Filtre zapísané v adrese — tie sa nedajú zrušiť tlačidlom, len odkazom. */
const hasRouteFilters = computed(() => Boolean(props.range || props.tags || props.municipality
  || route.query.municipality || route.query.tags))
const hasActiveFilters = computed(() => hasRouteFilters.value || Boolean(search.value.trim()))

const resultLabel = computed(() => plural('public.list.counts.events', total.value || events.value.length))

/**
 * Zoznam ako `ItemList` — vyhľadávaču povie, že stránka nesie usporiadaný
 * výber podujatí a v akom poradí. Jednotlivé detaily majú vlastný `Event`.
 */
useHead(computed(() => {
  if (!events.value.length) return {}
  const itemList = {
    '@context': 'https://schema.org',
    '@type': 'ItemList',
    name: props.heading,
    numberOfItems: events.value.length,
    itemListElement: events.value.map((event, idx) => ({
      '@type': 'ListItem',
      position: idx + 1,
      url: absoluteUrl(publicEventPath(event)),
      name: event.name,
    })),
  }
  return {
    script: [{ key: 'event-list-jsonld', type: 'application/ld+json', innerHTML: JSON.stringify(itemList) }],
  }
}))

function openSearch() {
  searchOpen.value = true
  // Až po prekreslení — pole ešte v DOM nie je, keď sa tlačidlo klikne.
  void nextTick(() => searchInput.value?.focus())
}

/** Prázdne pole sa po opustení zbalí; s výrazom by sa stratil kontext filtra. */
function collapseSearch() {
  if (!search.value.trim()) searchOpen.value = false
}

function clearSearch() {
  if (search.value) {
    search.value = ''
    clearTimeout(searchTimer)
    void loadPage(1)
  }
  searchInput.value?.focus()
}

/** Esc najprv zruší výraz, druhé stlačenie zbalí pole späť na ikonu. */
function onSearchEscape() {
  if (search.value) {
    clearSearch()
    return
  }
  searchOpen.value = false
}

function onSearchInput() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => void loadPage(1), 400)
}

/** Po prestránkovaní sa vracia pohľad na začiatok zoznamu, nie doprostred. */
function goToPage(p: number) {
  pushPage(p)
  listTop.value?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

/**
 * Hľadanie aj strana žijú v adrese, aby sa dali zdieľať a prežili „späť".
 * Zapisujú sa až tu, pri načítaní — jedno miesto, kde adresa dobehne zoznam.
 */
function syncQuery(p: number) {
  const query = { ...route.query }
  const value = search.value.trim()
  if (value) query['q'] = value
  else delete query['q']
  replaceQuery(query, p)
}

async function fetchPage(p: number) {
  syncQuery(p)
  loading.value = true
  error.value = null
  try {
    const params: Record<string, unknown> = { page: p, per_page: settings.value.publicEventsPerPage }
    // Archív ostáva archívom aj počas hľadania — inak by výraz zadaný nad
    // uplynulými podujatiami ticho preskočil na nadchádzajúce.
    params['list'] = props.list ?? (search.value.trim() ? 'all' : 'upcoming')
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
    error.value = t('public.list.loadFailed')
  } finally {
    loading.value = false
  }
}

// Iný filter je iný výsledok — tretia strana v ňom nemusí existovať.
watch(() => [municipalityParam.value, tagsParam.value, props.range], () => loadPage(1))

// Tlačidlo „späť" mení `?q=` mimo políčka — vstup aj výsledky sa musia dotiahnuť.
watch(() => route.query.q, (value) => {
  const next = typeof value === 'string' ? value : ''
  if (next === search.value) return
  search.value = next
  void loadPage(pageFromQuery())
})

onMounted(() => loadPage(pageFromQuery()))
onBeforeUnmount(() => clearTimeout(searchTimer))
</script>
