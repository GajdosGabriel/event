<template>
  <div class="relative" ref="rootEl">
    <button
      class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-700"
      @click.stop="open = !open"
      aria-label="Akcie"
    >
      <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
        <circle cx="10" cy="4" r="1.5"/><circle cx="10" cy="10" r="1.5"/><circle cx="10" cy="16" r="1.5"/>
      </svg>
    </button>

    <Teleport to="body">
      <div
        v-if="open"
        :style="dropdownStyle"
        class="fixed z-[500] min-w-[160px] rounded-xl border border-slate-200 bg-white py-1 shadow-lg"
      >
        <slot />
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'

const open = ref(false)
const rootEl = ref<HTMLElement | null>(null)
const triggerRect = ref<DOMRect | null>(null)

const dropdownStyle = computed(() => {
  if (!triggerRect.value) return {}
  const r = triggerRect.value
  return {
    top: `${r.bottom + 4}px`,
    right: `${window.innerWidth - r.right}px`,
  }
})

function onDocClick(e: MouseEvent) {
  if (!rootEl.value?.contains(e.target as Node)) open.value = false
}

function updateRect() {
  triggerRect.value = rootEl.value?.getBoundingClientRect() ?? null
}

watch(open, (val) => { if (val) updateRect() })

onMounted(() => document.addEventListener('click', onDocClick))
onUnmounted(() => document.removeEventListener('click', onDocClick))
</script>
