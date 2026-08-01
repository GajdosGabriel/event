<template>
  <div class="mx-auto w-full max-w-[1320px] px-4 pt-6 pb-8">
    <PosterHero />

    <PublicEventList
      heading="Eventy"
      subheading="Prehľad verejných podujatí."
    />
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useHead } from '@vueuse/head'
import PublicEventList from '@/components/PublicEventList.vue'
import PosterHero from '@/components/poster/PosterHero.vue'
import { absoluteUrl, PUBLIC_EVENTS } from '@/utils/publicUrl'

/**
 * Homepage ukazuje ten istý katalóg ako `/podujatia`, len s hero sekciou.
 *
 * `canonical` preto smeruje na `/podujatia`: dve adresy s rovnakým zoznamom by
 * si inak konkurovali a vyhľadávač by si sám vybral, ktorú indexuje. Kanonická
 * je tá, ktorá má vlastný nadpis a na ktorú vedú landing stránky.
 */
const canonical = computed(() => absoluteUrl(PUBLIC_EVENTS))
const description = 'Prehľad nadchádzajúcich koncertov, divadiel, workshopov a ďalších podujatí.'

useHead(computed(() => ({
  title: 'Podujatia na Slovensku | Event',
  link: [{ rel: 'canonical', href: canonical.value }],
  meta: [
    { name: 'description', content: description },
    { property: 'og:type', content: 'website' },
    { property: 'og:title', content: 'Podujatia na Slovensku' },
    { property: 'og:description', content: description },
    { property: 'og:url', content: canonical.value },
  ],
})))
</script>
