<template>
  <!-- Cieľ odkazu „Odhlásiť sa" z oslovenia po akcii. Samotné odhlásenie
       spravil server (EmailUnsubscribeController) ešte pred presmerovaním. -->
  <div class="mx-auto max-w-lg px-4 py-10">
    <div class="rounded-2xl border border-slate-200 bg-white p-6">
      <h1 class="text-lg font-semibold text-slate-900">{{ t('unsubscribed.title') }}</h1>
      <p class="mt-2 text-sm text-slate-700">{{ t('unsubscribed.lead') }}</p>
      <RouterLink to="/" class="mt-4 inline-block text-sm font-medium text-blue-700 no-underline hover:underline">
        {{ t('unsubscribed.home') }} →
      </RouterLink>
    </div>
  </div>
</template>

<script setup lang="ts">
import { t } from '@/i18n'
</script>
