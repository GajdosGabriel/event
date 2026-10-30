<template>
  <div class="mx-auto my-5 w-full max-w-[1320px] px-4">
    <EventTicketsTabs :event-id="eventId" />

    <div class="mb-4 min-w-0">
      <h1 class="text-2xl font-semibold text-slate-900">{{ eventName || t('tickets.attendees.title') }}</h1>
      <p v-if="eventName" class="text-sm text-slate-500">{{ t('tickets.attendees.title') }}</p>
    </div>

    <!-- Zoznam je užší, vpravo stojí prehľad: pri vchode sa organizátor pozerá
         hlavne naň a tabuľku len prehľadáva. -->
    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_20rem]">
      <AttendeeWorkspace :event-id="eventId" :event-name="eventName" @changed="loadSummary" />

      <!-- Bočný panel: prehľad pri vchode a doplnkové nastavenia podujatia —
           zamykanie workshopov aj pripomienka sa týkajú účastníkov, preto stoja
           tu. Pri dlhom zozname typov lístkov sa panel roluje sám, nech je
           tlačidlo Uložiť vždy dosiahnuteľné. -->
      <aside class="grid gap-3 self-start lg:sticky lg:top-4 lg:max-h-[calc(100vh-2rem)] lg:overflow-y-auto">
        <AttendeeStatsPanel v-if="summary" :summary="summary" />
        <section v-if="answersSummary.length" class="rounded-2xl border border-slate-200 bg-white p-4">
          <div class="mb-2 flex items-center justify-between gap-2">
            <h2 class="text-sm font-semibold text-slate-800">{{ t('questionnaire.summaryTitle') }}</h2>
            <RouterLink :to="{ name: 'dashboard-events-questionnaire', params: { id: eventId } }" class="text-xs text-blue-600 hover:underline">
              {{ t('questionnaire.manage') }}
            </RouterLink>
          </div>
          <QuestionnaireSummary :summary="answersSummary" compact />
        </section>
        <RouterLink :to="{ name: 'dashboard-events-checkin', params: { id: eventId } }" class="btn btn-secondary">
          {{ t('tickets.stats.openScanner') }}
        </RouterLink>
        <EventExtraSettingsPanel :event-id="eventId" />
      </aside>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { attendeeStats } from '@/api/tickets'
import { questionnaireSummary } from '@/api/questionnaire'
import { showEvent } from '@/api/events'
import AttendeeWorkspace from '@/components/AttendeeWorkspace.vue'
import AttendeeStatsPanel from '@/components/stats/AttendeeStatsPanel.vue'
import EventExtraSettingsPanel from '@/components/EventExtraSettingsPanel.vue'
import EventTicketsTabs from '@/components/EventTicketsTabs.vue'
import QuestionnaireSummary from '@/components/QuestionnaireSummary.vue'
import type { AttendeeSummary, QuestionSummaryItem } from '@/types'
import { useI18n } from '@/i18n'

const { t } = useI18n()

const route = useRoute()
const eventId = Number(route.params.id)

const summary = ref<AttendeeSummary | null>(null)
const eventName = ref('')
const answersSummary = ref<QuestionSummaryItem[]>([])

async function loadSummary() {
  summary.value = await attendeeStats(eventId).catch(() => summary.value)
  answersSummary.value = await questionnaireSummary(eventId).catch(() => answersSummary.value)
}

onMounted(() => {
  loadSummary()
  showEvent('dashboard', eventId).then((e) => { eventName.value = e.name }).catch(() => {})
})
</script>
