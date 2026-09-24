<template>
  <div class="relative" ref="wrapper">
    <button
      type="button"
      ref="trigger"
      class="form-input flex items-center justify-between gap-2 cursor-pointer text-left"
      :class="{ invalid }"
      @click="toggle"
      :aria-expanded="open"
      aria-haspopup="listbox"
    >
      <span class="truncate" :class="selectedOpt ? 'text-slate-900' : 'text-slate-400'">
        {{ selectedOpt ? selectedOpt.name : (modelValue ? `#${modelValue}` : (placeholder ?? '—')) }}
      </span>
      <svg class="h-4 w-4 shrink-0 text-slate-400 transition-transform" :class="{ 'rotate-180': open }"
        fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
      </svg>
    </button>

    <Teleport to="body">
      <div
        v-if="open"
        ref="dropdown"
        :style="dropStyle"
        class="fixed z-[700] rounded-xl border border-slate-200 bg-white shadow-xl"
      >
        <div class="p-2 border-b border-slate-100">
          <input
            ref="searchInput"
            v-model="search"
            type="text"
            class="form-input h-8 text-sm"
            :placeholder="t('filters.search')"
            @click.stop
            @keydown.down.prevent="active = Math.min(active + 1, filtered.length - 1)"
            @keydown.up.prevent="active = Math.max(active - 1, 0)"
            @keydown.enter.prevent="selectActive"
            @keydown.esc.stop.prevent="open = false; trigger?.focus()"
            role="combobox"
            :aria-label="t('filters.search')"
            :aria-expanded="open"
            :aria-controls="listId"
            :aria-activedescendant="filtered[active] ? `${listId}-${filtered[active]?.id}` : undefined"
          />
        </div>
        <ul class="max-h-52 overflow-y-auto py-1" role="listbox" :id="listId" :aria-busy="loading">
          <li v-if="loading" class="px-3 py-2 text-sm">{{ t('common.loading') }}</li>
          <li v-if="failed" class="px-3 py-2 text-sm" role="alert">{{ t('common.actionFailed') }} <button type="button" @click="fetchOptions(nextPage)">{{ t('roadmap.retry') }}</button></li>
          <li
            v-if="!filtered.length && !loading && !failed"
            class="px-3 py-2 text-sm text-slate-400"
          >
            {{ t('filters.noResults') }}
          </li>
          <li
            v-for="(opt, index) in filtered"
            role="option"
            :id="`${listId}-${opt.id}`"
            :aria-selected="opt.id === modelValue"
            :key="opt.id"
            class="cursor-pointer px-3 py-2 text-sm text-slate-900 hover:bg-slate-50"
            :class="{ 'bg-blue-50 font-semibold text-blue-700': opt.id === modelValue || index === active }"
            @mousedown.prevent="select(opt)"
          >
            {{ opt.name }}
          </li>
        </ul>
        <button v-if="source && nextPage <= lastPage && !loading && !failed" type="button" class="p-2 text-sm" @click="fetchOptions(nextPage)">{{ t('roadmap.more') }}</button>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, nextTick, onMounted, onUnmounted, useId } from 'vue'
import http from '@/api/index'
import { useI18n } from '@/i18n'

const { t } = useI18n()

interface Option { id: number; name: string }

const props = defineProps<{
  modelValue: number | null
  options: Option[]
  placeholder?: string
  invalid?: boolean
  source?: string
  params?: Record<string, unknown>
}>()

const emit = defineEmits<{ (e: 'update:modelValue', v: number | null): void; (e: 'selected', option: Option): void }>()

const listId = useId()
const trigger = ref<HTMLButtonElement | null>(null)
const dropdown = ref<HTMLElement | null>(null)
const remote = ref<Option[]>([])
const chosen = ref<Option | null>(null)
const loading = ref(false)
const failed = ref(false)
const nextPage = ref(1)
const lastPage = ref(1)
const active = ref(0)
let requestId = 0
let timer: ReturnType<typeof setTimeout> | undefined
const wrapper = ref<HTMLElement | null>(null)
const searchInput = ref<HTMLInputElement | null>(null)
const open = ref(false)
const search = ref('')
const dropStyle = ref<Record<string, string>>({})

const selectedOpt = computed(() => [...props.options, ...remote.value, ...(chosen.value ? [chosen.value] : [])].find(o => o.id === props.modelValue) ?? null)

const filtered = computed(() => {
  if (props.source) return remote.value
  const q = search.value.trim().toLowerCase()
  const list = q ? props.options.filter(o => o.name.toLowerCase().includes(q)) : props.options
  return list.slice(0, 60)
})

function calcStyle() {
  const rect = wrapper.value?.getBoundingClientRect()
  if (!rect) return
  dropStyle.value = {
    top: `${rect.bottom + 4}px`,
    left: `${rect.left}px`,
    width: `${rect.width}px`,
  }
}

function toggle() {
  if (open.value) { open.value = false; return }
  calcStyle()
  open.value = true
  search.value = ''
  if (props.source) fetchOptions(1)
  nextTick(() => searchInput.value?.focus())
}

function select(opt: Option) {
  chosen.value = opt
  emit('selected', opt)
  emit('update:modelValue', opt.id)
  open.value = false
  trigger.value?.focus()
}

function onDocClick(e: MouseEvent) {
  if (!wrapper.value?.contains(e.target as Node) && !dropdown.value?.contains(e.target as Node)) open.value = false
}

function selectActive() {
  const option = filtered.value[active.value]
  if (option) select(option)
}

async function fetchOptions(page: number) {
  if (!props.source) return
  const id = ++requestId
  loading.value = true
  failed.value = false
  nextPage.value = page
  try {
    const { data } = await http.get(props.source, { params: { ...props.params, search: search.value, per_page: 20, page } })
    if (id !== requestId) return
    const rows = (data.data ?? data) as Option[]
    remote.value = page === 1 ? rows : [...remote.value, ...rows.filter(row => !remote.value.some(old => old.id === row.id))]
    lastPage.value = data.meta?.last_page ?? 1
    nextPage.value = page + 1
    active.value = 0
  } catch {
    if (id === requestId) failed.value = true
  } finally {
    if (id === requestId) loading.value = false
  }
}

watch(() => [props.modelValue, props.source] as const, async ([id, source]) => {
  if (!id || !source || selectedOpt.value) return
  try {
    const { data } = await http.get(`${source}/${id}`)
    if (props.modelValue === id && props.source === source) chosen.value = data.data ?? data
  } catch { /* Keep the ID visible when the saved label is unavailable. */ }
}, { immediate: true })

watch(search, () => {
  if (!props.source || !open.value) return
  ++requestId
  clearTimeout(timer)
  remote.value = []
  loading.value = true
  timer = setTimeout(() => fetchOptions(1), 300)
})
watch(() => [props.source, props.params], () => {
  ++requestId
  clearTimeout(timer)
  remote.value = []
  if (open.value) fetchOptions(1)
}, { deep: true })

onMounted(() => document.addEventListener('click', onDocClick))
onUnmounted(() => { ++requestId; clearTimeout(timer); document.removeEventListener('click', onDocClick) })

watch(active, () => nextTick(() => document.getElementById(`${listId}-${filtered.value[active.value]?.id}`)?.scrollIntoView?.({ block: 'nearest' })))
watch(search, () => { active.value = 0 })
watch(open, v => { if (v) calcStyle() })
</script>
