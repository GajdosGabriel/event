<template>
  <div class="mt-1.5 flex flex-col gap-1">
    <!-- Obec + kanály, ktoré miesto používajú -->
    <div v-if="hasChips" class="flex flex-wrap items-center gap-1.5">
      <RouterLink
        v-if="municipalityName && municipalityId"
        :to="municipalityLink"
        class="row-chip row-chip-place"
        :title="t('venues.row.filterByMunicipality', { name: municipalityName })"
        @click.stop
      >
        <svg class="h-3 w-3 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M12 21s7-6.4 7-11a7 7 0 10-14 0c0 4.6 7 11 7 11z" />
          <circle cx="12" cy="10" r="2.5" />
        </svg>
        {{ municipalityName }}
      </RouterLink>
      <span v-else-if="municipalityName" class="row-chip">{{ municipalityName }}</span>

      <!-- Kanál miesto nefiltruje (miesta ho nemajú stĺpcom, len cez väzbu),
           preto chip vedie rovno na jeho detail. -->
      <RouterLink
        v-for="canal in visibleCanals"
        :key="canal.id"
        :to="`${canalPrefix}/${canal.id}`"
        class="row-chip row-chip-canal"
        :title="canal.isOwner ? t('venues.row.ownerCanal', { name: canal.name }) : canal.name"
        @click.stop
      >{{ canal.name }}</RouterLink>
      <span v-if="hiddenCanals > 0" class="row-chip">{{ t('venues.row.moreCanals', { n: hiddenCanals }) }}</span>

      <span v-if="deleted" class="row-chip row-chip-danger">{{ t('venues.row.deleted') }}</span>
    </div>

    <span v-if="facts.length" class="row-facts">
      <span v-for="fact in facts" :key="fact">{{ fact }}</span>
    </span>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from '@/i18n'
import type { RowCanal } from '@/types'

const { t, plural } = useI18n()

/** Viac chipov než toto by z riadku spravilo odstavec. */
const MAX_CANALS = 3

const props = defineProps<{
  municipalityId?: number | null
  municipalityName?: string | null
  canals?: RowCanal[]
  /** Počet z `withCount` v index dotaze; `null` = odpoveď ho neniesla. */
  eventsCount?: number | null
  capacity?: number | null
  category?: string | null
  /** Už naformátovaný dátum vzniku. */
  createdAt?: string | null
  deleted?: boolean
  /** Adresa výpisu (`/admin/venues`) — chip obce naň vešia filter. */
  indexPath: string
  /** Prefix detailu kanála (`/admin/canals`), líši sa admin od dashboardu. */
  canalPrefix: string
}>()

const canals = computed(() => props.canals ?? [])
const visibleCanals = computed(() => canals.value.slice(0, MAX_CANALS))
const hiddenCanals = computed(() => Math.max(canals.value.length - MAX_CANALS, 0))

const hasChips = computed(() =>
  Boolean(props.municipalityName || canals.value.length || props.deleted))

/** Tá istá adresa, akú stavia bočný prehľad obcí — jeden filter, jeden odkaz. */
const municipalityLink = computed(() => ({
  path: props.indexPath,
  query: { municipality: props.municipalityId },
}))

const facts = computed(() =>
  [
    typeof props.eventsCount === 'number' ? plural('venues.row.counts.events', props.eventsCount) : null,
    typeof props.capacity === 'number' && props.capacity > 0
      ? t('venues.row.capacity', { n: props.capacity })
      : null,
    props.category || null,
    props.createdAt ? t('venues.row.createdAt', { date: props.createdAt }) : null,
  ].filter((value): value is string => Boolean(value)),
)
</script>
