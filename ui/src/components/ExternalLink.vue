<template>
  <a
    :href="href"
    target="_blank"
    rel="noopener noreferrer nofollow"
    @click="report"
    @auxclick="onAuxClick"
  >
    <slot>{{ label }}</slot>
  </a>
</template>

<script setup lang="ts">
/**
 * Odkaz mimo portál — jediné miesto, cez ktoré sa cudzie adresy zobrazujú.
 *
 * Okrem bezpečnostných atribútov (`noopener`, `nofollow`) rieši jednu vec:
 * pri kliknutí povie API, že o tento odkaz je záujem, nech ho prednostne
 * overí. Nie je to hlásenie chyby — po kliknutí prehliadač odíde na cudziu
 * doménu a či sa tam niečo načítalo, sa odtiaľto zistiť nedá. Rozhodne až
 * sonda na serveri; majiteľovi príde e-mail len vtedy, keď odkaz naozaj
 * nefunguje, a s cestou, kde na portáli visí.
 *
 * Ohlásenie je nepovinné: bez `target` sa komponent správa ako obyčajný odkaz
 * (tak sa dá použiť aj tam, kde adresa k žiadnemu záznamu nepatrí).
 */
import { computed } from 'vue'
import { reportLinkClick } from '@/api/linkReports'
import type { LinkReportTarget } from '@/types'

const props = defineProps<{
  href: string
  /** Typ a id záznamu, ktorému adresa patrí. Bez nich sa nič nehlási. */
  target?: LinkReportTarget
  targetId?: number
  /** Ktorý údaj záznamu to je — dnes vždy web. */
  attribute?: string
}>()

/** Adresa bez schémy — tak sa web bežne píše aj číta. */
const label = computed(() => props.href.replace(/^https?:\/\//, '').replace(/\/$/, ''))

function report(): void {
  if (!props.target || !props.targetId) return

  reportLinkClick(props.target, props.targetId, undefined, props.attribute)
}

/** Otvorenie kolieskom myši je tiež kliknutie — `click` ho nezachytí. */
function onAuxClick(event: MouseEvent): void {
  if (event.button === 1) report()
}
</script>
