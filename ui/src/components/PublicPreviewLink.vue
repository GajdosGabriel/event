<template>
  <!-- Zámerne `<a target="_blank">` a nie `<RouterLink>`: verejná stránka sa
       otvára **vedľa** dashboardu, nie namiesto neho. Kto kontroluje, ako
       záznam vidí návštevník, sa vzápätí vracia k rozrobenej práci — a keby
       sme mu ju prepísali, stratil by rozpísaný formulár. -->
  <a
    :href="to"
    target="_blank"
    rel="noopener"
    class="inline-flex items-center gap-1 text-sm text-slate-500 no-underline hover:text-blue-700"
  >
    {{ label || t('common.publicPreview') }}
    <span aria-hidden="true">↗</span>
  </a>
</template>

<script setup lang="ts">
/**
 * Nenápadný odkaz z administrácie na verejnú podobu záznamu — „ako to vidí
 * návštevník".
 *
 * Cestu si skladá volajúci (`publicEventPath`, `publicVenuePath`…), takže
 * komponent je jeden pre podujatia, miesta aj kanály a nemusí vedieť nič
 * o tom, čo práve zobrazuje.
 */
import { useI18n } from '@/i18n'

defineProps<{
  /** Verejná cesta, napr. z `publicEventPath(event)`. */
  to: string
  /** Vlastný popis, keď „Verejná stránka" nesedí. */
  label?: string
}>()

const { t } = useI18n()
</script>
