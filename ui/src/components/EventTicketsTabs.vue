<template>
  <div class="mb-4 flex flex-wrap items-center gap-2">
    <!-- Späť ukazujeme len na prvej karte (Nastavenia) — medzi kartami sa
         chodí navigáciou nižšie, nie tlačidlom späť. Vracia tam, odkiaľ
         používateľ do sekcie lístkov prišiel; ak to nevieme, na event. -->
    <button v-if="showBack" type="button" class="action-btn" @click="goBack">{{ t('common.back') }}</button>
    <nav class="flex flex-wrap gap-1 rounded-lg bg-slate-100 p-1" :class="showBack ? 'ml-auto' : ''">
      <RouterLink
        v-for="tab in tabs"
        :key="tab.name"
        :to="tab.to"
        class="rounded-md px-3 py-1.5 text-sm font-medium transition-colors"
        :class="isActive(tab.name)
          ? 'bg-white text-blue-700 shadow-sm'
          : 'text-slate-600 hover:text-slate-900'"
      >
        {{ tab.label }}
      </RouterLink>
    </nav>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from '@/i18n'

const props = withDefaults(defineProps<{ eventId: number; showBack?: boolean }>(), { showBack: false })
const route = useRoute()
const router = useRouter()
const { t } = useI18n()

const eventPath = computed(() => `/dashboard/events/${props.eventId}`)

const tabs = computed(() => [
  { name: 'settings', label: t('tickets.tabs.settings'), to: `${eventPath.value}/tickets` },
  { name: 'attendees', label: t('tickets.tabs.attendees'), to: `${eventPath.value}/attendees` },
  { name: 'checkin', label: t('tickets.tabs.checkin'), to: `${eventPath.value}/checkin` },
  { name: 'questions', label: t('questions.dashboard.tab'), to: `${eventPath.value}/otazky` },
])

function isActive(name: string): boolean {
  if (name === 'settings') return String(route.name ?? '').startsWith('dashboard-events-tickets')
  if (name === 'attendees') return route.name === 'dashboard-events-attendees'
  if (name === 'checkin') return route.name === 'dashboard-events-checkin'
  if (name === 'questions') return route.name === 'dashboard-events-questions'
  return false
}

function goBack() {
  // Predošlá adresa z histórie routera. Vraciame sa len na vlastnú aplikáciu a
  // nikdy späť do sekcie lístkov — inak by tlačidlo krúžilo medzi kartami.
  const previous = (router.options.history.state as { back?: unknown } | null)?.back
  const path = typeof previous === 'string' ? previous : ''
  if (path.startsWith('/') && !path.startsWith('//') && !path.startsWith(`${eventPath.value}/`)) {
    router.push(path)
    return
  }
  router.push(eventPath.value)
}
</script>
