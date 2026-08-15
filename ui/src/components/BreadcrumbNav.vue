<template>
  <!-- Posledná položka je aktuálna stránka, preto bez odkazu a s aria-current.
       Štruktúrované dáta pre vyhľadávač vydáva stránka cez JSON-LD, tu ide
       len o navigáciu pre človeka. -->
  <nav :aria-label="t('common.breadcrumb')" class="min-w-0">
    <ol class="flex flex-wrap items-center gap-x-1.5 gap-y-1 text-xs text-slate-500">
      <li v-for="(item, idx) in items" :key="idx" class="flex min-w-0 items-center gap-1.5">
        <svg
          v-if="idx > 0"
          class="h-3 w-3 shrink-0 text-slate-300"
          fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"
        >
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
        </svg>
        <RouterLink
          v-if="item.to && idx < items.length - 1"
          :to="item.to"
          class="truncate text-slate-500 no-underline hover:text-slate-900 hover:underline"
        >{{ item.label }}</RouterLink>
        <span v-else class="truncate font-medium text-slate-700" aria-current="page">{{ item.label }}</span>
      </li>
    </ol>
  </nav>
</template>

<script setup lang="ts">
import { useI18n } from '@/i18n'

const { t } = useI18n()

export interface BreadcrumbItem {
  label: string
  /** Bez cieľa sa položka vykreslí ako text — tak sa označí aktuálna stránka. */
  to?: string
}

defineProps<{ items: BreadcrumbItem[] }>()
</script>
