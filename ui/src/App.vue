<template>
  <div v-if="navigating" class="nav-progress" aria-hidden="true" />
  <RouterView />
  <AppToast />
</template>

<script setup lang="ts">
import AppToast from '@/components/AppToast.vue'
import { navigating } from '@/composables/useNavigationProgress'
</script>

<style scoped>
.nav-progress {
  position: fixed;
  inset: 0 0 auto 0;
  z-index: 100;
  height: 3px;
  background: linear-gradient(90deg, transparent, #14b8a6, transparent);
  background-size: 40% 100%;
  background-repeat: no-repeat;
  animation: nav-progress 1s ease-in-out infinite;
}
@keyframes nav-progress {
  from { background-position: -40% 0; }
  to { background-position: 140% 0; }
}
@media (prefers-reduced-motion: reduce) {
  .nav-progress { animation: none; background-size: 100% 100%; opacity: 0.6; }
}
</style>
