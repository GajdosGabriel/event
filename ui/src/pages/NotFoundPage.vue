<template>
  <div class="mx-auto max-w-lg px-4 py-16">
    <div class="rounded-2xl border border-slate-200 bg-white p-6 text-center">
      <p class="text-4xl font-bold text-slate-300">404</p>
      <h1 class="mt-2 text-lg font-semibold text-slate-900">
        {{ kind === 'event' ? t('notFound.eventTitle') : t('notFound.title') }}
      </h1>
      <p class="mt-2 text-sm text-slate-700">
        {{ kind === 'event' ? t('notFound.eventLead') : t('notFound.lead') }}
      </p>
      <RouterLink to="/" class="mt-4 inline-block text-sm font-medium text-blue-700 no-underline hover:underline">
        {{ t('notFound.home') }} →
      </RouterLink>
    </div>
  </div>
</template>

<script setup lang="ts">
import { t } from '@/i18n'
import { usePrivatePageHead } from '@/composables/usePrivatePageHead'

// `event` = neplatné ID podujatia (napr. /akcie/abc), inak všeobecná 404.
const props = defineProps<{ kind?: 'event' }>()

// SPA vracia pre neexistujúcu adresu 200, preto noindex — inak by vyhľadávače
// indexovali chybovú stránku ako bežnú.
usePrivatePageHead(() => (props.kind === 'event' ? t('notFound.eventTitle') : t('notFound.title')))
</script>
