<template>
  <!-- Deň je nadpis nad skupinou, nie šedý pruh v tabuľke — agenda sa číta
       ako program („dnes, zajtra, v sobotu"), nie ako výpis z databázy. -->
  <div class="space-y-8">
    <section v-for="group in groups" :key="group.key">
      <h2 class="mb-3 flex items-baseline gap-2 px-1">
        <span class="text-lg font-bold text-slate-900">{{ group.dayLabel }}</span>
        <span class="text-sm font-medium text-slate-400">{{ group.dateLabel }}</span>
      </h2>

      <div class="space-y-3">
        <!-- Celý riadok je klikateľný cez roztiahnutý odkaz v nadpise (after:inset-0),
             nie cez obalový <a> — inak by sa doň nedalo vložiť tlačidlo lístkov. -->
        <article
          v-for="event in group.events"
          :key="event.id"
          class="group relative flex gap-4 rounded-2xl bg-white p-3 shadow-sm ring-1 ring-slate-900/5 transition duration-200 hover:shadow-md hover:ring-slate-900/10"
        >
          <!-- Náhľad je 96 px (128 px od `sm`). Thumb má 320 px, takže pokryje aj
               retinu — srcset by tu len sťahoval kilobajty navyše. -->
          <div class="h-24 w-24 shrink-0 overflow-hidden rounded-xl bg-slate-100 sm:h-32 sm:w-32">
            <img
              v-if="event.imageUrl"
              :src="event.imageUrl"
              :alt="event.name"
              loading="lazy"
              decoding="async"
              class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
            />
          </div>

          <div class="flex min-w-0 flex-1 flex-col py-0.5">
            <div class="flex items-start gap-3">
              <div class="min-w-0 flex-1">
                <p v-if="event.dateRangeLabel" class="mb-1 text-xs font-semibold uppercase tracking-wide text-rose-600">
                  {{ event.dateRangeLabel }}
                </p>
                <h3 class="text-base font-bold leading-snug text-slate-900 sm:text-lg">
                  <RouterLink
                    :to="publicEventPath(event)"
                    class="text-inherit no-underline after:absolute after:inset-0 group-hover:text-blue-700"
                  >{{ event.name }}</RouterLink>
                </h3>
              </div>

              <!-- Kúpiť / rezervovať lístok — vpravo hore, nad roztiahnutým odkazom. -->
              <EventTicketCta
                v-if="showTicketCta && event.ticketCta"
                :cta="event.ticketCta"
                :to="`${publicEventPath(event)}#registracia`"
                :event-name="event.name"
                compact-on-mobile
                class="relative z-10"
              />
            </div>

            <p v-if="place(event) || event.canalName" class="mt-1.5 flex min-w-0 flex-wrap items-center gap-x-3 gap-y-1 text-sm text-slate-500">
              <span v-if="place(event)" class="inline-flex min-w-0 items-center gap-1">
                <AppIcon name="mapPin" class="h-3.5 w-3.5 shrink-0 text-slate-400" />
                <span class="truncate">{{ place(event) }}</span>
              </span>
              <span v-if="event.canalName" class="inline-flex min-w-0 items-center gap-1">
                <AppIcon name="users" class="h-3.5 w-3.5 shrink-0 text-slate-400" />
                <span class="truncate">{{ event.canalName }}</span>
              </span>
            </p>

            <!-- Perex je na telefóne prvý na odstrel: v úzkom stĺpci z neho boli
                 tri-štyri riadky a zoznam sa tým roztiahol na dvojnásobok. -->
            <p v-if="summary(event)" class="mt-1.5 hidden text-sm leading-relaxed text-slate-500 sm:line-clamp-2">{{ summary(event) }}</p>
          </div>
        </article>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { EventItem } from '@/types'
import EventTicketCta from '@/components/EventTicketCta.vue'
import AppIcon from '@/components/AppIcon.vue'
import { dayName, fmtDate } from '@/utils/dateFormat'
import { publicEventPath } from '@/utils/publicUrl'
import { useI18n } from '@/i18n'

const { t } = useI18n()

const props = withDefaults(defineProps<{ events: EventItem[]; showTicketCta?: boolean }>(), {
  showTicketCta: true,
})

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
