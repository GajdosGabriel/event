<template>
  <div v-if="lastPage > 1" class="flex flex-wrap justify-center gap-2 pt-4">
    <button class="nav-btn" :disabled="currentPage <= 1" @click="emit('change', currentPage - 1)">‹</button>
    <button
      v-for="page in pages"
      :key="page"
      class="page-btn"
      :class="{ active: page === currentPage }"
      @click="emit('change', page)"
    >{{ page }}</button>
    <button class="nav-btn" :disabled="currentPage >= lastPage" @click="emit('change', currentPage + 1)">›</button>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{ currentPage: number; lastPage: number }>()
const emit = defineEmits<{ change: [page: number] }>()

const pages = computed(() => {
  const range: number[] = []
  const start = Math.max(1, props.currentPage - 2)
  const end = Math.min(props.lastPage, props.currentPage + 2)
  for (let i = start; i <= end; i++) range.push(i)
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
</style>
