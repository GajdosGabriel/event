<template>
  <div class="grid gap-4">
    <div class="flex flex-wrap items-end justify-between gap-3">
      <div>
        <h1 class="text-3xl">Dashboard</h1>
        <p class="text-slate-500">Vitajte, {{ auth.canalName || 'používateľ' }}.</p>
      </div>

      <nav class="flex flex-wrap gap-2">
        <RouterLink to="/dashboard/events" class="btn btn-secondary btn-sm">Eventy</RouterLink>
        <RouterLink to="/dashboard/canals" class="btn btn-secondary btn-sm">Kanály</RouterLink>
        <RouterLink to="/dashboard/venues" class="btn btn-secondary btn-sm">Miesta</RouterLink>
        <RouterLink v-if="auth.isSuperAdmin" to="/admin" class="btn btn-sm border-amber-300 bg-amber-50 text-amber-800 hover:bg-amber-100">
          Admin
        </RouterLink>
      </nav>
    </div>

    <StatsOverview scope="dashboard" />
  </div>
</template>

<script setup lang="ts">
import StatsOverview from '@/components/stats/StatsOverview.vue'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
</script>
