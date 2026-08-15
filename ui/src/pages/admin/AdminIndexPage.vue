<template>
  <div class="grid gap-4">
    <div class="flex flex-wrap items-end justify-between gap-3">
      <div>
        <h1 class="text-3xl">{{ t('nav.admin') }}</h1>
        <p class="text-slate-500">{{ t('admin.index.lead') }}</p>
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
import { computed } from 'vue'
import StatsOverview from '@/components/stats/StatsOverview.vue'
import { useI18n } from '@/i18n'

const { t } = useI18n()

// Tie isté položky nesie aj bočný panel, preto popisky z `nav`.
const links = computed(() => [
  { to: '/admin/events', label: t('nav.events') },
  { to: '/admin/canals', label: t('nav.canals') },
  { to: '/admin/venues', label: t('nav.venues') },
  { to: '/admin/municipalities', label: t('nav.municipalities') },
  { to: '/admin/users', label: t('nav.users') },
  { to: '/admin/files', label: t('nav.files') },
  { to: '/admin/tools', label: t('nav.tools') },
  { to: '/admin/settings', label: t('nav.settings') },
])
</script>
