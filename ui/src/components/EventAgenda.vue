<template>
  <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
    <section v-for="group in groups" :key="group.key">
      <h2 class="flex items-baseline gap-2 border-b border-slate-200 bg-slate-50 px-4 py-2">
        <span class="font-semibold text-slate-900">{{ group.dayLabel }}</span>
        <span class="text-sm text-slate-500">{{ group.dateLabel }}</span>
      </h2>

      <!-- Celý riadok je klikateľný cez roztiahnutý odkaz v nadpise (after:inset-0),
           nie cez obalový <a> — inak by sa doň nedalo vložiť tlačidlo lístkov. -->
      <div
        v-for="event in group.events"
        :key="event.id"
        class="relative flex gap-4 border-b border-dotted border-slate-300 px-4 py-3 transition-colors last:border-b-0 hover:bg-slate-50"
      >
        <!-- Náhľad je 96 px (112 px od `sm`). Thumb má 320 px, takže pokryje aj
             retinu (112 × 2 = 224) — srcset by tu len sťahoval kilobajty navyše. -->
        <img
          v-if="event.imageUrl"
          :src="event.imageUrl"
          :alt="event.name"
          loading="lazy"
          decoding="async"
          class="h-24 w-24 shrink-0 rounded-lg object-cover sm:h-28 sm:w-28"
        />
        <div v-else class="h-24 w-24 shrink-0 rounded-lg bg-slate-100 sm:h-28 sm:w-28" />

        <div class="min-w-0 flex-1">
          <div class="flex items-start gap-3">
            <h3 class="min-w-0 flex-1 text-base leading-tight text-slate-900">
              <RouterLink
                :to="publicEventPath(event)"
                class="font-semibold text-slate-900 no-underline after:absolute after:inset-0 hover:underline"
              >{{ event.name }}</RouterLink>
              <span v-if="event.dateRangeLabel" class="ml-2 text-sm font-normal text-slate-500">{{ event.dateRangeLabel }}</span>
            </h3>

            <!-- Kúpiť / rezervovať lístok — vpravo hore, nad roztiahnutým odkazom. -->
            <EventTicketCta
              v-if="event.ticketCta"
              :cta="event.ticketCta"
              :to="`${publicEventPath(event)}#registracia`"
              :event-name="event.name"
              compact-on-mobile
              class="relative z-10"
            />
          </div>

          <p v-if="place(event)" class="mt-1 text-sm text-slate-600">{{ place(event) }}</p>

          <span
            v-if="event.canalName"
            class="mt-1 inline-block rounded bg-slate-100 px-2 py-0.5 text-xs text-slate-600"
          >{{ event.canalName }}</span>

          <!-- Perex je na telefóne prvý na odstrel: v úzkom stĺpci z neho boli
               tri-štyri riadky a zoznam sa tým roztiahol na dvojnásobok. -->
          <p v-if="summary(event)" class="mt-1 hidden text-sm leading-snug text-slate-500 sm:block">{{ summary(event) }}</p>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { EventItem } from '@/types'
import EventTicketCta from '@/components/EventTicketCta.vue'
import { dayName, fmtDate } from '@/utils/dateFormat'
import { publicEventPath } from '@/utils/publicUrl'
import { useI18n } from '@/i18n'

const { t } = useI18n()

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
        dayLabel: event.startAt ? dayName(event.startAt) : t('common.noDate'),
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
  const raw = event.body
  if (!raw) return ''
  const text = raw.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim()
  return text.length > 160 ? `${text.slice(0, 160).trimEnd()}…` : text
}
</script>
