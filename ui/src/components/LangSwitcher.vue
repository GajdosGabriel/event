<template>
  <div class="relative" ref="rootEl">
    <button
      type="button"
      class="flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-sm font-semibold transition"
      :class="triggerClass"
      :title="t('lang.label')"
      :aria-label="t('lang.label')"
      aria-haspopup="listbox"
      :aria-expanded="open"
      @click="open = !open"
    >
      <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="10" />
        <path stroke-linecap="round" d="M2 12h20M12 2c-2.5 2.5-4 5.9-4 10s1.5 7.5 4 10M12 2c2.5 2.5 4 5.9 4 10s-1.5 7.5-4 10" />
      </svg>
      <span class="uppercase">{{ locale }}</span>
    </button>

    <Transition
      enter-active-class="transition duration-100 ease-out"
      enter-from-class="scale-95 opacity-0"
      enter-to-class="scale-100 opacity-100"
      leave-active-class="transition duration-75 ease-in"
      leave-from-class="scale-100 opacity-100"
      leave-to-class="scale-95 opacity-0"
    >
      <ul
        v-if="open"
        role="listbox"
        class="absolute right-0 top-full z-50 mt-2 w-40 origin-top-right overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-lg"
      >
        <li v-for="code in locales" :key="code">
          <button
            type="button"
            role="option"
            :aria-selected="code === locale"
            class="flex w-full items-center justify-between gap-2 px-4 py-2 text-left text-sm transition hover:bg-slate-50"
            :class="code === locale ? 'font-semibold text-slate-900' : 'text-slate-700'"
            @click="choose(code)"
          >
            <span class="truncate">{{ t(`lang.${code}` as MessageKey) }}</span>
            <svg
              v-if="code === locale"
              class="h-4 w-4 shrink-0 text-slate-400"
              fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"
            >
              <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
            </svg>
          </button>
        </li>
      </ul>
    </Transition>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useI18n, type Locale, type MessageKey } from '@/i18n'

// Rovnaké varianty ako UserDropdown — prepínač stojí vedľa neho v hlavičke
// a musí sadnúť na tmavý, teal aj amber podklad.
const props = withDefaults(defineProps<{
  variant?: 'dark' | 'teal' | 'amber'
}>(), {
  variant: 'dark',
})

const { t, locale, setLocale, locales } = useI18n()

const open = ref(false)
const rootEl = ref<HTMLElement | null>(null)

const triggerClass = computed(() => ({
  dark:  'text-slate-300 hover:bg-white/10 hover:text-white',
  teal:  'text-teal-50/80 hover:bg-teal-200/15 hover:text-white',
  amber: 'text-amber-50/80 hover:bg-amber-200/15 hover:text-white',
}[props.variant]))

function choose(code: Locale) {
  setLocale(code)
  open.value = false
}

function onClickOutside(e: MouseEvent) {
  if (rootEl.value && !rootEl.value.contains(e.target as Node)) open.value = false
}

onMounted(() => document.addEventListener('mousedown', onClickOutside))
onUnmounted(() => document.removeEventListener('mousedown', onClickOutside))
</script>
