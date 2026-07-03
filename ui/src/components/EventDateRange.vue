<template>
  <div v-if="startAt">
    <!-- Same day: Sobota / 4. 7. 2026, 10:00 – 18:00 -->
    <template v-if="endAt && isSameDay(startAt, endAt)">
      <span class="block font-semibold">{{ dayName(startAt) }}</span>
      <span>{{ fmtDate(startAt) }}, {{ fmtTime(startAt) }} – {{ fmtTime(endAt) }}</span>
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
const props = defineProps<{
  startAt?: string | null
  endAt?: string | null
}>()

const DAY_NAMES: Record<number, string> = {
  0: 'Nedeľa', 1: 'Pondelok', 2: 'Utorok', 3: 'Streda',
  4: 'Štvrtok', 5: 'Piatok', 6: 'Sobota',
}

function dayName(d: string) {
  return DAY_NAMES[new Date(d).getDay()] ?? ''
}

function fmtDate(d: string) {
  return new Date(d).toLocaleDateString('sk-SK', { day: 'numeric', month: 'numeric', year: 'numeric' })
}

function fmtTime(d: string) {
  return new Date(d).toLocaleTimeString('sk-SK', { hour: '2-digit', minute: '2-digit' })
}

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
