<template>
  <div class="flex h-full gap-4 border-b border-dotted border-slate-300 p-3 md:flex-col">
    <RouterLink :to="`/events/${event.id}`" class="w-[42%] shrink-0 md:block md:w-full">
      <img
        v-if="event.imageUrl"
        :src="event.imageUrl"
        :alt="event.name"
        class="block h-full max-h-[120px] w-full rounded-lg object-cover md:h-[180px] md:max-h-none"
      />
      <div v-else class="block h-full max-h-[120px] w-full rounded-lg bg-slate-100 md:h-[180px] md:max-h-none" />
    </RouterLink>

    <div class="flex min-w-0 flex-1 flex-col">
      <h3 class="mb-1 text-base leading-tight">
        <RouterLink :to="`/events/${event.id}`" class="text-slate-900 no-underline hover:underline">{{ event.name }}</RouterLink>
      </h3>
      <p v-if="event.dateRangeLabel" class="mb-2 text-sm leading-snug text-slate-500">{{ event.dateRangeLabel }}</p>
      <p v-if="event.canalName" class="mb-1 text-sm text-slate-600">{{ event.canalName }}</p>
      <p v-if="event.publishedAt" class="mt-auto text-xs text-slate-400">{{ formatDate(event.publishedAt) }}</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { EventItem } from '@/types'

defineProps<{ event: EventItem }>()

function formatDate(d: string) {
  return new Date(d).toLocaleDateString('sk-SK')
}
</script>
