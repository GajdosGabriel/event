<template>
  <div class="mx-auto w-full max-w-[1320px] px-4 pt-6 pb-8">
    <PosterHero />

    <!-- Jediný `h1` na stránke — vlastnú hlavičku homepage nemá, takže nadpis
         zoznamu musí zostať na prvej úrovni, inak osnova začne od `h2`. -->
    <PublicEventList
      heading="Nadchádzajúce podujatia"
      subheading="Zoradené podľa najbližšieho termínu."
      heading-level="h1"
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

/**
 * `WebSite` s `SearchAction` — vyhľadávaču povie, že portál má vlastné hľadanie
 * a na akej adrese. Bez neho nemá z čoho ponúknuť sitelinks searchbox.
 */
const websiteJsonLd = computed(() => ({
  '@context': 'https://schema.org',
  '@type': 'WebSite',
  name: 'Event',
  url: absoluteUrl('/'),
  inLanguage: 'sk',
  potentialAction: {
    '@type': 'SearchAction',
    target: {
      '@type': 'EntryPoint',
      urlTemplate: `${absoluteUrl(PUBLIC_EVENTS)}?q={search_term_string}`,
    },
    'query-input': 'required name=search_term_string',
  },
}))

useHead(computed(() => ({
  title: 'Podujatia na Slovensku | Event',
  link: [{ rel: 'canonical', href: canonical.value }],
  meta: [
    { name: 'description', content: description },
    { property: 'og:type', content: 'website' },
    { property: 'og:title', content: 'Podujatia na Slovensku' },
    { property: 'og:description', content: description },
    { property: 'og:url', content: canonical.value },
    { property: 'og:locale', content: 'sk_SK' },
  ],
  script: [
    { key: 'website-jsonld', type: 'application/ld+json', innerHTML: JSON.stringify(websiteJsonLd.value) },
  ],
})))
</script>
