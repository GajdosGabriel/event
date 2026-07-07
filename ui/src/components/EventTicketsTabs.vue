<template>
  <div class="mb-4 flex flex-wrap items-center gap-2">
    <RouterLink :to="`/dashboard/events/${eventId}`" class="action-btn">← Späť na event</RouterLink>
    <nav class="ml-auto flex flex-wrap gap-1 rounded-lg bg-slate-100 p-1">
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
import { useRoute } from 'vue-router'

const props = defineProps<{ eventId: number }>()
const route = useRoute()

const tabs = computed(() => [
  { name: 'settings', label: 'Nastavenia', to: `/dashboard/events/${props.eventId}/tickets` },
  { name: 'attendees', label: 'Prihlásení', to: `/dashboard/events/${props.eventId}/attendees` },
  { name: 'checkin', label: 'Check-in', to: `/dashboard/events/${props.eventId}/checkin` },
])

function isActive(name: string): boolean {
  if (name === 'settings') return route.name === 'dashboard-events-tickets'
  if (name === 'attendees') return route.name === 'dashboard-events-attendees'
  if (name === 'checkin') return route.name === 'dashboard-events-checkin'
  return false
}
</script>
