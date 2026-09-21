<template>
  <div class="rounded-lg p-4 text-sm" :class="tone">
    <p class="font-semibold">{{ copy.title }}</p>
    <p class="mt-1">{{ copy.lead }}</p>
    <div class="mt-3 flex flex-wrap gap-2">
      <RouterLink v-if="result.ticket_uuid" :to="`/tickets/${result.ticket_uuid}`" class="btn btn-primary">
        {{ t('eventSignup.showTicket') }}
      </RouterLink>
      <RouterLink v-else-if="result.status === 'already_registered'" to="/moje-listky" class="btn btn-primary">
        {{ t('eventSignup.myTickets') }}
      </RouterLink>
      <RouterLink :to="`/akcie/${result.event.id}`" class="btn btn-secondary">
        {{ t('eventSignup.backToEvent') }}
      </RouterLink>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { EventSignupResult } from '@/api/tickets'
import { t } from '@/i18n'

// Výsledok „Prihlásiť sa" na akciu — po overení e-mailu aj na /prihlasenie/:id.
const props = defineProps<{ result: EventSignupResult }>()

const copy = computed(() => {
  const event = props.result.event.name ? `„${props.result.event.name}"` : t('eventSignup.eventFallback')
  switch (props.result.status) {
    case 'reserved':
      return { title: t('eventSignup.reservedTitle'), lead: t('eventSignup.reservedLead', { event }) }
    case 'already_registered':
      return { title: t('eventSignup.alreadyTitle'), lead: t('eventSignup.alreadyLead', { event }) }
    case 'interest':
      return { title: t('eventSignup.interestTitle'), lead: t('eventSignup.interestLead', { event }) }
    default:
      return { title: t('eventSignup.closedTitle'), lead: t('eventSignup.closedLead', { event }) }
  }
})

const tone = computed(() => ({
  'bg-green-50 text-green-900': props.result.status === 'reserved' || props.result.status === 'already_registered',
  'bg-blue-50 text-blue-900': props.result.status === 'interest',
  'bg-amber-50 text-amber-900': props.result.status === 'closed',
}))
</script>
