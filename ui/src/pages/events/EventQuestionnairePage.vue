<template>
  <div class="mx-auto my-5 w-full max-w-[1320px] px-4">
    <EventTicketsTabs :event-id="eventId" />

    <div class="mb-4">
      <h1 class="text-2xl font-semibold text-slate-900">{{ eventName || t('questionnaire.title') }}</h1>
      <p v-if="eventName" class="text-sm text-slate-500">{{ t('questionnaire.title') }}</p>
    </div>

    <p v-if="loading" class="text-slate-500">{{ t('common.loading') }}</p>
    <p v-else-if="loadError" class="text-red-600">{{ loadError }}</p>

    <template v-else>
      <section class="rounded-2xl border border-slate-200 bg-white p-5">
        <div class="mb-1 flex flex-wrap items-center justify-between gap-2">
          <h2 class="text-lg font-semibold text-slate-800">{{ t('questionnaire.listTitle') }}</h2>
          <button v-if="!editor" type="button" class="btn btn-secondary" @click="openNew(null)">
            {{ t('questionnaire.new') }}
          </button>
        </div>
        <p class="mb-3 text-xs text-slate-500">{{ t('questionnaire.lead') }}</p>

        <!-- Rýchly štart — najčastejšie otázky jedným klikom. -->
        <div v-if="!editor" class="mb-4 flex flex-wrap items-center gap-2">
          <span class="text-xs text-slate-500">{{ t('questionnaire.presetsLead') }}</span>
          <button v-for="preset in presets" :key="preset.key" type="button"
            class="rounded-full border border-slate-300 px-3 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50"
            @click="openNew(preset.question)">
            + {{ preset.question.label }}
          </button>
        </div>

        <p v-if="!questions.length" class="text-sm text-slate-400">{{ t('questionnaire.empty') }}</p>

        <ul v-else class="divide-y divide-slate-100 rounded-xl border border-slate-200">
          <li v-for="(question, i) in questions" :key="question.id" class="flex flex-wrap items-start gap-3 px-4 py-3">
            <div class="min-w-0 flex-1">
              <p class="font-medium text-slate-900">
                {{ question.label }}
                <span v-if="question.isRequired" class="text-red-600" :title="t('questionnaire.required')">*</span>
                <span v-if="!question.isActive" class="ml-1 rounded-full bg-slate-200 px-2 py-0.5 text-xs font-medium text-slate-600">
                  {{ t('questionnaire.inactive') }}
                </span>
              </p>
              <p class="text-xs text-slate-500">
                {{ t(`questionnaire.types.${question.type}`) }} · {{ t(`questionnaire.scope.${question.scope}`) }}
                <template v-if="question.ticketTypeIds?.length"> · {{ typeNames(question) }}</template>
                <template v-if="!question.editableByAttendee"> · {{ t('questionnaire.locked') }}</template>
              </p>
              <p v-if="question.options.length" class="mt-1 flex flex-wrap gap-1">
                <span v-for="option in question.options.filter(o => o.isActive)" :key="option.id"
                  class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-700">
                  {{ option.label }}
                  <template v-if="option.priceAmount"> · +{{ formatPrice(option.priceAmount) }}</template>
                  <template v-if="option.capacity"> · {{ option.takenCount ?? 0 }}/{{ option.capacity }}</template>
                  <template v-else-if="option.takenCount"> · {{ option.takenCount }}</template>
                </span>
              </p>
            </div>
            <div class="flex items-center gap-1">
              <button type="button" class="rounded-md p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 disabled:opacity-30"
                :disabled="i === 0" :aria-label="t('questionnaire.moveUp')" :title="t('questionnaire.moveUp')" @click="move(i, -1)">↑</button>
              <button type="button" class="rounded-md p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 disabled:opacity-30"
                :disabled="i === questions.length - 1" :aria-label="t('questionnaire.moveDown')" :title="t('questionnaire.moveDown')" @click="move(i, 1)">↓</button>
              <RowActions>
                <button type="button" class="row-menu-item" @click="openEdit(question)">{{ t('questionnaire.edit') }}</button>
                <button type="button" class="row-menu-item row-menu-item-danger" @click="remove(question)">{{ t('questionnaire.remove') }}</button>
              </RowActions>
            </div>
          </li>
        </ul>
      </section>

      <div v-if="editor" class="mt-5">
        <h2 class="mb-3 text-lg font-semibold text-slate-800">
          {{ editor.question ? t('questionnaire.editTitle') : t('questionnaire.createTitle') }}
        </h2>
        <QuestionForm
          :key="editor.key"
          :event-id="eventId"
          :question="editor.question"
          :preset="editor.preset"
          :ticket-types="types"
          @saved="onSaved"
          @cancel="editor = null"
        />
      </div>

      <!-- Súhrn odpovedí — koľko obedov, koľko lôžok. -->
      <section v-if="summary.length" class="mt-5 rounded-2xl border border-slate-200 bg-white p-5">
        <h2 class="mb-3 text-lg font-semibold text-slate-800">{{ t('questionnaire.summaryTitle') }}</h2>
        <QuestionnaireSummary :summary="summary" />
      </section>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { showEvent } from '@/api/events'
import { indexTicketTypes } from '@/api/ticketTypes'
import { deleteQuestion, indexQuestions, questionnaireSummary, reorderQuestions } from '@/api/questionnaire'
import { t } from '@/i18n'
import { useToast } from '@/composables/useToast'
import { formatPrice } from '@/utils/money'
import EventTicketsTabs from '@/components/EventTicketsTabs.vue'
import QuestionForm from '@/components/QuestionForm.vue'
import QuestionnaireSummary from '@/components/QuestionnaireSummary.vue'
import RowActions from '@/components/RowActions.vue'
import type { QuestionItem, QuestionSummaryItem, TicketTypeItem } from '@/types'

const route = useRoute()
const toast = useToast()
const eventId = Number(route.params.id)

const loading = ref(false)
const loadError = ref<string | null>(null)
const eventName = ref('')
const questions = ref<QuestionItem[]>([])
const types = ref<TicketTypeItem[]>([])
const summary = ref<QuestionSummaryItem[]>([])

/** Otvorený formulár: nová otázka (s predvyplnením) alebo úprava existujúcej. */
const editor = ref<{ key: number; question: QuestionItem | null; preset: Partial<QuestionItem> | null } | null>(null)
let editorKey = 0

function presetOption(label: string) {
  return { label, priceAmount: null, capacity: null, isActive: true }
}

const presets = computed<{ key: string; question: Partial<QuestionItem> }[]>(() => [
  {
    key: 'fee',
    question: {
      label: t('questionnaire.presets.fee'),
      help: t('questionnaire.presets.feeHelp'),
      type: 'single_choice',
      scope: 'attendee',
      isRequired: true,
      editableByAttendee: false,
      options: [presetOption(t('questionnaire.presets.fee'))],
    },
  },
  {
    key: 'meal',
    question: {
      label: t('questionnaire.presets.meal'),
      type: 'single_choice',
      scope: 'attendee',
      isRequired: true,
      options: [presetOption(t('questionnaire.presets.mealNormal')), presetOption(t('questionnaire.presets.mealVegetarian')), presetOption(t('questionnaire.presets.mealDiet'))],
    },
  },
  {
    key: 'lunch',
    question: {
      label: t('questionnaire.presets.lunch'),
      type: 'single_choice',
      scope: 'attendee',
      isRequired: true,
      options: [presetOption(t('questionnaire.presets.yes')), presetOption(t('questionnaire.presets.no'))],
    },
  },
  {
    key: 'lodging',
    question: {
      label: t('questionnaire.presets.lodging'),
      help: t('questionnaire.presets.lodgingHelp'),
      type: 'single_choice',
      scope: 'attendee',
      isRequired: true,
      options: [presetOption(t('questionnaire.presets.lodgingYes')), presetOption(t('questionnaire.presets.no'))],
    },
  },
  {
    key: 'allergies',
    question: { label: t('questionnaire.presets.allergies'), type: 'textarea', scope: 'attendee', options: [] },
  },
])

function typeNames(question: QuestionItem): string {
  return types.value.filter(type => question.ticketTypeIds?.includes(type.id!)).map(type => type.name).join(', ')
}

function openNew(preset: Partial<QuestionItem> | null) {
  editor.value = { key: ++editorKey, question: null, preset }
}

function openEdit(question: QuestionItem) {
  editor.value = { key: ++editorKey, question, preset: null }
}

async function reload() {
  const [list, totals] = await Promise.all([indexQuestions(eventId), questionnaireSummary(eventId).catch(() => [])])
  questions.value = list
  summary.value = totals
}

async function onSaved() {
  editor.value = null
  await reload()
  toast.success(t('questionnaire.saved'))
}

async function move(index: number, delta: number) {
  const ids = questions.value.map(q => q.id!)
  const [moved] = ids.splice(index, 1)
  ids.splice(index + delta, 0, moved)
  try {
    questions.value = await reorderQuestions(eventId, ids)
  } catch {
    toast.error(t('questionnaire.saveFailed'))
  }
}

async function remove(question: QuestionItem) {
  if (!question.id || !confirm(t('questionnaire.removeConfirm', { name: question.label }))) return
  try {
    await deleteQuestion(eventId, question.id)
    await reload()
    toast.success(t('questionnaire.removed'))
  } catch {
    toast.error(t('questionnaire.saveFailed'))
  }
}

onMounted(async () => {
  loading.value = true
  try {
    const ev = await showEvent('dashboard', eventId)
    eventName.value = ev.name
    types.value = await indexTicketTypes(eventId)
    await reload()
  } catch {
    loadError.value = t('questionnaire.loadFailed')
  } finally {
    loading.value = false
  }
})
</script>
