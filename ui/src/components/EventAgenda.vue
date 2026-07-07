<template>
  <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
    <section v-for="group in groups" :key="group.key">
      <h2 class="flex items-baseline gap-2 border-b border-slate-200 bg-slate-50 px-4 py-2">
        <span class="font-semibold text-slate-900">{{ group.dayLabel }}</span>
        <span class="text-sm text-slate-500">{{ group.dateLabel }}</span>
      </h2>

      <RouterLink
        v-for="event in group.events"
        :key="event.id"
        :to="`/events/${event.id}`"
        class="flex gap-4 border-b border-dotted border-slate-300 px-4 py-3 no-underline transition-colors last:border-b-0 hover:bg-slate-50"
      >
        <img
          v-if="event.imageUrl"
          :src="event.imageUrl"
          :alt="event.name"
          class="h-14 w-14 shrink-0 rounded-lg object-cover"
        />
        <div v-else class="h-14 w-14 shrink-0 rounded-lg bg-slate-100" />

        <div class="min-w-0 flex-1">
          <h3 class="text-base leading-tight text-slate-900">
            <span class="font-semibold hover:underline">{{ event.name }}</span>
            <span v-if="event.dateRangeLabel" class="ml-2 text-sm font-normal text-slate-500">{{ event.dateRangeLabel }}</span>
          </h3>

          <p v-if="place(event)" class="mt-1 text-sm text-slate-600">{{ place(event) }}</p>

          <span
            v-if="event.canalName"
            class="mt-1 inline-block rounded bg-slate-100 px-2 py-0.5 text-xs text-slate-600"
          >{{ event.canalName }}</span>

          <p v-if="summary(event)" class="mt-1 text-sm leading-snug text-slate-500">{{ summary(event) }}</p>
        </div>
      </RouterLink>
    </section>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { EventItem } from '@/types'
import { dayName, fmtDate } from '@/utils/dateFormat'

const props = defineProps<{ events: EventItem[] }>()

const NO_DATE_KEY = 'no-date'

interface DayGroup {
  key: string
  dayLabel: string
  dateLabel: string
  events: EventItem[]
}

const groups = computed<DayGroup[]>(() => {
  const sorted = [...props.events].sort((a, b) => {
    if (!a.startAt) return 1
    if (!b.startAt) return -1
    return new Date(a.startAt).getTime() - new Date(b.startAt).getTime()
  })

  const map = new Map<string, DayGroup>()
  for (const event of sorted) {
    const key = event.startAt ? new Date(event.startAt).toDateString() : NO_DATE_KEY
    let group = map.get(key)
    if (!group) {
      group = {
        key,
        dayLabel: event.startAt ? dayName(event.startAt) : 'Bez termínu',
        dateLabel: event.startAt ? fmtDate(event.startAt) : '',
        events: [],
      }
      map.set(key, group)
    }
    group.events.push(event)
  }
  return [...map.values()]
})

function place(event: EventItem): string {
  return event.venue?.name ?? event.locationName ?? event.municipality?.name ?? ''
}

function summary(event: EventItem): string {
  const raw = event.body ?? event.bodyAi
  if (!raw) return ''
  const text = raw.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim()
  return text.length > 160 ? `${text.slice(0, 160).trimEnd()}…` : text
}
</script>
