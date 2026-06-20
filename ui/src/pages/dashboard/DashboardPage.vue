<template>
  <div>
    <h1 class="mb-1 text-3xl">Dashboard</h1>
    <p class="mb-5 text-slate-500">Vitajte, {{ auth.canalName || 'používateľ' }}.</p>

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
      <RouterLink to="/dashboard/events" class="rounded-xl border border-slate-200 bg-white p-4 no-underline hover:border-slate-300">
        <h2 class="mb-1 text-sm text-slate-700">Eventy</h2>
        <p class="text-2xl font-bold text-slate-900">→</p>
      </RouterLink>
      <RouterLink to="/dashboard/canals" class="rounded-xl border border-slate-200 bg-white p-4 no-underline hover:border-slate-300">
        <h2 class="mb-1 text-sm text-slate-700">Kanály</h2>
        <p class="text-2xl font-bold text-slate-900">→</p>
      </RouterLink>
      <RouterLink to="/dashboard/venues" class="rounded-xl border border-slate-200 bg-white p-4 no-underline hover:border-slate-300">
        <h2 class="mb-1 text-sm text-slate-700">Miesta</h2>
        <p class="text-2xl font-bold text-slate-900">→</p>
      </RouterLink>
      <RouterLink v-if="auth.isSuperAdmin" to="/admin" class="rounded-xl border border-amber-200 bg-amber-50 p-4 no-underline hover:border-amber-300">
        <h2 class="mb-1 text-sm text-amber-700">Admin</h2>
        <p class="text-2xl font-bold text-amber-900">→</p>
      </RouterLink>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useAuthStore } from '@/stores/auth'
const auth = useAuthStore()
</script>
