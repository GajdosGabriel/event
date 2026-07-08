<template>
  <div v-if="startAt">
    <!-- Same day: Sobota / 4. 7. 2026, 10:00 – 18:00 (or "Celý deň" for 00:00 – 23:59) -->
    <template v-if="endAt && isSameDay(startAt, endAt)">
      <span class="block font-semibold">{{ dayName(startAt) }}</span>
      <span v-if="isAllDayRange(startAt, endAt)">{{ fmtDate(startAt) }}, Celý deň</span>
      <span v-else>{{ fmtDate(startAt) }}, {{ fmtTime(startAt) }} – {{ fmtTime(endAt) }}</span>
    </template>

    <!-- Multi-day, no specific time (00:00 - 23:59): Pondelok - Piatok / 28. 9. 2026, - 15. 1. 2027 -->
    <template v-else-if="endAt && isAllDayRange(startAt, endAt)">
      <span class="block font-semibold">{{ dayName(startAt) }} - {{ dayName(endAt) }}</span>
      <span>{{ fmtDate(startAt) }}, - {{ fmtDate(endAt) }}</span>
    </template>

    <!-- Multi-day or no end -->
    <template v-else>
      <span class="block font-semibold">{{ dayName(startAt) }}</span>
      <span>{{ fmtDate(startAt) }}, {{ fmtTime(startAt) }}</span>
      <template v-if="endAt">
        <span class="mt-2 block font-semibold">{{ dayName(endAt) }}</span>
        <span>{{ fmtDate(endAt) }}, {{ fmtTime(endAt) }}</span>
      </template>
    </template>
  </div>
  <span v-else>—</span>
</template>

<script setup lang="ts">
import { dayName, fmtDate, fmtTime } from '@/utils/dateFormat'

const props = defineProps<{
  startAt?: string | null
  endAt?: string | null
}>()

function isSameDay(a: string, b: string) {
  return new Date(a).toDateString() === new Date(b).toDateString()
}

function isAllDayRange(a: string, b: string) {
  const start = new Date(a)
  const end = new Date(b)
  return start.getHours() === 0 && start.getMinutes() === 0
    && end.getHours() === 23 && end.getMinutes() === 59
}
</script>
