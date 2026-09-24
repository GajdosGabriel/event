<template>
  <!-- Všetko v jednom riadku: filtre, zrušenie, prepínač filtrov a na konci
       hľadanie zbalené na ikonu. Na telefóne (a pri `collapsible`) sa filtre
       rozbaľujú do vlastného riadku pod lištou. -->
  <div class="flex flex-wrap items-center gap-2">
    <!-- Collapsible filter group (visible from sm up, unless `collapsible`) -->
    <div
      class="order-last w-full flex-wrap items-center gap-2"
      :class="[expanded ? 'flex' : 'hidden', collapsible ? '' : 'sm:order-none sm:flex sm:w-auto sm:min-w-0 sm:flex-1']"
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

    <!-- Prepínač filtrov (mobil) + hľadanie na konci riadku -->
    <div class="ml-auto flex w-full items-center justify-end gap-2 sm:w-auto">
      <button
        type="button"
        class="flex h-11 shrink-0 items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 text-sm font-medium text-slate-700"
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

      <SearchField
        v-model="search"
        :placeholder="searchPlaceholder"
        :history-key="historyKey"
        shortcut
        @search="emitChange"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import SearchField from '@/components/SearchField.vue'
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

const expanded = ref(false)

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

function emitChange() {
  emit('change')
}

function clearCanal() {
  emit('clear-canal')
  emitChange()
}

function reset() {
  search.value = ''
  status.value = ''
  phase.value = ''
  sort.value = 'newest'
  dateFrom.value = ''
  dateTo.value = ''
  if (props.canalFilter) emit('clear-canal')
  emit('reset')
  emitChange()
}
</script>
