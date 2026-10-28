<template>
  <!-- Súhlas s analytickými cookies. Obe voľby sú rovnako výrazné — GDPR
       nepripúšťa, aby odmietnutie bolo schované alebo ťažšie ako súhlas. -->
  <div
    v-if="visible"
    role="dialog"
    aria-live="polite"
    :aria-label="t('cookies.title')"
    class="fixed inset-x-3 bottom-3 z-[1100] mx-auto max-w-xl rounded-2xl border border-slate-200 bg-white p-4 text-sm text-slate-700 shadow-xl sm:p-5"
  >
    <p class="font-semibold text-slate-900">{{ t('cookies.title') }}</p>
    <p class="mt-1">
      {{ t('cookies.text') }}
      <RouterLink to="/ochrana-osobnych-udajov" class="font-medium text-slate-900 underline">{{ t('cookies.more') }}</RouterLink>
    </p>
    <div class="mt-3 flex flex-wrap gap-2">
      <button type="button" class="flex-1 rounded-lg bg-slate-900 px-4 py-2 font-semibold text-white hover:bg-slate-700 sm:flex-none" @click="setAnalyticsConsent('granted')">{{ t('cookies.accept') }}</button>
      <button type="button" class="flex-1 rounded-lg bg-slate-900 px-4 py-2 font-semibold text-white hover:bg-slate-700 sm:flex-none" @click="setAnalyticsConsent('denied')">{{ t('cookies.reject') }}</button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from '@/i18n'
import { analyticsAvailable, consentBannerOpen, setAnalyticsConsent } from '@/utils/analytics'

const { t } = useI18n()
const route = useRoute()

// Widget na cudzom webe nemeriame ani tam nič nevyskakuje — to je ich stránka.
const visible = computed(
  () => consentBannerOpen.value && (analyticsAvailable || import.meta.env.DEV) && !route.path.startsWith('/embed'),
)
</script>

