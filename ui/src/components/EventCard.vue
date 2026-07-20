<template>
  <!-- Univerzálna karta eventu — používa sa vo verejnom výpise, na stránke kanála aj miesta.
       Vizuál zámerne kopíruje riadok v admine/dashboarde (IndexRow): obrázok navrchu,
       pod ním názov a badge pre dátum/kanál/miesto v rovnakých farbách. -->
  <div class="group flex h-full flex-col overflow-hidden rounded-lg border border-slate-200 bg-white transition-shadow hover:shadow-sm">
    <RouterLink :to="link" class="block">
      <img
        v-if="imageUrl"
        :src="imageUrl"
        :alt="name"
        class="block h-40 w-full object-cover"
      />
      <div v-else class="block h-40 w-full bg-slate-100" />
    </RouterLink>

    <div class="flex min-w-0 flex-1 flex-col gap-1.5 p-3">
      <h3 class="text-[0.97rem] leading-tight">
        <RouterLink :to="link" class="text-slate-900 no-underline hover:underline">{{ name }}</RouterLink>
      </h3>

      <div v-if="dateLabel || canalName || venueName" class="mt-auto flex flex-wrap items-center gap-1.5 pt-1">
        <span
          v-if="dateLabel"
          class="inline-flex items-center gap-1 rounded-md bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-200"
        >
          <svg class="h-3 w-3 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
          {{ dateLabel }}
        </span>
        <span
          v-if="canalName"
          class="inline-flex items-center rounded-full bg-teal-50 px-2 py-0.5 text-xs font-medium text-teal-700 ring-1 ring-inset ring-teal-200"
        >
          {{ canalName }}
        </span>
        <span
          v-if="venueName"
          class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600"
        >
          {{ venueName }}
        </span>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  id: number
  name: string
  imageUrl?: string | null
  dateLabel?: string | null
  canalName?: string | null
  venueName?: string | null
  /** Cieľ odkazu; predvolene detail eventu. */
  to?: string
}>()

const link = computed(() => props.to ?? `/events/${props.id}`)
</script>
