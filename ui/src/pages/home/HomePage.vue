<template>
  <div class="mx-auto w-full max-w-[1320px] px-4 pt-4 pb-8 sm:pt-6">
    <HomeHero />

    <!-- `h1` nesie hero sekcia, zoznam je preto až druhou úrovňou — dve `h1`
         na stránke by rozbili osnovu dokumentu. -->
    <PublicEventList
      :heading="t('public.home.heading')"
      :subheading="t('public.home.subheading')"
      heading-level="h2"
      :show-ticket-cta="false"
      :filters-to="`#${HERO_FILTERS_ID}`"
    />
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useHead } from '@vueuse/head'
import PublicEventList from '@/components/PublicEventList.vue'
import HomeHero, { HERO_FILTERS_ID } from '@/components/home/HomeHero.vue'
import { absoluteUrl, PUBLIC_EVENTS } from '@/utils/publicUrl'
import { useI18n, currentLocale, localeTag } from '@/i18n'

const { t } = useI18n()

/**
 * Homepage je kanonická sama na sebe, hoci ukazuje ten istý katalóg ako
 * `/akcie`. Do 29. 10. 2026 smerovala na `/akcie` — koreň domény tak nebol
 * v indexe a Google nemal kde nájsť `WebSite`, podľa ktorého vyberá názov webu
 * vo výsledkoch. Duplicite s výpisom bráni iný titulok a popis.
 *
 * Crawler túto stránku nevidí (dostane bot-render, `seo.home` v api/); znenie
 * a JSON-LD tu musia s ním sedieť.
 */
const canonical = computed(() => absoluteUrl('/'))
const description = computed(() => t('public.home.heroLead'))
const organizationId = computed(() => `${absoluteUrl('/')}#organization`)

/**
 * `WebSite` s `SearchAction` — vyhľadávaču povie, že portál má vlastné hľadanie
 * a na akej adrese. Bez neho nemá z čoho ponúknuť sitelinks searchbox.
 */
const websiteJsonLd = computed(() => ({
  '@context': 'https://schema.org',
  '@type': 'WebSite',
  name: 'Tixio',
  url: absoluteUrl('/'),
  inLanguage: currentLocale(),
  publisher: { '@id': organizationId.value },
  potentialAction: {
    '@type': 'SearchAction',
    target: {
      '@type': 'EntryPoint',
      urlTemplate: `${absoluteUrl(PUBLIC_EVENTS)}?q={search_term_string}`,
    },
    'query-input': 'required name=search_term_string',
  },
}))

const organizationJsonLd = computed(() => ({
  '@context': 'https://schema.org',
  '@type': 'Organization',
  '@id': organizationId.value,
  name: 'Tixio',
  url: absoluteUrl('/'),
  logo: absoluteUrl('/icon-512.png'),
}))

useHead(computed(() => ({
  title: `${t('public.seo.siteTitle')} | Tixio`,
  link: [{ rel: 'canonical', href: canonical.value }],
  meta: [
    { name: 'description', content: description.value },
    { property: 'og:type', content: 'website' },
    { property: 'og:title', content: t('public.seo.siteTitle') },
    { property: 'og:description', content: description.value },
    { property: 'og:url', content: canonical.value },
    // og:locale chce podtržník, nie pomlčku („sk_SK“).
    { property: 'og:locale', content: localeTag().replace('-', '_') },
  ],
  script: [
    { key: 'website-jsonld', type: 'application/ld+json', innerHTML: JSON.stringify(websiteJsonLd.value) },
    { key: 'organization-jsonld', type: 'application/ld+json', innerHTML: JSON.stringify(organizationJsonLd.value) },
  ],
})))
</script>
