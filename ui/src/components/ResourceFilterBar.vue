<template>
  <div class="flex flex-wrap items-center gap-2">
    <!-- Search + mobile toggle -->
    <div class="flex w-full items-center gap-2 sm:w-auto">
      <!-- Search with "/" shortcut hint + history dropdown -->
      <div class="relative w-full max-w-xs">
        <input
          ref="searchInput"
          v-model="search"
          type="search"
          :placeholder="searchPlaceholder || t('filters.search')"
          class="form-input pr-8"
          autocomplete="off"
          role="combobox"
          aria-autocomplete="list"
          :aria-expanded="showHistory"
          @input="onSearchInput"
          @focus="historyOpen = true"
          @blur="onSearchBlur"
          @keydown.down.prevent="moveHistory(1)"
          @keydown.up.prevent="moveHistory(-1)"
          @keydown.enter="onSearchEnter"
          @keydown.esc="closeHistory"
        />
        <kbd
          v-if="!showHistory"
          class="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 rounded border border-slate-300 bg-slate-50 px-1.5 text-xs text-slate-400"
          :title="t('filters.searchHint')"
        >/</kbd>

        <!-- Naposledy hľadané. `mousedown.prevent` drží fokus v poli — inak by
             blur zavrel zoznam skôr, než sa klik stihne vyhodnotiť. -->
        <div
          v-if="showHistory"
          class="absolute left-0 right-0 top-full z-20 mt-1 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-lg"
        >
          <div class="flex items-center justify-between gap-2 px-3 py-1.5 text-xs text-slate-400">
            <span>{{ t('filters.history.label') }}</span>
            <button type="button" class="transition-colors hover:text-slate-600" @mousedown.prevent="clearHistory">
              {{ t('filters.history.clear') }}
            </button>
          </div>
          <ul class="max-h-64 overflow-y-auto pb-1">
            <li
              v-for="(item, i) in historySuggestions"
              :key="item"
              class="flex items-center"
              :class="i === historyIndex ? 'bg-slate-100' : ''"
              @mouseenter="historyIndex = i"
            >
              <button
                type="button"
                class="flex min-w-0 flex-1 items-center gap-2 px-3 py-1.5 text-left text-sm text-slate-700"
                @mousedown.prevent="pickHistory(item)"
              >
                <svg class="h-3.5 w-3.5 shrink-0 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l2.5 2.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span class="truncate">{{ item }}</span>
              </button>
              <button
                type="button"
                class="px-2 py-1.5 text-slate-300 transition-colors hover:text-slate-600"
                :title="t('filters.history.remove')"
                @mousedown.prevent="removeHistory(item)"
              >
                <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M18 6L6 18M6 6l12 12"/></svg>
              </button>
            </li>
          </ul>
        </div>
      </div>

      <!-- Mobile toggle for the rest of the filters -->
      <button
        type="button"
        class="flex h-10 shrink-0 items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 text-sm font-medium text-slate-700"
        :class="{ 'sm:hidden': !collapsible }"
        :aria-expanded="expanded"
        @click="expanded = !expanded"
      >
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4h18M6 12h12M10 20h4"/></svg>
        {{ t('filters.toggle') }}
        <span
          v-if="activeCount > 0"
          class="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-blue-100 px-1 text-xs font-semibold text-blue-700"
        >{{ activeCount }}</span>
      </button>
    </div>

    <!-- Collapsible filter group (visible from sm up, unless `collapsible`) -->
    <div
      class="w-full flex-wrap items-center gap-2"
      :class="[expanded ? 'flex' : 'hidden', collapsible ? '' : 'sm:flex sm:w-auto']"
    >
    <!-- Status -->
    <select v-if="statusOptions.length" v-model="status" class="form-input w-auto" @change="emitChange">
      <option value="">{{ allStatusesLabel || t('filters.allStatuses') }}</option>
      <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
    </select>

    <!-- Časové okno (podujatia) -->
    <select v-if="phaseOptions.length" v-model="phase" class="form-input w-auto" :title="t('filters.phaseTitle')" @change="emitChange">
      <option value="">{{ t('filters.allPhases') }}</option>
      <option v-for="opt in phaseOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
    </select>

    <!-- Sort (stránka ho môže vypnúť prázdnym poľom — napr. keď radí klikom v hlavičke tabuľky) -->
    <select v-if="sortChoices.length" v-model="sort" class="form-input w-auto" :title="t('filters.sortTitle')" @change="emitChange">
      <option v-for="opt in sortChoices" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
    </select>

    <!-- Extra filters injected by the host page -->
    <slot name="filters" />

    <!-- Date range (events) -->
    <template v-if="showDateRange">
      <label class="flex items-center gap-1.5 text-sm text-slate-500">
        {{ t('filters.dateFrom') }}
        <input v-model="dateFrom" type="date" class="form-input w-auto" :max="dateTo || undefined" @change="emitChange" />
      </label>
      <label class="flex items-center gap-1.5 text-sm text-slate-500">
        {{ t('filters.dateTo') }}
        <input v-model="dateTo" type="date" class="form-input w-auto" :min="dateFrom || undefined" @change="emitChange" />
      </label>
    </template>

    <!-- Active canal filter chip -->
    <button
      v-if="canalFilter"
      type="button"
      class="inline-flex items-center gap-1.5 rounded-full bg-teal-100 px-3 py-1 text-xs font-medium text-teal-800 ring-1 ring-inset ring-teal-300 transition-colors hover:bg-teal-200"
      @click="clearCanal"
    >
      {{ canalFilter.name }}
      <svg class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/></svg>
    </button>

    <!-- Reset -->
    <button
      v-if="activeCount > 0"
      type="button"
      class="inline-flex h-10 items-center gap-1.5 rounded-lg px-3 text-sm font-medium text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-700"
      @click="reset"
    >
      <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
      {{ t('filters.reset') }}
      <span class="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-blue-100 px-1 text-xs font-semibold text-blue-700">{{ activeCount }}</span>
    </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useSearchHistory } from '@/composables/useSearchHistory'
import { useI18n } from '@/i18n'

export interface FilterOption {
  value: string
  label: string
}

const props = withDefaults(defineProps<{
  statusOptions?: FilterOption[]
  /**
   * Popisok prázdnej voľby v prepínači stavu. Predvolene „Všetky stavy";
   * stránka ho prepíše tam, kde prázdna voľba nie je „všetko" — vo výpise
   * súborov znamená „bez zmazaných".
   */
  allStatusesLabel?: string
  /** Popisok v hľadacom poli; predvolene všeobecné „Hľadať…". */
  searchPlaceholder?: string
  sortOptions?: FilterOption[]
  phaseOptions?: FilterOption[]
  showDateRange?: boolean
  canalFilter?: { id: number; name: string } | null
  /** Priečinok histórie hľadania v prehliadači; prázdny kľúč históriu vypne. */
  historyKey?: string
  /**
   * Počet aktívnych filtrov, ktoré si stránka vykresľuje do slotu `filters`.
   * Bez tohto by ich počítadlo ani tlačidlo „Zrušiť filtre" nevideli.
   */
  extraActive?: number
  /**
   * Filtre sú schované pod tlačidlom aj na širokej obrazovke — vhodné tam, kde
   * je ich veľa a rozťahovali by lištu cez pol stránky.
   */
  collapsible?: boolean
}>(), {
  statusOptions: () => [],
  allStatusesLabel: '',
  searchPlaceholder: '',
  phaseOptions: () => [],
  // Bez default hodnoty: predvolené zoradenie sa skladá až v `sortChoices`,
  // inak by sa popisky preložili raz pri načítaní a prepnutie jazyka
  // by ich už nezmenilo.
  sortOptions: undefined,
  showDateRange: false,
  canalFilter: null,
  historyKey: '',
  extraActive: 0,
  collapsible: false,
})

const { t } = useI18n()

const sortChoices = computed<FilterOption[]>(() => props.sortOptions ?? [
  { value: 'newest', label: t('filters.sort.newest') },
  { value: 'oldest', label: t('filters.sort.oldest') },
  { value: 'name', label: t('filters.sort.name') },
])

const emit = defineEmits<{
  change: []
  'clear-canal': []
  /** Kliknutie na „Zrušiť filtre" — stránka si dočistí vlastné filtre zo slotu. */
  reset: []
}>()

const search = defineModel<string>('search', { default: '' })
const status = defineModel<string>('status', { default: '' })
const phase = defineModel<string>('phase', { default: '' })
const sort = defineModel<string>('sort', { default: 'newest' })
const dateFrom = defineModel<string>('dateFrom', { default: '' })
const dateTo = defineModel<string>('dateTo', { default: '' })

const searchInput = ref<HTMLInputElement | null>(null)
const expanded = ref(false)
let searchTimer: ReturnType<typeof setTimeout>

const activeCount = computed(() => {
  let n = 0
  if (search.value) n++
  if (status.value) n++
  if (phase.value) n++
  if (sort.value && sort.value !== 'newest') n++
  if (dateFrom.value) n++
  if (dateTo.value) n++
  if (props.canalFilter) n++
  return n + props.extraActive
})

// ── História hľadania ────────────────────────────────────────────────────────

const historyKey = computed(() => props.historyKey)
const { items: historyItems, add: rememberSearch, remove: forgetSearch, clear: forgetAll } =
  useSearchHistory(historyKey)

/** Fokus v poli; zoznam sa naozaj ukáže, až keď má čo ponúknuť. */
const historyOpen = ref(false)
/** Index zvýrazneného návrhu, -1 = žiadny (platí to, čo je napísané). */
const historyIndex = ref(-1)

const historySuggestions = computed(() => {
  const q = search.value.trim().toLowerCase()
  return historyItems.value.filter(item => {
    const value = item.toLowerCase()
    // Presnú zhodu neponúkame — to už používateľ napísal.
    return value !== q && (!q || value.includes(q))
  })
})

const showHistory = computed(() => historyOpen.value && historySuggestions.value.length > 0)

function closeHistory() {
  historyOpen.value = false
  historyIndex.value = -1
}

function moveHistory(delta: number) {
  historyOpen.value = true
  const n = historySuggestions.value.length
  if (!n) return
  const next = historyIndex.value + delta
  historyIndex.value = next < 0 ? n - 1 : next >= n ? 0 : next
}

function pickHistory(term: string) {
  clearTimeout(searchTimer)
  search.value = term
  rememberSearch(term)
  closeHistory()
  emitChange()
}

function removeHistory(term: string) {
  forgetSearch(term)
  historyIndex.value = -1
  searchInput.value?.focus()
}

function clearHistory() {
  forgetAll()
  historyIndex.value = -1
  searchInput.value?.focus()
}

function onSearchEnter() {
  const picked = historySuggestions.value[historyIndex.value]
  if (showHistory.value && picked) {
    pickHistory(picked)
    return
  }
  // Enter znamená „hľadaj hneď“ — nečakáme na doklepnutie debounce.
  clearTimeout(searchTimer)
  rememberSearch(search.value)
  closeHistory()
  emitChange()
}

function onSearchBlur() {
  // Opustené pole berieme ako dokončené hľadanie; rozpísané tvary („bra“ pred
  // „bratislava“) si história zahodí sama pri ďalšom zápise.
  rememberSearch(search.value)
  closeHistory()
}

// ─────────────────────────────────────────────────────────────────────────────

function emitChange() {
  emit('change')
}

function onSearchInput() {
  historyOpen.value = true
  historyIndex.value = -1
  clearTimeout(searchTimer)
  searchTimer = setTimeout(emitChange, 400)
}

function clearCanal() {
  emit('clear-canal')
  emitChange()
}

function reset() {
  clearTimeout(searchTimer)
  search.value = ''
  status.value = ''
  phase.value = ''
  sort.value = 'newest'
  dateFrom.value = ''
  dateTo.value = ''
  closeHistory()
  if (props.canalFilter) emit('clear-canal')
  emit('reset')
  emitChange()
}

// "/" focuses the search field (like GitHub)
function onKeydown(e: KeyboardEvent) {
  if (e.key !== '/' || e.ctrlKey || e.metaKey || e.altKey) return
  const target = e.target as HTMLElement | null
  if (target && (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA' || target.tagName === 'SELECT' || target.isContentEditable)) return
  e.preventDefault()
  searchInput.value?.focus()
}

onMounted(() => window.addEventListener('keydown', onKeydown))
onBeforeUnmount(() => {
  window.removeEventListener('keydown', onKeydown)
  clearTimeout(searchTimer)
  // Klik na výsledok odchádza zo stránky skôr, než pole stihne stratiť fokus.
  rememberSearch(search.value)
})
</script>
