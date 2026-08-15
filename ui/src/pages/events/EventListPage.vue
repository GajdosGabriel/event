<template>
  <div class="mx-auto w-full max-w-[1320px] px-4 pt-6 pb-8">
    <PublicEventList
      :heading="heading"
      :subheading="description"
      :municipality="municipalityFilter"
      :tags="tagFilter"
      :range="rangeFilter"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useHead } from '@vueuse/head'
import PublicEventList from '@/components/PublicEventList.vue'
import { showPublicMunicipality } from '@/api/municipalities'
import { indexTags } from '@/api/tags'
import { absoluteUrl, publicMunicipalityPath, publicTagPath, publicWeekendPath, PUBLIC_EVENTS } from '@/utils/publicUrl'
import { useI18n } from '@/i18n'

const { t } = useI18n()

/**
 * Verejný katalóg podujatí a jeho landing varianty.
 *
 * Každý variant má vlastnú adresu, `title`, popis a `canonical` — to je celý
 * dôvod jeho existencie. Predtým bol zoznam len homepage s query parametrami,
 * takže „podujatia v Košiciach" ani „tento víkend" nemali čo indexovať.
 *
 * Serverom vykreslenú podobu tých istých adries pre crawlerov vracia
 * `PrerenderController` na backende; nadpisy a popisy sú zámerne rovnaké.
 */
const props = withDefaults(defineProps<{
  variant?: 'all' | 'weekend' | 'municipality' | 'tag'
  slug?: string
}>(), {
  variant: 'all',
  slug: '',
})

/** Názov obce/štítku poznáme až po dotaze — do vtedy stojí nadpis na slugu. */
const resolvedName = ref<string | null>(null)

const municipalityFilter = computed(() => (props.variant === 'municipality' ? props.slug : null))
const tagFilter = computed(() => (props.variant === 'tag' ? props.slug : null))
const rangeFilter = computed(() => (props.variant === 'weekend' ? 'weekend' : null))

const label = computed(() => resolvedName.value ?? props.slug)

const heading = computed(() => {
  switch (props.variant) {
    case 'weekend': return t('public.seo.listHeadingWeekend')
    case 'municipality':
    case 'tag': return t('public.seo.listHeadingLabel', { label: label.value })
    default: return t('public.seo.listHeading')
  }
})

const title = computed(() => {
  switch (props.variant) {
    case 'weekend': return t('public.seo.listHeadingWeekend')
    case 'municipality': return t('public.seo.listTitleMunicipality', { label: label.value })
    case 'tag': return t('public.seo.listTitleTag', { label: label.value })
    default: return t('public.seo.homeTitle')
  }
})

const description = computed(() => {
  switch (props.variant) {
    case 'weekend': return t('public.seo.listDescriptionWeekend')
    case 'municipality': return t('public.seo.listDescriptionMunicipality', { label: label.value })
    case 'tag': return t('public.seo.listDescriptionTag', { label: label.value })
    default: return t('public.seo.homeDescription')
  }
})

const canonical = computed(() => {
  switch (props.variant) {
    case 'weekend': return publicWeekendPath()
    case 'municipality': return publicMunicipalityPath(props.slug)
    case 'tag': return publicTagPath(props.slug)
    default: return PUBLIC_EVENTS
  }
})

useHead(computed(() => ({
  title: `${title.value} | Event`,
  link: [{ rel: 'canonical', href: absoluteUrl(canonical.value) }],
  meta: [
    { name: 'description', content: description.value },
    { property: 'og:type', content: 'website' },
    { property: 'og:title', content: title.value },
    { property: 'og:description', content: description.value },
    { property: 'og:url', content: absoluteUrl(canonical.value) },
  ],
})))

/**
 * Slug sám o sebe nie je nadpis („bratislava"), preto sa dotiahne čitateľný
 * názov. Zlyhanie je neškodné — stránka ostane pri slugu a zoznam sa načíta
 * tak či tak.
 */
async function resolveName() {
  resolvedName.value = null

  try {
    if (props.variant === 'municipality') {
      resolvedName.value = (await showPublicMunicipality(props.slug)).name
    } else if (props.variant === 'tag') {
      const tags = (await indexTags()).flatMap((group) => group.tags)
      resolvedName.value = tags.find((tag) => tag.slug === props.slug)?.name ?? null
    }
  } catch {
    resolvedName.value = null
  }
}

watch(() => [props.variant, props.slug], resolveName)
onMounted(resolveName)
</script>
