<template>
  <div class="relative" ref="wrapper">
    <button
      type="button"
      class="form-input flex items-center justify-between gap-2 cursor-pointer text-left"
      :class="{ invalid }"
      @click="toggle"
    >
      <span class="truncate" :class="selectedOpt ? 'text-slate-900' : 'text-slate-400'">
        {{ selectedOpt ? selectedOpt.name : (placeholder ?? '— vyberte —') }}
      </span>
      <svg class="h-4 w-4 shrink-0 text-slate-400 transition-transform" :class="{ 'rotate-180': open }"
        fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
      </svg>
    </button>

    <Teleport to="body">
      <div
        v-if="open"
        :style="dropStyle"
        class="fixed z-[700] rounded-xl border border-slate-200 bg-white shadow-xl"
      >
        <div class="p-2 border-b border-slate-100">
          <input
            ref="searchInput"
            v-model="search"
            type="text"
            class="form-input h-8 text-sm"
            placeholder="Hľadať…"
            @click.stop
          />
        </div>
        <ul class="max-h-52 overflow-y-auto py-1">
          <li
            v-if="!filtered.length"
            class="px-3 py-2 text-sm text-slate-400"
          >
            Žiadne výsledky
          </li>
          <li
            v-for="opt in filtered"
            :key="opt.id"
            class="cursor-pointer px-3 py-2 text-sm text-slate-900 hover:bg-slate-50"
            :class="{ 'bg-blue-50 font-semibold text-blue-700': opt.id === modelValue }"
            @mousedown.prevent="select(opt)"
          >
            {{ opt.name }}
          </li>
        </ul>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, nextTick, onMounted, onUnmounted } from 'vue'

interface Option { id: number; name: string }

const props = defineProps<{
  modelValue: number | null
  options: Option[]
  placeholder?: string
  invalid?: boolean
}>()

const emit = defineEmits<{ (e: 'update:modelValue', v: number | null): void }>()

const wrapper = ref<HTMLElement | null>(null)
const searchInput = ref<HTMLInputElement | null>(null)
const open = ref(false)
const search = ref('')
const dropStyle = ref<Record<string, string>>({})

const selectedOpt = computed(() => props.options.find(o => o.id === props.modelValue) ?? null)

const filtered = computed(() => {
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
  nextTick(() => searchInput.value?.focus())
}

function select(opt: Option) {
  emit('update:modelValue', opt.id)
  open.value = false
}

function onDocClick(e: MouseEvent) {
  if (!wrapper.value?.contains(e.target as Node)) open.value = false
}

onMounted(() => document.addEventListener('click', onDocClick))
onUnmounted(() => document.removeEventListener('click', onDocClick))

watch(open, v => { if (v) calcStyle() })
</script>
