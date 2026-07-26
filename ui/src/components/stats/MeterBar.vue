<template>
  <div>
    <div class="flex items-baseline justify-between gap-2">
      <p class="text-sm font-semibold text-slate-700">{{ label }}</p>
      <p class="text-sm font-bold tabular-nums text-slate-900">{{ fmtPercent(rate) }}</p>
    </div>

    <div
      class="mt-1.5 h-2.5 overflow-hidden rounded-full bg-slate-100"
      role="meter"
      :aria-valuenow="rate ?? 0"
      aria-valuemin="0"
      aria-valuemax="100"
      :aria-label="label"
    >
      <div class="h-full rounded-full transition-[width]" :style="{ width: `${width}%`, backgroundColor: color }" />
    </div>

    <p class="mt-1 text-xs text-slate-500">
      <span class="font-semibold text-slate-700">{{ fmtCount(part) }}</span> z {{ fmtCount(whole) }} {{ unit }}
      <span v-if="note"> · {{ note }}</span>
    </p>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { fmtCount, fmtPercent } from '@/utils/statsFormat'

const props = withDefaults(defineProps<{
  label: string
  part: number
  whole: number
  rate: number | null
  unit?: string
  note?: string | null
  color?: string
}>(), {
  unit: '',
  note: null,
  color: '#2a78d6',
})

// Preplnenú kapacitu (viac miest než limit) ukazujeme ako plnú lištu —
// percento vedľa nej povie skutočnú hodnotu.
const width = computed(() => Math.min(100, Math.max(0, props.rate ?? 0)))
</script>
