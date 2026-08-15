<template>
  <div class="mx-auto w-full max-w-[1320px] px-4 pt-6 pb-8">
    <PosterHero />

    <!-- Jediný `h1` na stránke — vlastnú hlavičku homepage nemá, takže nadpis
         zoznamu musí zostať na prvej úrovni, inak osnova začne od `h2`. -->
    <PublicEventList
      :heading="t('public.home.heading')"
      :subheading="t('public.home.subheading')"
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
import { useI18n, currentLocale, localeTag } from '@/i18n'

const { t } = useI18n()

/**
 * Homepage ukazuje ten istý katalóg ako `/podujatia`, len s hero sekciou.
 *
 * `canonical` preto smeruje na `/podujatia`: dve adresy s rovnakým zoznamom by
 * si inak konkurovali a vyhľadávač by si sám vybral, ktorú indexuje. Kanonická
 * je tá, ktorá má vlastný nadpis a na ktorú vedú landing stránky.
 */
const canonical = computed(() => absoluteUrl(PUBLIC_EVENTS))
const description = computed(() => t('public.seo.homeDescription'))

/**
 * `WebSite` s `SearchAction` — vyhľadávaču povie, že portál má vlastné hľadanie
 * a na akej adrese. Bez neho nemá z čoho ponúknuť sitelinks searchbox.
 */
const websiteJsonLd = computed(() => ({
  '@context': 'https://schema.org',
  '@type': 'WebSite',
  name: 'Event',
  url: absoluteUrl('/'),
  inLanguage: currentLocale(),
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
  title: `${t('public.seo.homeTitle')} | Event`,
  link: [{ rel: 'canonical', href: canonical.value }],
  meta: [
    { name: 'description', content: description.value },
    { property: 'og:type', content: 'website' },
    { property: 'og:title', content: t('public.seo.homeTitle') },
    { property: 'og:description', content: description.value },
    { property: 'og:url', content: canonical.value },
    // og:locale chce podtržník, nie pomlčku („sk_SK“).
    { property: 'og:locale', content: localeTag().replace('-', '_') },
  ],
  script: [
    { key: 'website-jsonld', type: 'application/ld+json', innerHTML: JSON.stringify(websiteJsonLd.value) },
  ],
})))
</script>
