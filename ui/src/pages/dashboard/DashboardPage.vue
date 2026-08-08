<template>
  <div class="grid gap-4">
    <div class="flex flex-wrap items-end justify-between gap-3">
      <div>
        <h1 class="text-3xl">{{ t('dashboard.title') }}</h1>
        <p class="text-slate-500">{{ t('dashboard.greeting', { name: auth.canalName || t('dashboard.fallbackName') }) }}</p>
      </div>

      <nav class="flex flex-wrap gap-2">
        <RouterLink to="/dashboard/events" class="btn btn-secondary btn-sm">{{ t('nav.events') }}</RouterLink>
        <RouterLink to="/dashboard/canals" class="btn btn-secondary btn-sm">{{ t('nav.canals') }}</RouterLink>
        <RouterLink to="/dashboard/venues" class="btn btn-secondary btn-sm">{{ t('nav.venues') }}</RouterLink>
        <RouterLink v-if="auth.isSuperAdmin" to="/admin" class="btn btn-sm border-amber-300 bg-amber-50 text-amber-800 hover:bg-amber-100">
          {{ t('nav.admin') }}
        </RouterLink>
      </nav>
    </div>

    <StatsOverview scope="dashboard" />
  </div>
</template>

<script setup lang="ts">
import StatsOverview from '@/components/stats/StatsOverview.vue'
import { useI18n } from '@/i18n'
import { useAuthStore } from '@/stores/auth'

const { t } = useI18n()
const auth = useAuthStore()
</script>
