<template>
  <div class="grid gap-4">
    <div class="flex flex-wrap items-end justify-between gap-3">
      <div>
        <h1 class="text-3xl">{{ t('nav.admin') }}</h1>
        <p class="text-slate-500">{{ t('admin.index.lead') }}</p>
      </div>

    </div>

    <StatsOverview scope="admin" />
  </div>
</template>

<script setup lang="ts">
import StatsOverview from '@/components/stats/StatsOverview.vue'
import { useI18n } from '@/i18n'

const { t } = useI18n()
</script>
