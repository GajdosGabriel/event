<template>
  <!-- Plávajúca lišta, nie pruh nad stránkou — layouty počítajú s celou
       výškou okna a pruh by ich posunul. -->
  <div v-if="auth.impersonator" class="impersonation-bar" role="status">
    <span class="min-w-0">
      <strong class="block truncate">{{ t('impersonation.viewing', { name: auth.displayName }) }}</strong>
      <small class="block truncate">{{ t('impersonation.by', { admin: auth.impersonator.display_name }) }}</small>
    </span>
    <button type="button" class="impersonation-back" :disabled="leaving" @click="back">
      {{ t('impersonation.back') }}
    </button>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { t } from '@/i18n'

const auth = useAuthStore()
const leaving = ref(false)

async function back() {
  leaving.value = true
  try {
    await auth.stopImpersonating()
  } finally {
    leaving.value = false
  }
}
</script>

<style scoped>
.impersonation-bar {
  position: fixed;
  left: 50%;
  bottom: 1rem;
  z-index: 90;
  display: flex;
  align-items: center;
  gap: 0.75rem;
  max-width: calc(100vw - 2rem);
  transform: translateX(-50%);
  padding: 0.5rem 0.5rem 0.5rem 1rem;
  border-radius: 9999px;
  background: #92400e;
  color: #fff;
  font-size: 0.8125rem;
  line-height: 1.25;
  box-shadow: 0 10px 25px -5px rgb(0 0 0 / 0.3);
}
.impersonation-bar small {
  color: #fde68a;
}
.impersonation-back {
  flex-shrink: 0;
  padding: 0.375rem 0.875rem;
  border-radius: 9999px;
  background: #fff;
  color: #92400e;
  font-weight: 600;
  white-space: nowrap;
}
.impersonation-back:hover {
  background: #fef3c7;
}
.impersonation-back:disabled {
  opacity: 0.6;
}
</style>
