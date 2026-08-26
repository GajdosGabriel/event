<template>
  <!-- Zámerne odkaz do novej karty a nie `RouterLink`: verejná stránka sa
       otvára **vedľa** dashboardu, nie namiesto neho. Kto kontroluje, ako
       záznam vidí návštevník, sa vzápätí vracia k rozrobenej práci — a keby
       sme mu ju prepísali, stratil by rozpísaný formulár. -->
  <ActionButton :href="to" :variant="variant" :label="label || t('common.publicPreview')" />
</template>

<script setup lang="ts">
/**
 * Odkaz z administrácie na verejnú podobu záznamu — „ako to vidí návštevník".
 *
 * Vzhľad si komponent nekreslí sám, dáva ho `ActionButton` — vedľa neho vždy
 * stoja susedia z toho istého pruhu a musí im sedieť. Preto `variant`: v pruhu
 * nad detailom sú susedia modré tlačidlá funkcií (`feature`), v sekcii lístkov
 * je vedľa prepínač kariet a tam sa odkaz kreslí ako karta (`tab`) — inak by
 * modrá pilulka vedľa šedých kariet vyzerala ako cudzí prvok.
 *
 * Cestu si skladá volajúci (`publicEventPath`, `publicVenuePath`…), takže
 * komponent je jeden pre podujatia, miesta aj kanály a nemusí vedieť nič
 * o tom, čo práve zobrazuje.
 */
import ActionButton from '@/components/ActionButton.vue'
import { useI18n } from '@/i18n'

withDefaults(defineProps<{
  /** Verejná cesta, napr. z `publicEventPath(event)`. */
  to: string
  /** Vlastný popis, keď „Verejná stránka" nesedí. */
  label?: string
  /** Podľa toho, čo stojí v pruhu vedľa — pozri komentár vyššie. */
  variant?: 'feature' | 'tab'
}>(), { variant: 'feature' })

const { t } = useI18n()
</script>
