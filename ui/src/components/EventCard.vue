<template>
  <!-- Univerzálna karta eventu — používa sa vo verejnom výpise, na stránke kanála aj miesta.
       Obrázok nesie kartu; údaje pod ním sú tichý text s ikonami, nie rad
       farebných odznakov — pri šiestich farbách sa strácal názov. -->
  <article class="group relative flex h-full flex-col overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-900/5 transition duration-200 hover:-translate-y-0.5 hover:shadow-lg">
    <div class="relative aspect-[4/3] overflow-hidden bg-slate-100">
      <img
        v-if="imageUrl"
        :src="imageUrl"
        :srcset="srcset"
        :sizes="srcset ? CARD_IMAGE_SIZES : undefined"
        :alt="name"
        loading="lazy"
        decoding="async"
        class="block h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
      />
      <div v-else class="flex h-full w-full items-center justify-center bg-linear-to-br from-slate-100 to-slate-200 text-slate-300">
        <AppIcon name="calendar" class="h-10 w-10" />
      </div>

      <!-- Kalendárny lístok — dátum sa dá prečítať skôr než názov. -->
      <div
        v-if="tile"
        class="absolute top-3 left-3 flex w-12 flex-col items-center rounded-xl bg-white/95 py-1 leading-none shadow-md backdrop-blur-sm"
        aria-hidden="true"
      >
        <span class="text-[10px] font-bold uppercase tracking-wide text-rose-600">{{ tile.month }}</span>
        <span class="mt-0.5 text-lg font-extrabold text-slate-900">{{ tile.day }}</span>
      </div>

      <!-- Kúpiť / rezervovať lístok — vpravo hore cez obrázok, len keď to
           backend ponúka (ticket_cta). Nad roztiahnutým odkazom názvu. -->
      <EventTicketCta
        v-if="ticketCta"
        :cta="ticketCta"
        :to="`${link}#registracia`"
        :event-name="name"
        class="absolute top-3 right-3 z-10 shadow-md ring-1 ring-white/40"
      />
    </div>

    <div class="flex min-w-0 flex-1 flex-col gap-2 p-4">
      <!-- Celá karta je klikateľná cez roztiahnutý odkaz (after:inset-0),
           nie cez obalový <a> — <a> v <a> by nebolo platné HTML. -->
      <h3 class="line-clamp-2 text-base font-bold leading-snug text-slate-900">
        <RouterLink :to="link" class="text-inherit no-underline after:absolute after:inset-0 group-hover:text-blue-700">{{ name }}</RouterLink>
      </h3>

      <ul class="mt-auto space-y-1 text-sm text-slate-500">
        <li v-if="dateLabel" class="flex items-center gap-1.5">
          <AppIcon name="calendar" class="h-3.5 w-3.5 shrink-0 text-slate-400" />
          <span class="truncate">{{ dateLabel }}</span>
        </li>
        <li v-if="venueName || distanceLabel" class="flex items-center gap-1.5">
          <AppIcon name="mapPin" class="h-3.5 w-3.5 shrink-0 text-slate-400" />
          <span class="truncate">{{ venueName }}</span>
          <!-- Vzdialenosť od polohy návštevníka; len so zapnutým „v mojom okolí". -->
          <span v-if="distanceLabel" class="shrink-0 font-semibold text-emerald-700">{{ distanceLabel }}</span>
        </li>
        <li v-if="canalName" class="truncate text-xs font-medium text-slate-400">{{ canalName }}</li>
      </ul>

      <div v-if="visibleTags.length || seriesUpcomingCount" class="flex flex-wrap items-center gap-1">
        <!-- Zbalená séria: vo výpise je z ôsmich repríz jedna karta, aby
             nevytlačili všetko ostatné. Ostatné termíny sú na detaile. -->
        <span
          v-if="seriesUpcomingCount"
          class="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-800"
        >{{ plural('public.series.more', seriesUpcomingCount) }}</span>
        <span
          v-for="tag in visibleTags"
          :key="tag.id"
          class="inline-flex items-center gap-0.5 rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600"
        >
          <span v-if="tag.emoji">{{ tag.emoji }}</span>
          {{ tag.name }}
        </span>
        <span v-if="hiddenTagCount" class="text-xs text-slate-400">+{{ hiddenTagCount }}</span>
      </div>
    </div>
  </article>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { EventTicketCta as TicketCta, TagItem } from '@/types'
import EventTicketCta from '@/components/EventTicketCta.vue'
import AppIcon from '@/components/AppIcon.vue'
import { dateTile } from '@/utils/dateFormat'
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
  /** Slug do kanonickej adresy `/akcie/{id}/{slug}`. */
  slug?: string | null
  imageUrl?: string | null
  /** Veľký variant; bez neho sa srcset nevykreslí a použije sa len `imageUrl`. */
  imageUrlLarge?: string | null
  dateLabel?: string | null
  /** Začiatok podujatia — z neho sa kreslí kalendárny lístok na obrázku. */
  startAt?: string | null
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
  /** Tlačidlo „Kúpiť lístok" / „Rezervovať" vpravo hore; null = žiadne. */
  ticketCta?: TicketCta | null
}>()

const link = computed(() => props.to ?? publicEventPath({ id: props.id, slug: props.slug }))

// Deskriptory zodpovedajú dlhšej hrane variantov z ImageVariantGenerator
// (thumb 320, large 1280). Pri portrétovom plagáte je skutočná šírka menšia,
// takže ide o horný odhad — prehliadač si vyberie skôr väčší súbor, nie horší.
const srcset = computed(() => {
  if (!props.imageUrl || !props.imageUrlLarge || props.imageUrlLarge === props.imageUrl) return undefined
  return `${props.imageUrl} 320w, ${props.imageUrlLarge} 1280w`
})
const tile = computed(() => (props.startAt ? dateTile(props.startAt) : null))
const visibleTags = computed(() => (props.tags ?? []).slice(0, MAX_VISIBLE_TAGS))
const hiddenTagCount = computed(() => Math.max(0, (props.tags?.length ?? 0) - MAX_VISIBLE_TAGS))
</script>
