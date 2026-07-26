<template>
  <div class="grid gap-4">
    <div class="flex flex-wrap items-end justify-between gap-3">
      <div>
        <h1 class="text-3xl">Admin</h1>
        <p class="text-slate-500">Prehľad celého systému.</p>
      </div>

      <nav class="flex flex-wrap gap-2">
        <RouterLink v-for="item in links" :key="item.to" :to="item.to" class="btn btn-secondary btn-sm">
          {{ item.label }}
        </RouterLink>
      </nav>
    </div>

    <StatsOverview scope="admin" />
  </div>
</template>

<script setup lang="ts">
import StatsOverview from '@/components/stats/StatsOverview.vue'

const links = [
  { to: '/admin/events', label: 'Eventy' },
  { to: '/admin/canals', label: 'Kanály' },
  { to: '/admin/venues', label: 'Miesta' },
  { to: '/admin/municipalities', label: 'Obce' },
  { to: '/admin/users', label: 'Používatelia' },
  { to: '/admin/files', label: 'Súbory' },
  { to: '/admin/tools', label: 'Nástroje' },
  { to: '/admin/settings', label: 'Nastavenia' },
]
</script>
