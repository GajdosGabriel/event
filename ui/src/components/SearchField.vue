<template>
  <!-- Zbalené hľadanie je len ikona, aby sa filtre zmestili do jedného riadku;
       po kliknutí (alebo „/") sa pole roztiahne. S výrazom zostáva rozbalené,
       inak by sa stratil kontext filtra. -->
  <div
    class="relative min-w-0 shrink-0"
    :class="open ? (collapsible ? 'flex-1 sm:w-64 sm:flex-none' : 'w-full sm:w-64') : sizeClass.box"
  >
    <button
      v-if="!open"
      type="button"
      class="flex items-center justify-center rounded-lg border border-slate-300 bg-white text-slate-500 transition-colors hover:bg-slate-50 hover:text-slate-700"
      :class="sizeClass.box"
      :aria-label="label || placeholder || t('filters.search')"
      :title="shortcut ? t('filters.searchHint') : undefined"
      :aria-expanded="false"
      @click="expand"
    >
      <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
        <circle cx="11" cy="11" r="7" />
        <path d="M21 21l-4.3-4.3" stroke-linecap="round" />
      </svg>
    </button>

    <template v-else>
      <svg
        class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
        fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"
      >
        <circle cx="11" cy="11" r="7" />
        <path d="M21 21l-4.3-4.3" stroke-linecap="round" />
      </svg>
      <input
        ref="input"
        v-model="model"
        type="search"
        :placeholder="placeholder || t('filters.search')"
        :aria-label="label || placeholder || t('filters.search')"
        class="form-input search-field-input pl-9 pr-8"
        :class="sizeClass.input"
        autocomplete="off"
        role="combobox"
        aria-autocomplete="list"
        :aria-expanded="showHistory"
        @input="onInput"
        @focus="historyOpen = true"
        @blur="onBlur"
        @keydown.down.prevent="moveHistory(1)"
        @keydown.up.prevent="moveHistory(-1)"
        @keydown.enter.prevent="onEnter"
        @keydown.esc="onEscape"
      />
      <!-- `mousedown.prevent` drží fokus v poli — inak by blur pole zbalil
           skôr, než sa klik stihne vyhodnotiť. -->
      <button
        v-if="model"
        type="button"
        :aria-label="t('filters.clearSearch')"
        :title="t('filters.clearSearch')"
        class="absolute right-2 top-1/2 flex h-5 w-5 -translate-y-1/2 items-center justify-center rounded-full text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-700"
        @mousedown.prevent="clear"
      >
        <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
          <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" />
        </svg>
      </button>
      <kbd
        v-else-if="shortcut && !showHistory"
        class="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 rounded border border-slate-300 bg-slate-50 px-1.5 text-xs text-slate-400"
        :title="t('filters.searchHint')"
      >/</kbd>

      <!-- Naposledy hľadané -->
      <div
        v-if="showHistory"
        class="absolute right-0 top-full z-20 mt-1 w-full min-w-64 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-lg"
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
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useSearchHistory } from '@/composables/useSearchHistory'
import { useI18n } from '@/i18n'

/**
 * Hľadacie pole filtrov: zbalené na ikonu, s históriou naposledy hľadaných
 * výrazov (v prehliadači, per-výpis podľa `historyKey`).
 *
 * `search` sa emituje, keď má stránka naozaj hľadať — po doklepnutí (debounce),
 * hneď po Enteri, výbere z histórie alebo zrušení výrazu.
 */
const props = withDefaults(defineProps<{
  placeholder?: string
  /** Prístupný popis poľa a ikony; predvolene placeholder. */
  label?: string
  /** Priečinok histórie hľadania v prehliadači; prázdny kľúč históriu vypne. */
  historyKey?: string
  /** Bez výrazu sa pole zbalí na ikonu. */
  collapsible?: boolean
  /** Klávesa „/" kdekoľvek na stránke skočí do poľa (ako na GitHube). */
  shortcut?: boolean
  size?: 'md' | 'sm'
  debounce?: number
}>(), {
  placeholder: '',
  label: '',
  historyKey: '',
  collapsible: true,
  shortcut: false,
  size: 'md',
  debounce: 400,
})

const emit = defineEmits<{ search: [] }>()

const model = defineModel<string>({ default: '' })

const { t } = useI18n()

const input = ref<HTMLInputElement | null>(null)
const open = ref(!props.collapsible || Boolean(model.value))
let timer: ReturnType<typeof setTimeout> | undefined

const sizeClass = computed(() => props.size === 'sm'
  ? { box: 'h-9 w-9', input: 'h-9 text-sm' }
  : { box: 'h-11 w-11', input: '' })

// Výraz nastavený zvonku (adresa, „Zrušiť filtre") má pole rozbaliť, resp. zbaliť.
watch(model, value => {
  if (value) open.value = true
  else if (props.collapsible && document.activeElement !== input.value) open.value = false
})

function expand() {
  open.value = true
  // Až po prekreslení — pole ešte v DOM nie je, keď sa tlačidlo klikne.
  void nextTick(() => input.value?.focus())
}

function collapse() {
  if (props.collapsible && !model.value.trim()) open.value = false
}

function commit() {
  clearTimeout(timer)
  emit('search')
}

// ── História hľadania ────────────────────────────────────────────────────────

const historyKey = computed(() => props.historyKey)
const { items: historyItems, add: rememberSearch, remove: forgetSearch, clear: forgetAll } =
  useSearchHistory(historyKey)

/** Fokus v poli; zoznam sa naozaj ukáže, až keď má čo ponúknuť. */
const historyOpen = ref(false)
/** Index zvýrazneného návrhu, -1 = žiadny (platí to, čo je napísané). */
const historyIndex = ref(-1)

const historySuggestions = computed(() => {
  const q = model.value.trim().toLowerCase()
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
  model.value = term
  rememberSearch(term)
  closeHistory()
  commit()
}

function removeHistory(term: string) {
  forgetSearch(term)
  historyIndex.value = -1
  input.value?.focus()
}

function clearHistory() {
  forgetAll()
  historyIndex.value = -1
  input.value?.focus()
}

// ─────────────────────────────────────────────────────────────────────────────

function onInput() {
  historyOpen.value = true
  historyIndex.value = -1
  clearTimeout(timer)
  timer = setTimeout(() => emit('search'), props.debounce)
}

function onEnter() {
  const picked = historySuggestions.value[historyIndex.value]
  if (showHistory.value && picked) {
    pickHistory(picked)
    return
  }
  // Enter znamená „hľadaj hneď" — nečakáme na doklepnutie debounce.
  rememberSearch(model.value)
  closeHistory()
  commit()
}

function onBlur() {
  // Opustené pole berieme ako dokončené hľadanie; rozpísané tvary („bra" pred
  // „bratislava") si história zahodí sama pri ďalšom zápise.
  rememberSearch(model.value)
  closeHistory()
  collapse()
}

function clear() {
  if (model.value) {
    model.value = ''
    commit()
  }
  input.value?.focus()
}

/** Esc najprv zavrie históriu, potom zruší výraz, napokon pole zbalí. */
function onEscape() {
  if (showHistory.value) {
    closeHistory()
    return
  }
  if (model.value) {
    clear()
    return
  }
  input.value?.blur()
}

function onKeydown(e: KeyboardEvent) {
  if (e.key !== '/' || e.ctrlKey || e.metaKey || e.altKey) return
  const target = e.target as HTMLElement | null
  if (target && (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA' || target.tagName === 'SELECT' || target.isContentEditable)) return
  e.preventDefault()
  expand()
}

onMounted(() => {
  if (props.shortcut) window.addEventListener('keydown', onKeydown)
})
onBeforeUnmount(() => {
  window.removeEventListener('keydown', onKeydown)
  clearTimeout(timer)
  // Klik na výsledok odchádza zo stránky skôr, než pole stihne stratiť fokus.
  rememberSearch(model.value)
})

defineExpose({ focus: expand })
</script>

<style scoped>
/* Vlastný krížik máme v tlačidle — natívny by sa zobrazil vedľa neho. */
.search-field-input::-webkit-search-cancel-button { display: none; }
</style>
