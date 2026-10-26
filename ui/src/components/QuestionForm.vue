<template>
  <form class="space-y-4 rounded-2xl border border-slate-200 bg-white p-5" @submit.prevent="submit">
    <div class="grid gap-4 md:grid-cols-2">
      <FormField v-model="form.label" :label="t('questionnaire.form.label')" required trim maxlength="250" :error="fieldError('label')" />
      <FormField v-model="form.type" type="select" :label="t('questionnaire.form.type')" :options="typeOptions" :error="fieldError('type')" />
    </div>

    <FormField v-model="form.help" :label="t('questionnaire.form.help')" :hint="t('questionnaire.form.helpHint')" trim maxlength="500" />

    <FormField v-model="form.scope" type="radio" :label="t('questionnaire.form.scope')" :options="scopeOptions" />

    <!-- Pre ktoré typy lístkov sa otázka pýta — len pri otázke za účastníka. -->
    <fieldset v-if="form.scope === 'attendee' && ticketTypes.length > 1" class="form-radio-group">
      <legend class="form-radio-legend">{{ t('questionnaire.form.types') }}</legend>
      <FormField
        v-for="type in ticketTypes"
        :key="type.id"
        type="checkbox"
        :label="type.kind === 'workshop' ? `${type.name} (${t('tickets.settings.workshop')})` : type.name"
        :model-value="form.ticketTypeIds.includes(type.id!)"
        @update:model-value="toggleType(type.id!, Boolean($event))"
      />
      <span class="form-hint">{{ t('questionnaire.form.typesHint') }}</span>
    </fieldset>

    <!-- Možnosti výberu: každá môže mať príplatok a kapacitu (ubytovanie, obed). -->
    <div v-if="isChoice" class="space-y-2">
      <p class="text-sm font-medium text-slate-700">{{ t('questionnaire.form.options') }}</p>
      <p class="text-xs text-slate-500">{{ t('questionnaire.form.optionsHint') }}</p>
      <div v-for="(option, i) in form.options" :key="option.key" class="grid grid-cols-[1fr_auto] items-end gap-2 rounded-lg bg-slate-50 p-2 sm:grid-cols-[1fr_8rem_7rem_auto]">
        <FormField v-model="option.label" class="col-span-2 sm:col-span-1" :label="t('questionnaire.form.optionLabel')" required trim maxlength="250" :error="fieldError(`options.${i}.label`)" />
        <FormField v-model="option.price" type="number" min="0" step="0.01" :label="t('questionnaire.form.optionPrice')" />
        <FormField v-model="option.capacity" type="number" min="1" step="1" :label="t('questionnaire.form.optionCapacity')" />
        <button type="button" class="mb-1 rounded-md p-2 text-slate-400 hover:bg-slate-200 hover:text-red-600"
          :title="t('questionnaire.form.optionRemove')" :aria-label="t('questionnaire.form.optionRemove')"
          @click="form.options.splice(i, 1)">
          <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
          </svg>
        </button>
      </div>
      <p v-if="fieldError('options')" class="field-error">{{ fieldError('options') }}</p>
      <button type="button" class="btn btn-secondary" @click="addOption()">{{ t('questionnaire.form.optionAdd') }}</button>
    </div>

    <div class="space-y-2">
      <FormField v-model="form.isRequired" type="checkbox" :label="t('questionnaire.form.required')" />
      <FormField v-model="form.editableByAttendee" type="checkbox" :label="t('questionnaire.form.editable')" :hint="t('questionnaire.form.editableHint')" />
      <FormField v-if="question" v-model="form.isActive" type="checkbox" :label="t('questionnaire.form.active')" :hint="t('questionnaire.form.activeHint')" />
    </div>

    <p v-if="message" class="text-sm text-red-600">{{ message }}</p>

    <div class="flex flex-wrap gap-2">
      <button type="submit" class="btn btn-primary" :disabled="saving">
        {{ saving ? t('questionnaire.saving') : t('questionnaire.save') }}
      </button>
      <button type="button" class="btn btn-secondary" :disabled="saving" @click="emit('cancel')">{{ t('common.cancel') }}</button>
    </div>
  </form>
</template>

<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { t } from '@/i18n'
import { createQuestion, updateQuestion, type QuestionPayload } from '@/api/questionnaire'
import { provideFormValidation } from '@/composables/useFormValidation'
import FormField from '@/components/FormField.vue'
import type { FieldOption, QuestionItem, QuestionType, TicketTypeItem } from '@/types'

const props = defineProps<{
  eventId: number
  /** Upravovaná otázka; bez nej sa zakladá nová. */
  question?: QuestionItem | null
  /** Predvyplnenie novej otázky (šablóna „Strava", „Ubytovanie"…). */
  preset?: Partial<QuestionItem> | null
  ticketTypes: TicketTypeItem[]
}>()

const emit = defineEmits<{ saved: [question: QuestionItem]; cancel: [] }>()

const validation = provideFormValidation()

interface OptionDraft {
  key: number
  id?: number
  label: string
  /** V eurách (vo formulári), na server ide v centoch. */
  price: number | null
  capacity: number | null
  isActive: boolean
}

let nextKey = 1
const source = props.question ?? props.preset ?? {}

const form = reactive({
  label: source.label ?? '',
  help: source.help ?? '',
  type: (source.type ?? 'single_choice') as QuestionType,
  scope: source.scope ?? 'attendee',
  isRequired: source.isRequired ?? false,
  editableByAttendee: source.editableByAttendee ?? true,
  isActive: source.isActive ?? true,
  ticketTypeIds: [...(source.ticketTypeIds ?? [])],
  // Vypnuté možnosti (už zvolené niekým) sa vo formulári nezobrazujú, ale
  // posielajú sa späť, aby ich server nezmazal.
  options: (source.options ?? []).filter(o => o.isActive).map(o => ({
    key: nextKey++,
    id: o.id,
    label: o.label,
    price: o.priceAmount ? o.priceAmount / 100 : null,
    capacity: o.capacity,
    isActive: true,
  })) as OptionDraft[],
})

const hiddenOptions = (source.options ?? []).filter(o => !o.isActive)

if (!form.options.length) {
  addOption()
  addOption()
}

const saving = ref(false)
const message = ref<string | null>(null)
const errors = ref<Record<string, string[]>>({})

const isChoice = computed(() => form.type === 'single_choice' || form.type === 'multi_choice')

const typeOptions = computed<FieldOption[]>(() =>
  (['single_choice', 'multi_choice', 'text', 'textarea', 'checkbox', 'number', 'date'] as QuestionType[])
    .map(value => ({ value, label: t(`questionnaire.types.${value}`) })),
)

const scopeOptions = computed<FieldOption[]>(() => [
  { value: 'attendee', label: t('questionnaire.scope.attendee'), hint: t('questionnaire.scope.attendeeHint') },
  { value: 'order', label: t('questionnaire.scope.order'), hint: t('questionnaire.scope.orderHint') },
])

function addOption() {
  form.options.push({ key: nextKey++, label: '', price: null, capacity: null, isActive: true })
}

function toggleType(id: number, checked: boolean) {
  form.ticketTypeIds = checked ? [...form.ticketTypeIds, id] : form.ticketTypeIds.filter(x => x !== id)
}

function fieldError(key: string): string | null {
  return errors.value[key]?.[0] ?? null
}

function payload(): QuestionPayload {
  return {
    label: form.label,
    help: form.help || null,
    type: form.type,
    scope: form.scope,
    is_required: form.isRequired,
    editable_by_attendee: form.editableByAttendee,
    is_active: form.isActive,
    ticket_type_ids: form.scope === 'attendee' && form.ticketTypeIds.length ? form.ticketTypeIds : null,
    options: isChoice.value
      ? [
          ...form.options.map(o => ({
            id: o.id,
            label: o.label,
            price_amount: o.price ? Math.round(o.price * 100) : null,
            capacity: o.capacity || null,
            is_active: true,
          })),
          ...hiddenOptions.map(o => ({ id: o.id, label: o.label, price_amount: o.priceAmount, capacity: o.capacity, is_active: false })),
        ]
      : [],
  }
}

async function submit() {
  validation.markValidated()
  saving.value = true
  message.value = null
  errors.value = {}
  try {
    const saved = props.question?.id
      ? await updateQuestion(props.eventId, props.question.id, payload())
      : await createQuestion(props.eventId, payload())
    emit('saved', saved)
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }
    errors.value = err.response?.data?.errors ?? {}
    message.value = err.response?.data?.message ?? t('questionnaire.saveFailed')
  } finally {
    saving.value = false
  }
}
</script>
