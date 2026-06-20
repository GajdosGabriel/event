<template>
  <Teleport to="body">
    <div class="fixed bottom-4 right-4 z-50 flex flex-col gap-2">
      <TransitionGroup name="toast">
        <div
          v-for="toast in toasts"
          :key="toast.id"
          class="flex items-center gap-3 rounded-xl border px-4 py-3 shadow-lg text-sm font-medium"
          :class="{
            'border-green-300 bg-green-50 text-green-800': toast.type === 'success',
            'border-red-300 bg-red-50 text-red-800': toast.type === 'error',
            'border-blue-300 bg-blue-50 text-blue-800': toast.type === 'info',
          }"
        >
          <span>{{ toast.message }}</span>
          <button class="ml-auto text-current opacity-60 hover:opacity-100" @click="dismiss(toast.id)">✕</button>
        </div>
      </TransitionGroup>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { useToast } from '@/composables/useToast'
const { toasts, dismiss } = useToast()
</script>

<style scoped>
@reference "tailwindcss";

.toast-enter-active, .toast-leave-active { transition: all 0.2s ease; }
.toast-enter-from { opacity: 0; transform: translateY(8px); }
.toast-leave-to { opacity: 0; transform: translateY(-4px); }
</style>
