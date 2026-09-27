<template>
  <div class="grid gap-4">
    <div class="flex flex-wrap items-end justify-between gap-3">
      <div>
        <h1 class="text-3xl">{{ t('dashboard.title') }}</h1>
        <p class="text-slate-500">{{ t('dashboard.greeting', { name: auth.canalName || t('dashboard.fallbackName') }) }}</p>
      </div>

      <nav class="flex flex-wrap gap-2">
        <RouterLink to="/dashboard/events/create" class="btn btn-primary">+ {{ t('eventJourney.add') }}</RouterLink>
        <RouterLink to="/dashboard/events" class="btn btn-secondary">{{ t('nav.events') }}</RouterLink>
        <RouterLink v-if="auth.isSuperAdmin" to="/admin" class="btn btn-sm border-amber-300 bg-amber-50 text-amber-800 hover:bg-amber-100">
          {{ t('nav.admin') }}
        </RouterLink>
      </nav>
    </div>

    <DashboardNextActions />
    <FormSection :title="t('eventJourney.statistics')">
      <StatsOverview scope="dashboard" />
    </FormSection>
  </div>
</template>

<script setup lang="ts">
import DashboardNextActions from '@/components/DashboardNextActions.vue'
import FormSection from '@/components/FormSection.vue'
import StatsOverview from '@/components/stats/StatsOverview.vue'
import { useI18n } from '@/i18n'
import { useAuthStore } from '@/stores/auth'

const { t } = useI18n()
const auth = useAuthStore()
</script>
