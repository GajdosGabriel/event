<template>
  <div class="flex flex-wrap items-center gap-2">
    <!-- Search + mobile toggle -->
    <div class="flex w-full items-center gap-2 sm:w-auto">
      <!-- Search with "/" shortcut hint -->
      <div class="relative w-full max-w-xs">
        <input
          ref="searchInput"
          v-model="search"
          type="search"
          placeholder="Hľadať…"
          class="form-input pr-8"
          @input="onSearchInput"
        />
        <kbd
          class="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 rounded border border-slate-300 bg-slate-50 px-1.5 text-xs text-slate-400"
          title="Stlač / pre vyhľadávanie"
        >/</kbd>
      </div>

      <!-- Mobile toggle for the rest of the filters -->
      <button
        type="button"
        class="flex h-10 shrink-0 items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 text-sm font-medium text-slate-700 sm:hidden"
        :aria-expanded="expanded"
        @click="expanded = !expanded"
      >
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4h18M6 12h12M10 20h4"/></svg>
        Filtre
        <span
          v-if="activeCount > 0"
          class="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-blue-100 px-1 text-xs font-semibold text-blue-700"
        >{{ activeCount }}</span>
      </button>
    </div>

    <!-- Collapsible filter group (always visible from sm up) -->
    <div
      class="w-full flex-wrap items-center gap-2 sm:flex sm:w-auto"
      :class="expanded ? 'flex' : 'hidden'"
    >
    <!-- Status -->
    <select v-if="statusOptions.length" v-model="status" class="form-input w-auto" @change="emitChange">
      <option value="">Všetky stavy</option>
      <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
    </select>

    <!-- Sort -->
    <select v-model="sort" class="form-input w-auto" title="Zoradenie" @change="emitChange">
      <option v-for="opt in sortOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
    </select>

    <!-- Extra filters injected by the host page -->
    <slot name="filters" />

    <!-- Date range (events) -->
    <template v-if="showDateRange">
      <label class="flex items-center gap-1.5 text-sm text-slate-500">
        Od
        <input v-model="dateFrom" type="date" class="form-input w-auto" :max="dateTo || undefined" @change="emitChange" />
      </label>
      <label class="flex items-center gap-1.5 text-sm text-slate-500">
        Do
        <input v-model="dateTo" type="date" class="form-input w-auto" :min="dateFrom || undefined" @change="emitChange" />
      </label>
    </template>

    <!-- Only deleted (admin) -->
    <label
      v-if="showDeleted"
      class="flex h-10 cursor-pointer select-none items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-700"
      :class="{ 'border-red-300 bg-red-50 text-red-700': onlyDeleted }"
    >
      <input v-model="onlyDeleted" type="checkbox" class="accent-red-500" @change="emitChange" />
      Len zmazané
    </label>

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
      Zrušiť filtre
      <span class="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-blue-100 px-1 text-xs font-semibold text-blue-700">{{ activeCount }}</span>
    </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'

export interface FilterOption {
  value: string
  label: string
}

const props = withDefaults(defineProps<{
  statusOptions?: FilterOption[]
  sortOptions?: FilterOption[]
  showDateRange?: boolean
  showDeleted?: boolean
  canalFilter?: { id: number; name: string } | null
}>(), {
  statusOptions: () => [],
  sortOptions: () => [
    { value: 'newest', label: 'Najnovšie' },
    { value: 'oldest', label: 'Najstaršie' },
    { value: 'name', label: 'Názov A–Z' },
  ],
  showDateRange: false,
  showDeleted: false,
  canalFilter: null,
})

const emit = defineEmits<{
  change: []
  'clear-canal': []
}>()

const search = defineModel<string>('search', { default: '' })
const status = defineModel<string>('status', { default: '' })
const sort = defineModel<string>('sort', { default: 'newest' })
const dateFrom = defineModel<string>('dateFrom', { default: '' })
const dateTo = defineModel<string>('dateTo', { default: '' })
const onlyDeleted = defineModel<boolean>('onlyDeleted', { default: false })

const searchInput = ref<HTMLInputElement | null>(null)
const expanded = ref(false)
let searchTimer: ReturnType<typeof setTimeout>

const activeCount = computed(() => {
  let n = 0
  if (search.value) n++
  if (status.value) n++
  if (sort.value && sort.value !== 'newest') n++
  if (dateFrom.value) n++
  if (dateTo.value) n++
  if (onlyDeleted.value) n++
  if (props.canalFilter) n++
  return n
})

function emitChange() {
  emit('change')
}

function onSearchInput() {
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
  sort.value = 'newest'
  dateFrom.value = ''
  dateTo.value = ''
  onlyDeleted.value = false
  if (props.canalFilter) emit('clear-canal')
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
})
</script>
