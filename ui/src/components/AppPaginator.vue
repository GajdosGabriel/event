<template>
  <div v-if="lastPage > 1" class="flex flex-wrap justify-center gap-2 pt-4">
    <button class="nav-btn" :disabled="currentPage <= 1" @click="emit('change', currentPage - 1)">‹</button>
    <template v-for="(page, i) in pages" :key="i">
      <span v-if="page === GAP" class="gap">…</span>
      <button
        v-else
        class="page-btn"
        :class="{ active: page === currentPage }"
        :aria-current="page === currentPage ? 'page' : undefined"
        @click="emit('change', page)"
      >{{ page }}</button>
    </template>
    <button class="nav-btn" :disabled="currentPage >= lastPage" @click="emit('change', currentPage + 1)">›</button>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{ currentPage: number; lastPage: number }>()
const emit = defineEmits<{ change: [page: number] }>()

const GAP = '…'
// Počet čísel v posuvnom okne okolo aktuálnej stránky (prvá a posledná sa zobrazujú vždy).
const WINDOW = 5

const pages = computed<(number | typeof GAP)[]>(() => {
  const total = props.lastPage
  if (total <= WINDOW + 2) return Array.from({ length: total }, (_, i) => i + 1)

  // Okno má vždy rovnakú šírku, aby sa pri prepínaní stránok neobjavovali a nemizli odkazy.
  const start = Math.min(Math.max(props.currentPage - Math.floor(WINDOW / 2), 2), total - WINDOW)
  const end = start + WINDOW - 1

  const range: (number | typeof GAP)[] = [1]
  if (start > 2) range.push(GAP)
  for (let i = start; i <= end; i++) range.push(i)
  if (end < total - 1) range.push(GAP)
  range.push(total)
  return range
})
</script>

<style scoped>
@reference "tailwindcss";

button {
  @apply h-8 min-w-8 cursor-pointer rounded-md border border-slate-300 bg-white px-2 text-slate-700;
}
button:hover:not(:disabled) { @apply border-slate-400; }
button:disabled { @apply cursor-default opacity-45; }
.page-btn.active { @apply border-blue-600 bg-blue-600 text-white; }
.nav-btn { @apply px-3; }
.gap { @apply flex h-8 min-w-8 items-center justify-center text-slate-400 select-none; }
</style>
