<template>
  <!-- Súhrn odpovedí dotazníka: pri výbere počty (a obsadenosť, tržba) po
       možnostiach, pri ostatných otázkach počet vyplnených. -->
  <div class="grid gap-3" :class="{ 'md:grid-cols-2 xl:grid-cols-3': !compact }">
    <div v-for="question in summary" :key="question.id" :class="compact ? '' : 'rounded-xl border border-slate-200 p-3'">
      <p class="mb-2 text-sm font-semibold text-slate-800">{{ question.label }}</p>
      <ul v-if="question.options.length" class="space-y-1 text-sm">
        <li v-for="option in question.options" :key="option.id" class="flex items-baseline justify-between gap-2">
          <span class="text-slate-600" :class="{ 'line-through': !option.isActive }">{{ option.label }}</span>
          <span class="font-semibold text-slate-900">
            {{ option.count }}<template v-if="option.capacity"> / {{ option.capacity }}</template>
            <span v-if="option.revenue" class="ml-1 text-xs font-normal text-slate-500">({{ formatPrice(option.revenue) }})</span>
          </span>
        </li>
      </ul>
      <p v-else class="text-sm text-slate-600">{{ t('questionnaire.answered', { n: question.answeredCount ?? 0 }) }}</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { t } from '@/i18n'
import { formatPrice } from '@/utils/money'
import type { QuestionSummaryItem } from '@/types'

defineProps<{
  summary: QuestionSummaryItem[]
  /** Úzky bočný panel (zoznam prihlásených) — jeden stĺpec bez rámčekov. */
  compact?: boolean
}>()
</script>
