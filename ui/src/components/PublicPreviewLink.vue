<template>
  <!-- Zámerne `<a target="_blank">` a nie `<RouterLink>`: verejná stránka sa
       otvára **vedľa** dashboardu, nie namiesto neho. Kto kontroluje, ako
       záznam vidí návštevník, sa vzápätí vracia k rozrobenej práci — a keby
       sme mu ju prepísali, stratil by rozpísaný formulár. -->
  <a :href="to" target="_blank" rel="noopener" class="action-btn action-btn-feature">
    {{ label || t('common.publicPreview') }}<AppIcon name="externalLink" class="h-4 w-4" />
  </a>
</template>

<script setup lang="ts">
/**
 * Odkaz z administrácie na verejnú podobu záznamu — „ako to vidí návštevník".
 *
 * Vyzerá ako ostatné zapnuté funkcie v pruhu nad záznamom (`Lístky`,
 * `Check-in`): šedá šípka za textom sa v tom rade strácala natoľko, že ju
 * nikto nenašiel. Ikona je z rovnakého registra ako pri susedných tlačidlách,
 * takže sa im zhoduje veľkosťou aj ťahom — stojí ale za textom, nie pred ním:
 * susedia ikonou uvádzajú, kam vedú, tu je to poznámka „otvorí sa inde".
 *
 * Cestu si skladá volajúci (`publicEventPath`, `publicVenuePath`…), takže
 * komponent je jeden pre podujatia, miesta aj kanály a nemusí vedieť nič
 * o tom, čo práve zobrazuje.
 */
import AppIcon from '@/components/AppIcon.vue'
import { useI18n } from '@/i18n'

defineProps<{
  /** Verejná cesta, napr. z `publicEventPath(event)`. */
  to: string
  /** Vlastný popis, keď „Verejná stránka" nesedí. */
  label?: string
}>()

const { t } = useI18n()
</script>
