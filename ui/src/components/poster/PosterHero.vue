<template>
  <!-- Plocha ostáva svetlá — tmavý blok by hero opticky odrezal od zoznamu pod
       ním. Dôraz nesie sýta zelená (pruh, štítok, tlačidlo), nie výška: hero
       stojí nad zoznamom podujatí a nesmie ho odtlačiť pod ohyb. Na telefóne
       je to kritické, preto tam ostáva len nadpis a tlačidlo — kroky aj
       podrobnosti čakajú na stránke nahrávania, kde ich sprievodca zopakuje. -->
  <section class="relative mb-8 overflow-hidden rounded-2xl bg-linear-to-r from-emerald-50 via-teal-50 to-sky-50 ring-1 ring-emerald-900/10">
    <!-- Dekoratívny kruh vpravo — len aby plocha nebola plochá. -->
    <div class="pointer-events-none absolute -top-16 -right-10 h-48 w-48 rounded-full bg-emerald-200/40 blur-2xl" aria-hidden="true"></div>

    <div class="relative flex flex-wrap items-center justify-between gap-x-8 gap-y-3 px-5 py-4 sm:px-7 sm:py-5">
      <div class="min-w-0">
        <h2 class="flex flex-wrap items-center gap-x-2 gap-y-1 text-lg font-bold leading-tight tracking-tight text-slate-900 sm:mb-2 sm:gap-x-3 sm:text-2xl">
          <span>{{ t('poster.hero.title') }}<span class="text-emerald-700">{{ t('poster.hero.titleAccent') }}</span></span>
          <span class="rounded-full bg-emerald-600 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-white">{{ t('poster.hero.badge') }}</span>
        </h2>

        <!-- Tri kroky nahradili odstavec s popisom: povedia to isté (nič sa
             nevypĺňa, účet až na konci) na jednom riadku namiesto troch. Na
             telefóne by sa zas rozpadli na tri riadky, tak sú skryté. -->
        <ol class="hidden flex-wrap gap-x-5 gap-y-1 text-sm text-slate-600 sm:flex">
          <li v-for="(step, index) in steps" :key="step" class="flex items-center gap-1.5">
            <span class="flex h-5 w-5 items-center justify-center rounded-full bg-emerald-600 text-[11px] font-semibold text-white">
              {{ index + 1 }}
            </span>
            {{ step }}
          </li>
        </ol>
      </div>

      <RouterLink to="/nahrat-plagat" class="btn btn-primary btn-lg shrink-0 shadow-sm">{{ t('poster.hero.cta') }}</RouterLink>
    </div>
  </section>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from '@/i18n'

const { t } = useI18n()

// Computed, nie obyčajné pole — pri prepnutí jazyka sa musia prekresliť aj kroky.
const steps = computed(() => [
  t('poster.hero.stepUpload'),
  t('poster.hero.stepReview'),
  t('poster.hero.stepSave'),
])
</script>
