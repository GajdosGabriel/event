<template>
  <!-- Plocha ostáva svetlá — tmavý blok by hero opticky odrezal od zoznamu pod
       ním. Dôraz nesie sýta zelená (pruh, štítok, číslice krokov), nie výška:
       hero stojí nad zoznamom podujatí a nesmie ho odtlačiť pod ohyb. Ilustrácia
       preto zostala až na stránke nahrávania, kde má miesto. -->
  <section class="mb-5 overflow-hidden rounded-xl border border-emerald-200 bg-emerald-50">
    <div class="h-1 w-full bg-emerald-600"></div>

    <div class="flex flex-wrap items-center justify-between gap-x-8 gap-y-4 px-5 py-4 sm:px-6">
      <div class="min-w-0">
        <h2 class="mb-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-xl font-semibold leading-tight text-slate-900 sm:text-2xl">
          <span>Nahrajte plagát<span class="text-emerald-700">, o všetko ostatné sa postaráme</span></span>
          <span class="rounded-full bg-emerald-600 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-white">Nové</span>
        </h2>

        <!-- Tri kroky nahradili odstavec s popisom: povedia to isté (nič sa
             nevypĺňa, účet až na konci) na jednom riadku namiesto troch. -->
        <ol class="mb-2 flex flex-wrap gap-x-5 gap-y-1 text-sm text-slate-600">
          <li v-for="(step, index) in steps" :key="step" class="flex items-center gap-1.5">
            <span class="flex h-5 w-5 items-center justify-center rounded-full bg-emerald-600 text-[11px] font-semibold text-white">
              {{ index + 1 }}
            </span>
            {{ step }}
          </li>
        </ol>

        <!-- Podrobnosti sú zabalené: hero má byť nízky, ale človek, ktorý o tom
             počuje prvýkrát, si vysvetlenie musí vedieť rozkliknúť. -->
        <button
          type="button"
          class="flex items-center gap-1 text-sm font-medium text-emerald-800 hover:text-emerald-900"
          :aria-expanded="expanded"
          aria-controls="poster-hero-details"
          @click="expanded = !expanded"
        >
          Ako to funguje?
          <svg
            class="h-3.5 w-3.5 transition-transform"
            :class="expanded ? 'rotate-180' : ''"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2.5"
            stroke-linecap="round"
            stroke-linejoin="round"
            aria-hidden="true"
          >
            <path d="M6 9l6 6 6-6" />
          </svg>
        </button>

        <p v-if="expanded" id="poster-hero-details" class="mt-2 max-w-xl text-sm leading-relaxed text-slate-600">
          Nahrajte PDF, Word, fotku plagátu alebo len text pozvánky. Prečítame z neho termín, miesto
          aj organizátora, napojíme podujatie na existujúce miesto či kanál a ukážeme vám, čo sme
          našli a čo treba doplniť. Účet si vypýtame až na konci, pri ukladaní.
        </p>
      </div>

      <div class="flex shrink-0 flex-col items-start gap-1.5 sm:items-end">
        <RouterLink to="/nahrat-plagat" class="btn btn-primary">Nahrať plagát</RouterLink>
        <span class="text-xs text-slate-500">PDF, Word, obrázok alebo text</span>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
import { ref } from 'vue'

const steps = ['Nahráte plagát', 'Ukážeme, čo sme našli', 'Uložíte podujatie']

const expanded = ref(false)
</script>
