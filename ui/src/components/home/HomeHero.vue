<template>
  <!-- `overflow-clip`, nie `overflow-hidden`: skrytý presah sa dá rolovať a
       prehliadač by scénu pri fokuse na filter posunul za plagátmi. -->
  <!-- Úvodná scéna homepage. Tmavá plocha s plagátmi skutočných podujatí —
       namiesto ilustrácie ukazuje hneď to, čo portál ponúka. Výška ostáva
       pri zemi (na telefóne len nadpis, hľadanie a pás s plagátom), aby
       zoznam pod ňou nezačínal až na druhej obrazovke. -->
  <section class="relative isolate mb-8 overflow-clip rounded-3xl bg-slate-950 text-white shadow-xl shadow-slate-900/10 sm:rounded-[2rem]">
    <!-- Pozadie: dve farebné žiary a jemný raster. Čisto dekoratívne. -->
    <div class="pointer-events-none absolute inset-0 -z-10" aria-hidden="true">
      <div class="hero-glow absolute -top-32 -left-24 h-96 w-96 rounded-full bg-rose-500/35 blur-3xl"></div>
      <div class="hero-glow hero-glow-slow absolute -bottom-40 left-1/3 h-[28rem] w-[28rem] rounded-full bg-indigo-500/35 blur-3xl"></div>
      <div class="absolute -top-20 right-0 h-80 w-80 rounded-full bg-amber-400/15 blur-3xl"></div>
      <div class="hero-dots absolute inset-0"></div>
    </div>

    <!-- Plagáty. Tri stĺpce bežia proti sebe; každý nesie svoj zoznam dvakrát,
         takže posun o polovicu výšky nadväzuje bez švu. Pre čítačku aj
         klávesnicu je to duplicita zoznamu pod hero sekciou, preto je celý
         blok skrytý a odkazy sú mimo poradia tabulátora. -->
    <div
      v-if="columns.length"
      class="hero-marquee absolute inset-y-0 right-0 hidden w-[46%] max-w-[560px] overflow-clip lg:block"
      aria-hidden="true"
    >
      <div class="absolute -inset-y-24 right-[-4%] left-[6%] grid rotate-[8deg] grid-cols-3 gap-3">
        <div
          v-for="(column, index) in columns"
          :key="index"
          class="hero-marquee-col"
          :class="{ 'hero-marquee-col-reverse': index === 1 }"
          :style="{ animationDuration: `${COLUMN_SECONDS[index]}s` }"
        >
          <ul v-for="copy in 2" :key="copy" class="m-0 flex list-none flex-col gap-3 p-0 pb-3">
            <li v-for="(event, i) in column" :key="`${event.id}-${i}`">
              <RouterLink
                :to="publicEventPath(event)"
                tabindex="-1"
                class="group/poster relative block overflow-hidden rounded-2xl bg-white/5 shadow-lg ring-1 ring-white/10"
              >
                <img
                  :src="event.imageUrl!"
                  alt=""
                  loading="lazy"
                  decoding="async"
                  class="block aspect-[3/4] w-full object-cover transition duration-500 group-hover/poster:scale-105"
                />
                <span class="absolute inset-x-0 bottom-0 line-clamp-2 bg-linear-to-t from-slate-950/95 to-transparent px-2.5 pt-10 pb-2 text-[11px] leading-tight font-semibold text-white opacity-0 transition-opacity duration-300 group-hover/poster:opacity-100">
                  {{ event.name }}
                </span>
              </RouterLink>
            </li>
          </ul>
        </div>
      </div>
      <!-- Prechod do tmavej plochy — text vľavo nesmie súťažiť s plagátmi. -->
      <div class="absolute inset-y-0 left-0 w-40 bg-linear-to-r from-slate-950 to-transparent"></div>
    </div>

    <div class="relative px-5 pt-8 pb-6 sm:px-10 sm:pt-12 sm:pb-8 lg:max-w-[58%] lg:pt-14">
      <!-- Miesto drží aj pred načítaním počtu — inak by nadpis poskočil. -->
      <p
        :class="{ invisible: !total }"
        class="mb-4 inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-xs font-semibold text-white/90 ring-1 ring-inset ring-white/15 backdrop-blur-sm"
      >
        <span class="relative flex h-2 w-2">
          <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
          <span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-400"></span>
        </span>
        {{ plural('public.home.heroCount', total) }}
      </p>

      <h1 class="text-3xl leading-[1.08] font-extrabold tracking-tight text-balance sm:text-5xl lg:text-6xl">
        {{ t('public.home.heroTitle') }}
        <span class="bg-linear-to-r from-amber-200 via-rose-300 to-fuchsia-300 bg-clip-text text-transparent">{{ t('public.home.heroTitleAccent') }}</span>
      </h1>

      <p class="mt-4 hidden max-w-xl text-base leading-relaxed text-slate-300 sm:block sm:text-lg">
        {{ t('public.home.heroLead') }}
      </p>

      <!-- Rýchle filtre. Kreslí ich sem zoznam podujatí (Teleport v
           PublicEventList) — scéna im dáva len miesto a tmavý vzhľad. -->
      <div :id="HERO_FILTERS_ID" class="chips-on-dark mt-6 max-w-xl"></div>
    </div>

    <!-- Nahratie plagátu. Sklenený pás na spodku scény: pre organizátora je to
         hlavná akcia portálu, ale návštevníkovi nesmie zavadzať v hľadaní. -->
    <div class="relative border-t border-white/10 bg-white/5 backdrop-blur-md">
      <div class="flex flex-wrap items-center justify-between gap-x-8 gap-y-3 px-5 py-4 sm:px-10">
        <div class="min-w-0">
          <h2 class="flex flex-wrap items-center gap-x-2.5 gap-y-1 text-base leading-tight font-bold sm:text-lg">
            <span>{{ t('poster.hero.title') }}<span class="text-emerald-300">{{ t('poster.hero.titleAccent') }}</span></span>
            <span class="rounded-full bg-emerald-400 px-2 py-0.5 text-[10px] font-bold tracking-wide text-emerald-950 uppercase">{{ t('poster.hero.badge') }}</span>
          </h2>
          <!-- Na telefóne by sa kroky rozpadli na tri riadky, tak sú skryté —
               sprievodca ich na stránke nahrávania zopakuje. -->
          <ol class="mt-1.5 hidden flex-wrap gap-x-5 gap-y-1 text-sm text-slate-300 sm:flex">
            <li v-for="(step, index) in steps" :key="step" class="flex items-center gap-1.5">
              <span class="flex h-5 w-5 items-center justify-center rounded-full bg-white/15 text-[11px] font-semibold text-white ring-1 ring-inset ring-white/20">
                {{ index + 1 }}
              </span>
              {{ step }}
            </li>
          </ol>
        </div>

        <RouterLink
          to="/nahrat-plagat"
          class="inline-flex h-11 shrink-0 items-center gap-2 rounded-xl bg-white px-5 text-sm font-bold text-slate-900 no-underline shadow-lg transition hover:bg-emerald-300"
        >
          <AppIcon name="upload" class="h-4 w-4" />
          {{ t('poster.hero.cta') }}
        </RouterLink>
      </div>
    </div>
  </section>
</template>

<script lang="ts">
/** Kam si homepage nechá vykresliť rýchle filtre zo zoznamu podujatí. */
export const HERO_FILTERS_ID = 'home-hero-filters'
</script>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { indexEvents } from '@/api/events'
import type { EventItem } from '@/types'
import AppIcon from '@/components/AppIcon.vue'
import { publicEventPath } from '@/utils/publicUrl'
import { useI18n } from '@/i18n'

const { t, plural } = useI18n()

/** Koľko podujatí si hero pýta; plagátov z nich býva o niečo menej. */
const SAMPLE_SIZE = 24
const COLUMN_COUNT = 3
/** Stĺpec kratší než scéna by pri slučke ukázal prázdne miesto. */
const MIN_PER_COLUMN = 4
/** Každý stĺpec inou rýchlosťou, inak pôsobia ako jeden blok. */
const COLUMN_SECONDS = [48, 62, 54]

const events = ref<EventItem[]>([])
const total = ref(0)

// Computed, nie obyčajné pole — pri prepnutí jazyka sa musia prekresliť aj kroky.
const steps = computed(() => [
  t('poster.hero.stepUpload'),
  t('poster.hero.stepReview'),
  t('poster.hero.stepSave'),
])

/**
 * Plagáty rozdelené do stĺpcov. Termíny série zdieľajú jeden obrázok, preto
 * sa berie každý len raz — tri rovnaké plagáty vedľa seba vyzerajú ako chyba.
 */
const columns = computed<EventItem[][]>(() => {
  const seen = new Set<string>()
  const posters = events.value.filter((event) => {
    if (!event.imageUrl || seen.has(event.imageUrl)) return false
    seen.add(event.imageUrl)
    return true
  })
  if (posters.length < COLUMN_COUNT) return []

  const result: EventItem[][] = Array.from({ length: COLUMN_COUNT }, () => [])
  posters.forEach((event, index) => result[index % COLUMN_COUNT].push(event))

  return result.map((column) => {
    const filled = [...column]
    while (filled.length < MIN_PER_COLUMN) filled.push(...column)
    return filled
  })
})

onMounted(async () => {
  try {
    const res = await indexEvents('public', { list: 'upcoming', per_page: SAMPLE_SIZE })
    events.value = res.data
    total.value = res.meta.total ?? res.data.length
  } catch { /* hero ostane bez plagátov — filtre fungujú aj tak */ }
})
</script>

<style scoped>
.hero-dots {
  background-image: radial-gradient(rgb(255 255 255 / 0.09) 1px, transparent 1px);
  background-size: 22px 22px;
  mask-image: linear-gradient(to bottom, black, transparent 85%);
}

.hero-glow { animation: hero-drift 18s ease-in-out infinite alternate; }
.hero-glow-slow { animation-duration: 26s; animation-direction: alternate-reverse; }

.hero-marquee {
  mask-image: linear-gradient(to bottom, transparent, black 14%, black 80%, transparent);
}
.hero-marquee-col { animation: hero-scroll linear infinite; will-change: transform; }
.hero-marquee-col-reverse { animation-direction: reverse; }
/* Plagát sa dá chytiť: pod kurzorom sa pás zastaví. */
.hero-marquee:hover .hero-marquee-col { animation-play-state: paused; }

@keyframes hero-scroll {
  from { transform: translateY(0); }
  to { transform: translateY(-50%); }
}
@keyframes hero-drift {
  from { transform: translate3d(0, 0, 0) scale(1); }
  to { transform: translate3d(40px, 24px, 0) scale(1.15); }
}

@media (prefers-reduced-motion: reduce) {
  .hero-glow,
  .hero-marquee-col { animation: none; }
}
</style>
