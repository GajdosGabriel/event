<template>
  <!-- Malé tlačidlo „Kúpiť lístok" / „Rezervovať" na karte podujatia.
       Druh aj text posiela backend (ticket_cta v objekte eventu), takže ho
       stačí vložiť do ktorejkoľvek karty — nič sa tu neprekladá ani nerozhoduje.
       Vedie na registračnú sekciu detailu (#registracia). -->
  <RouterLink
    :to="to"
    :aria-label="eventName ? `${cta.label} – ${eventName}` : cta.label"
    :title="compactOnMobile ? cta.label : undefined"
    class="group/cta inline-flex shrink-0 items-center gap-1.5 rounded-full py-1 pr-2 pl-2.5 text-xs font-semibold text-white no-underline shadow-sm transition hover:shadow-md focus-visible:outline-2 focus-visible:outline-offset-2 active:scale-[0.97]"
    :class="cta.kind === 'buy'
      ? 'bg-linear-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 focus-visible:outline-blue-600'
      : 'bg-linear-to-r from-emerald-500 to-teal-600 hover:from-emerald-600 hover:to-teal-700 focus-visible:outline-emerald-600'"
  >
    <!-- Lístok -->
    <svg class="h-3.5 w-3.5 shrink-0 opacity-90" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
      <path stroke-linecap="round" stroke-linejoin="round" d="M9 5v2m0 4v2m0 4v2M5 5h14a2 2 0 012 2v3a2 2 0 000 4v3a2 2 0 01-2 2H5a2 2 0 01-2-2v-3a2 2 0 000-4V7a2 2 0 012-2z"/>
    </svg>
    <span class="whitespace-nowrap" :class="{ 'hidden sm:inline': compactOnMobile }">{{ cta.label }}</span>
    <!-- » — pri hoveri sa posunie dopredu -->
    <svg class="h-3.5 w-3.5 shrink-0 transition-transform duration-200 group-hover/cta:translate-x-0.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
      <path stroke-linecap="round" stroke-linejoin="round" d="M6 7l5 5-5 5M13 7l5 5-5 5"/>
    </svg>
  </RouterLink>
</template>

<script setup lang="ts">
import type { EventTicketCta } from '@/types'

defineProps<{
  cta: EventTicketCta
  /** Cieľ — detail podujatia s kotvou na registráciu. */
  to: string
  /** Názov podujatia do aria-label, nech čítačka nepovie len „Rezervovať". */
  eventName?: string
  /** Na telefóne len ikona + », text až od `sm` — pre úzke riadky (agenda). */
  compactOnMobile?: boolean
}>()
</script>
