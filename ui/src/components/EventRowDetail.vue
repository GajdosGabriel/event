<template>
  <div class="mt-1.5 flex flex-col gap-1">
    <!-- Termín + fáza podujatia -->
    <div v-if="dateLabel" class="flex flex-wrap items-center gap-1.5">
      <span class="row-chip row-chip-date">
        <svg class="h-3 w-3 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
          <rect x="3" y="4" width="18" height="18" rx="2" />
          <path d="M16 2v4M8 2v4M3 10h18" />
        </svg>
        {{ dateLabel }}
      </span>
      <span v-if="timeState" class="row-chip" :class="timeState.class">{{ timeState.label }}</span>
    </div>

    <!-- Kanál (klikateľný filter) + zvyšné metadáta -->
    <div class="flex flex-wrap items-center gap-1.5">
      <button
        v-if="canalName && canalId"
        type="button"
        class="row-chip row-chip-canal"
        :title="`Filtrovať podľa kanála: ${canalName}`"
        @click.stop.prevent="emit('filter-canal')"
      >{{ canalName }}</button>
      <span v-else-if="canalName" class="row-chip row-chip-canal">{{ canalName }}</span>

      <span v-if="deleted" class="row-chip row-chip-danger">Zmazaný</span>

      <span v-if="facts.length" class="row-facts">
        <span v-for="fact in facts" :key="fact">{{ fact }}</span>
      </span>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { eventTimeState, fmtRowDateRange } from '@/utils/dateFormat'

const props = defineProps<{
  startAt?: string | null
  endAt?: string | null
  canalId?: number | null
  canalName?: string | null
  /** Firma nad kanálom — chodí len v admin výpise, dashboard ju nepotrebuje. */
  organizationTitle?: string | null
  venueName?: string | null
  /** Už naformátovaný dátum vzniku. */
  createdAt?: string | null
  deleted?: boolean
}>()

const emit = defineEmits<{ (e: 'filter-canal'): void }>()

const dateLabel = computed(() => fmtRowDateRange(props.startAt ?? null, props.endAt ?? null))

const TIME_STATES = {
  ongoing: { label: 'Prebieha', class: 'row-chip-live' },
  past: { label: 'Skončil', class: 'row-chip-past' },
} as const

const timeState = computed(() => {
  const state = eventTimeState(props.startAt ?? null, props.endAt ?? null)
  return state ? TIME_STATES[state] : null
})

const facts = computed(() =>
  [
    props.organizationTitle,
    props.venueName,
    props.createdAt ? `vytvorené ${props.createdAt}` : null,
  ].filter((value): value is string => Boolean(value)),
)
</script>
