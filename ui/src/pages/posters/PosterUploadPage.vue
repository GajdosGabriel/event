<template>
  <div class="mx-auto w-full max-w-[820px] px-4 pt-6 pb-10">
    <header class="mb-5">
      <h1 class="mb-1 text-2xl text-slate-900">{{ t('poster.page.title') }}</h1>
      <p class="text-slate-500">
        {{ t('poster.page.lead') }}
      </p>
    </header>

    <PosterUploadWizard />

    <p class="mt-4 text-xs text-slate-400">
      {{ t('poster.page.note') }}
    </p>
  </div>
</template>

<script setup lang="ts">
import PosterUploadWizard from '@/components/poster/PosterUploadWizard.vue'
import { t } from '@/i18n'
</script>
