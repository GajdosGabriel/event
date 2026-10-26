<template>
  <!-- Odpovede dotazníka objednávky — prehľad a úprava. Účastník na stránke
       lístka (do uzávierky, len odomknuté otázky), organizátor v zozname
       prihlásených (všetko). -->
  <div v-if="hasContent" class="space-y-3">
    <div class="flex items-center justify-between gap-2">
      <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ t('questionnaire.answersTitle') }}</p>
      <button v-if="canEdit && !editing" type="button" class="text-xs font-medium text-blue-600 hover:underline" @click="startEdit">
        {{ t('questionnaire.edit') }}
      </button>
    </div>

    <!-- Prehľad -->
    <template v-if="!editing">
      <dl v-if="ticket.answers?.length" class="space-y-1 text-sm">
        <div v-for="answer in ticket.answers" :key="answer.questionId" class="flex flex-wrap gap-x-2">
          <dt class="text-slate-500">{{ answer.label }}:</dt>
          <dd class="font-medium text-slate-800">
            {{ answer.display }}<span v-if="answer.priceAmount" class="ml-1 font-normal text-slate-500">(+{{ formatPrice(answer.priceAmount, ticket.priceCurrency) }})</span>
          </dd>
        </div>
      </dl>
      <div v-for="(admission, i) in seats" :key="admission.uuid" class="text-sm">
        <p class="font-semibold text-slate-700">{{ seatLabel(admission, i) }}</p>
        <dl v-if="admission.answers?.length" class="space-y-1">
          <div v-for="answer in admission.answers" :key="answer.questionId" class="flex flex-wrap gap-x-2">
            <dt class="text-slate-500">{{ answer.label }}:</dt>
            <dd class="font-medium text-slate-800">
            {{ answer.display }}<span v-if="answer.priceAmount" class="ml-1 font-normal text-slate-500">(+{{ formatPrice(answer.priceAmount, ticket.priceCurrency) }})</span>
          </dd>
          </div>
        </dl>
        <p v-else class="text-xs text-slate-400">{{ t('questionnaire.noAnswers') }}</p>
      </div>
      <p v-if="!organizer && ticket.answersEditable && ticket.answersEditableUntil" class="text-xs text-slate-400">
        {{ t('questionnaire.editableUntil', { date: formatDate(ticket.answersEditableUntil) }) }}
      </p>
    </template>

    <!-- Úprava -->
    <form v-else class="space-y-3" @submit.prevent="submit">
      <div v-if="orderQs.length" class="space-y-2 rounded-lg bg-slate-50 p-3">
        <p class="text-xs font-semibold text-slate-600">{{ t('questionnaire.orderTitle') }}</p>
        <QuestionnaireFields
          v-model="orderDraft"
          :questions="orderQs"
          :errors="answerErrors(errors, 'answers')"
          :currency="ticket.priceCurrency"
          :respect-locks="!organizer"
          :saved="ticket.answers"
        />
      </div>

      <template v-for="(admission, i) in seats" :key="admission.uuid">
        <div v-if="seatQuestions(admission).length" class="space-y-2 rounded-lg bg-slate-50 p-3">
          <p class="text-xs font-semibold text-slate-600">{{ seatLabel(admission, i) }}</p>
          <QuestionnaireFields
            v-model="seatDrafts[admission.id!]"
            :questions="seatQuestions(admission)"
            :errors="answerErrors(errors, `admissions.${admission.id}`)"
            :currency="ticket.priceCurrency"
            :respect-locks="!organizer"
            :saved="admission.answers"
          />
        </div>
      </template>

      <p v-if="message" class="text-sm text-red-600">{{ message }}</p>

      <div class="flex flex-wrap gap-2">
        <button type="submit" :disabled="saving" class="btn btn-primary">
          {{ saving ? t('questionnaire.saving') : t('questionnaire.save') }}
        </button>
        <button type="button" class="btn btn-secondary" :disabled="saving" @click="editing = false">
          {{ t('common.cancel') }}
        </button>
      </div>
    </form>
  </div>
</template>

<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { t, localeTag } from '@/i18n'
import { answerErrors, answersToMap, appliesToType, orderQuestions } from '@/api/questionnaire'
import type { TicketAnswersPayload } from '@/api/tickets'
import { provideFormValidation } from '@/composables/useFormValidation'
import { seatName } from '@/utils/userDisplay'
import { formatPrice } from '@/utils/money'
import QuestionnaireFields from '@/components/QuestionnaireFields.vue'
import type { AdmissionItem, AnswerMap, QuestionItem, TicketItem } from '@/types'

const props = defineProps<{
  ticket: TicketItem
  questions: QuestionItem[]
  /** Organizátor mení aj zamknuté otázky a po uzávierke. */
  organizer?: boolean
  save: (payload: TicketAnswersPayload) => Promise<TicketItem>
}>()

const emit = defineEmits<{ saved: [ticket: TicketItem] }>()

const validation = provideFormValidation()

const editing = ref(false)
const saving = ref(false)
const message = ref<string | null>(null)
const errors = ref<Record<string, string[]>>({})
const orderDraft = ref<AnswerMap>({})
const seatDrafts = reactive<Record<number, AnswerMap>>({})

const orderQs = computed(() => orderQuestions(props.questions))

/** Miesta, ktorých sa dotazník týka — zrušené už nie. */
const seats = computed(() => props.ticket.admissions.filter(a => a.status !== 'cancelled' && seatQuestions(a).length))

function seatQuestions(admission: AdmissionItem): QuestionItem[] {
  const type = admission.ticketType
  if (!type) return []
  return props.questions.filter(q => appliesToType(q, { id: type.id, kind: type.kind ?? 'ticket' }))
}

const hasContent = computed(() => orderQs.value.length > 0 || seats.value.length > 0 || Boolean(props.ticket.answers?.length))

const canEdit = computed(() => {
  if (props.ticket.status === 'cancelled') return false
  if (props.organizer) return true
  if (!props.ticket.answersEditable) return false
  return props.questions.some(q => q.editableByAttendee)
})

function seatLabel(admission: AdmissionItem, index: number): string {
  return seatName(props.ticket, admission) || t('public.ticket.admission', { n: index + 1 })
}

function formatDate(value: string): string {
  return new Date(value).toLocaleString(localeTag(), { day: 'numeric', month: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

function startEdit() {
  orderDraft.value = answersToMap(orderQs.value, props.ticket.answers)
  for (const key of Object.keys(seatDrafts)) delete seatDrafts[Number(key)]
  for (const admission of seats.value) {
    seatDrafts[admission.id!] = answersToMap(seatQuestions(admission), admission.answers)
  }
  errors.value = {}
  message.value = null
  validation.reset()
  editing.value = true
}

async function submit() {
  validation.markValidated()
  saving.value = true
  errors.value = {}
  message.value = null
  try {
    const ticket = await props.save({
      answers: orderQs.value.length ? orderDraft.value : undefined,
      admissions: { ...seatDrafts },
    })
    editing.value = false
    emit('saved', ticket)
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }
    errors.value = err.response?.data?.errors ?? {}
    message.value = errors.value['answers']?.[0] ?? err.response?.data?.message ?? t('questionnaire.saveFailed')
  } finally {
    saving.value = false
  }
}
</script>
