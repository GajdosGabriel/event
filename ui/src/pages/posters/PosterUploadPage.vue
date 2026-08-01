<template>
  <div class="mx-auto w-full max-w-[820px] px-4 pt-6 pb-10">
    <header class="mb-5">
      <h1 class="mb-1 text-2xl text-slate-900">Nahrajte plagát, o všetko ostatné sa postaráme</h1>
      <p class="text-slate-500">
        Z plagátu prečítame termín, miesto aj organizátora a pripravíme z nich podujatie.
        Účet budeme potrebovať až pri ukladaní.
      </p>
    </header>

    <PosterUploadWizard />

    <p class="mt-4 text-xs text-slate-400">
      Nahratý súbor sa nikde nezverejní, kým podujatie sami nepublikujete. Nedokončené nahratia po
      siedmich dňoch automaticky mažeme.
    </p>
  </div>
</template>

<script setup lang="ts">
import PosterUploadWizard from '@/components/poster/PosterUploadWizard.vue'
</script>
