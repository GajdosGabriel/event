<template>
  <div class="grid gap-4">
    <div class="flex flex-wrap items-end justify-between gap-3">
      <div>
        <h1 class="text-3xl">{{ t('dashboard.title') }}</h1>
        <p class="text-slate-500">{{ t('dashboard.greeting', { name: auth.displayName || auth.canalName || t('dashboard.fallbackName') }) }}</p>
      </div>

      <nav class="flex flex-wrap gap-2">
        <RouterLink to="/dashboard/events/create" class="btn btn-primary inline-flex items-center"><svg class="mr-1 size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>{{ t('eventJourney.add') }}</RouterLink>
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
