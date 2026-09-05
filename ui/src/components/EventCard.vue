<template>
  <!-- Univerzálna karta eventu — používa sa vo verejnom výpise, na stránke kanála aj miesta.
       Vizuál zámerne kopíruje riadok v admine/dashboarde (IndexRow): obrázok navrchu,
       pod ním názov a badge pre dátum/kanál/miesto v rovnakých farbách. -->
  <div class="group flex h-full flex-col overflow-hidden rounded-lg border border-slate-200 bg-white transition-shadow hover:shadow-sm">
    <RouterLink :to="link" class="block">
      <img
        v-if="imageUrl"
        :src="imageUrl"
        :srcset="srcset"
        :sizes="srcset ? CARD_IMAGE_SIZES : undefined"
        :alt="name"
        loading="lazy"
        decoding="async"
        class="block h-40 w-full object-cover"
      />
      <div v-else class="block h-40 w-full bg-slate-100" />
    </RouterLink>

    <div class="flex min-w-0 flex-1 flex-col gap-1.5 p-3">
      <h3 class="text-[0.97rem] leading-tight">
        <RouterLink :to="link" class="text-slate-900 no-underline hover:underline">{{ name }}</RouterLink>
      </h3>

      <div v-if="dateLabel || canalName || venueName" class="mt-auto flex flex-wrap items-center gap-1.5 pt-1">
        <span
          v-if="dateLabel"
          class="inline-flex items-center gap-1 rounded-md bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-200"
        >
          <svg class="h-3 w-3 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
          {{ dateLabel }}
        </span>
        <span
          v-if="canalName"
          class="inline-flex items-center rounded-full bg-teal-50 px-2 py-0.5 text-xs font-medium text-teal-700 ring-1 ring-inset ring-teal-200"
        >
          {{ canalName }}
        </span>
        <span
          v-if="venueName"
          class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600"
        >
          {{ venueName }}
        </span>
        <!-- Zbalená séria: vo výpise je z ôsmich repríz jedna karta, aby
             nevytlačili všetko ostatné. Ostatné termíny sú na detaile. -->
        <!-- Vzdialenosť od polohy návštevníka; ukazuje sa len so zapnutým
             filtrom „v mojom okolí". -->
        <span
          v-if="distanceLabel"
          class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-200"
        >
          <span aria-hidden="true">📍</span>{{ distanceLabel }}
        </span>
        <span
          v-if="seriesUpcomingCount"
          class="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-800 ring-1 ring-inset ring-amber-200"
        >
          {{ plural('public.series.more', seriesUpcomingCount) }}
        </span>
      </div>

      <!-- Obsahové štítky. Modrá patrí dátumu, tyrkysová kanálu — štítky sú
           preto fialové, aby sa dali odlíšiť na prvý pohľad. -->
      <div v-if="visibleTags.length" class="flex flex-wrap items-center gap-1">
        <span
          v-for="tag in visibleTags"
          :key="tag.id"
          class="inline-flex items-center gap-0.5 rounded-full bg-violet-50 px-2 py-0.5 text-[0.7rem] text-violet-700 ring-1 ring-inset ring-violet-200"
        >
          <span v-if="tag.emoji">{{ tag.emoji }}</span>
          {{ tag.name }}
        </span>
        <span v-if="hiddenTagCount" class="text-[0.7rem] text-slate-400">+{{ hiddenTagCount }}</span>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { TagItem } from '@/types'
import { publicEventPath } from '@/utils/publicUrl'
import { plural } from '@/i18n'

/** Viac čipov než toľko kartu rozbije — zvyšok sa zhrnie do „+N". */
const MAX_VISIBLE_TAGS = 3

/**
 * Šírky kopírujú mriežky výpisov: 1 stĺpec do 640px, 2 do 768px, 3 nad ním.
 * Nemusí sedieť na pixel — prehliadaču to stačí ako horný odhad.
 */
const CARD_IMAGE_SIZES = '(min-width: 768px) 33vw, (min-width: 640px) 50vw, 100vw'

const props = defineProps<{
  id: number
  name: string
  /** Slug do kanonickej adresy `/podujatia/{slug}-{id}`. */
  slug?: string | null
  imageUrl?: string | null
  /** Veľký variant; bez neho sa srcset nevykreslí a použije sa len `imageUrl`. */
  imageUrlLarge?: string | null
  dateLabel?: string | null
  canalName?: string | null
  venueName?: string | null
  /** Obsahové štítky; karta ukáže prvé tri. */
  tags?: TagItem[] | null
  /** Cieľ odkazu; predvolene detail eventu. */
  to?: string
  /** Koľko ďalších termínov série ešte len bude; 0 alebo null = odznak sa neukáže. */
  seriesUpcomingCount?: number | null
  /** Vzdialenosť od polohy návštevníka, už naformátovaná. */
  distanceLabel?: string | null
}>()

const link = computed(() => props.to ?? publicEventPath({ id: props.id, slug: props.slug }))

// Deskriptory zodpovedajú dlhšej hrane variantov z ImageVariantGenerator
// (thumb 320, large 1280). Pri portrétovom plagáte je skutočná šírka menšia,
// takže ide o horný odhad — prehliadač si vyberie skôr väčší súbor, nie horší.
const srcset = computed(() => {
  if (!props.imageUrl || !props.imageUrlLarge || props.imageUrlLarge === props.imageUrl) return undefined
  return `${props.imageUrl} 320w, ${props.imageUrlLarge} 1280w`
})
const visibleTags = computed(() => (props.tags ?? []).slice(0, MAX_VISIBLE_TAGS))
const hiddenTagCount = computed(() => Math.max(0, (props.tags?.length ?? 0) - MAX_VISIBLE_TAGS))
</script>
