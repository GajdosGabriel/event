<template>
  <div v-if="events.length" class="overflow-hidden rounded-xl border border-slate-200 bg-white">
    <button
      type="button"
      class="flex w-full items-center gap-2 px-4 py-2 text-left transition-colors hover:bg-slate-50"
      @click="toggle"
    >
      <span class="relative flex h-2 w-2 shrink-0">
        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75" />
        <span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-500" />
      </span>
      <span class="text-sm font-semibold text-slate-900">Práve prebieha</span>
      <span class="rounded-full bg-slate-100 px-1.5 py-0.5 text-xs font-medium text-slate-500">{{ events.length }}</span>
      <svg
        class="ml-auto h-4 w-4 shrink-0 text-slate-400 transition-transform"
        :class="open ? 'rotate-180' : ''"
        fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"
      >
        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
      </svg>
    </button>

    <ul v-if="open" class="m-0 list-none divide-y divide-dashed divide-slate-100 border-t border-slate-100 p-0">
      <li v-for="event in events" :key="event.id">
        <RouterLink
          :to="`/events/${event.id}`"
          class="flex items-baseline gap-3 px-4 py-1.5 no-underline hover:bg-slate-50"
        >
          <span class="min-w-0 truncate text-sm text-slate-700 hover:underline">{{ event.name }}</span>
          <span v-if="event.endAt" class="ml-auto shrink-0 text-xs text-slate-400">do {{ fmtDate(event.endAt) }}</span>
        </RouterLink>
      </li>
    </ul>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { indexEvents } from '@/api/events'
import type { EventItem } from '@/types'
import { fmtDate } from '@/utils/dateFormat'
import { useSettings } from '@/composables/useSettings'

const props = defineProps<{ municipality?: number | null }>()

const { settings, save } = useSettings()
const events = ref<EventItem[]>([])

const open = computed(() => settings.value.homeOngoingOpen)

function toggle() {
  settings.value.homeOngoingOpen = !settings.value.homeOngoingOpen
  save()
}

async function load() {
  try {
    const params: Record<string, unknown> = { list: 'ongoing', per_page: 100 }
    if (props.municipality) params['municipality'] = props.municipality
    const res = await indexEvents('public', params)
    events.value = res.data
  } catch {
    events.value = []
  }
}

watch(() => props.municipality, load)
onMounted(load)
</script>
