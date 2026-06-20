<template>
  <div>
    <h1 class="mb-1 text-3xl">Admin</h1>
    <p class="mb-5 text-slate-500">Správa systému.</p>

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-3">
      <RouterLink v-for="link in links" :key="link.to" :to="link.to"
        class="rounded-xl border border-slate-200 bg-white p-4 no-underline hover:border-slate-300">
        <h2 class="mb-1 text-sm text-slate-700">{{ link.label }}</h2>
        <p class="text-2xl font-bold text-slate-900">→</p>
      </RouterLink>
    </div>
  </div>
</template>

<script setup lang="ts">
const links = [
  { to: '/admin/events', label: 'Eventy' },
  { to: '/admin/canals', label: 'Kanály' },
  { to: '/admin/venues', label: 'Miesta' },
  { to: '/admin/users', label: 'Používatelia' },
  { to: '/admin/settings', label: 'Nastavenia' },
  { to: '/admin/files', label: 'Súbory' },
]
</script>
