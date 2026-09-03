<template>
  <div class="mt-1.5 flex flex-col gap-1">
    <!-- Obec + identita (firma, prípadne typ kanála) -->
    <div v-if="hasChips" class="flex flex-wrap items-center gap-1.5">
      <RouterLink
        v-if="municipalityName && municipalityId"
        :to="municipalityLink"
        class="row-chip row-chip-place"
        :title="t('canals.row.filterByMunicipality', { name: municipalityName })"
        @click.stop
      >
        <svg class="h-3 w-3 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M12 21s7-6.4 7-11a7 7 0 10-14 0c0 4.6 7 11 7 11z" />
          <circle cx="12" cy="10" r="2.5" />
        </svg>
        {{ municipalityName }}
      </RouterLink>
      <span v-else-if="municipalityName" class="row-chip">{{ municipalityName }}</span>

      <!-- Firma za kanálom; osobný kanál žiadnu nemá, tak nastúpi typ identity. -->
      <span v-if="identityLabel" class="row-chip row-chip-org">{{ identityLabel }}</span>

      <span v-if="deleted" class="row-chip row-chip-danger">{{ t('canals.row.deleted') }}</span>
    </div>

    <span v-if="facts.length" class="row-facts">
      <span v-for="fact in facts" :key="fact">{{ fact }}</span>
    </span>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from '@/i18n'

const { t, plural } = useI18n()

const props = defineProps<{
  municipalityId?: number | null
  municipalityName?: string | null
  /** Fakturačná firma kanála; `null` pri osobnom kanáli. */
  organizationName?: string | null
  identityModeLabel?: string | null
  /** Počty z `withCount` v index dotaze; `null` = odpoveď ich neniesla. */
  eventsCount?: number | null
  venuesCount?: number | null
  membersCount?: number | null
  /** Už naformátovaný dátum vzniku. */
  createdAt?: string | null
  deleted?: boolean
  /** Adresa výpisu (`/admin/canals`) — chip obce naň vešia filter. */
  indexPath: string
}>()

const identityLabel = computed(() => props.organizationName || props.identityModeLabel || null)

const hasChips = computed(() =>
  Boolean(props.municipalityName || identityLabel.value || props.deleted))

/** Tá istá adresa, akú stavia bočný prehľad obcí — jeden filter, jeden odkaz. */
const municipalityLink = computed(() => ({
  path: props.indexPath,
  query: { municipality: props.municipalityId },
}))

const facts = computed(() =>
  [
    typeof props.eventsCount === 'number' ? plural('canals.row.counts.events', props.eventsCount) : null,
    typeof props.venuesCount === 'number' ? plural('canals.row.counts.venues', props.venuesCount) : null,
    typeof props.membersCount === 'number' ? plural('canals.row.counts.members', props.membersCount) : null,
    props.createdAt ? t('canals.row.createdAt', { date: props.createdAt }) : null,
  ].filter((value): value is string => Boolean(value)),
)
</script>
