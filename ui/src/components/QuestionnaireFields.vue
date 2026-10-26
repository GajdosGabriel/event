<template>
  <!-- Otázky dotazníka (strava, ubytovanie…) — registračný formulár, stránka
       lístka aj úprava v dashboarde. Hodnoty drží rodič (v-model = AnswerMap). -->
  <div v-if="questions.length" class="space-y-3">
    <template v-for="question in questions" :key="question.id">
      <!-- Odpoveď, ktorú účastník už meniť nesmie — len sa ukáže. -->
      <div v-if="isReadonly(question)" class="text-sm">
        <p class="text-xs font-medium text-slate-500">{{ question.label }}</p>
        <p class="text-slate-800">{{ readonlyDisplay(question) || '—' }}</p>
      </div>

      <!-- Povinný poplatok (jediná možnosť) — nie je čo vyberať, len sa ukáže. -->
      <div v-else-if="isFixedChoice(question)" class="flex items-baseline justify-between gap-3 rounded-lg bg-white px-3 py-2 text-sm ring-1 ring-slate-200">
        <span class="min-w-0 text-slate-700">
          {{ question.label }}
          <span v-if="question.help" class="block text-xs text-slate-500">{{ question.help }}</span>
        </span>
        <span class="shrink-0 font-semibold text-slate-900">{{ fixedLabel(question) }}</span>
      </div>

      <FormField
        v-else-if="question.type === 'single_choice'"
        type="radio"
        :label="question.label"
        :hint="question.help ?? undefined"
        :required="question.isRequired"
        :error="errors?.[question.id!]"
        :options="radioOptions(question)"
        :model-value="singleValue(question)"
        @update:model-value="set(question, $event as number | null)"
      />

      <fieldset v-else-if="question.type === 'multi_choice'" class="form-radio-group">
        <legend class="form-radio-legend">
          {{ question.label }}<span v-if="question.isRequired" class="form-required" aria-hidden="true">*</span>
        </legend>
        <FormField
          v-for="option in visibleOptions(question)"
          :key="option.id"
          type="checkbox"
          :label="optionLabel(option)"
          :hint="optionHint(question, option)"
          :disabled="optionDisabled(question, option)"
          :model-value="multiValue(question).includes(option.id!)"
          @update:model-value="toggle(question, option.id!, Boolean($event))"
        />
        <span v-if="errors?.[question.id!]" class="field-error">{{ errors[question.id!] }}</span>
        <span v-else-if="question.help" class="form-hint">{{ question.help }}</span>
      </fieldset>

      <FormField
        v-else-if="question.type === 'checkbox'"
        type="checkbox"
        :label="question.label + (question.isRequired ? ' *' : '')"
        :hint="question.help ?? undefined"
        :required="question.isRequired"
        :error="errors?.[question.id!]"
        :model-value="modelValue[question.id!] === true"
        @update:model-value="set(question, Boolean($event))"
      />

      <FormField
        v-else
        :type="question.type"
        :label="question.label"
        :hint="question.help ?? undefined"
        :required="question.isRequired"
        :error="errors?.[question.id!]"
        :maxlength="question.type === 'textarea' ? 2000 : question.type === 'text' ? 500 : undefined"
        :model-value="scalarValue(question)"
        @update:model-value="set(question, ($event ?? null) as string | number | null)"
      />
    </template>
  </div>
</template>

<script setup lang="ts">
import { t } from '@/i18n'
import { formatPrice } from '@/utils/money'
import { isFixedChoice } from '@/api/questionnaire'
import FormField from '@/components/FormField.vue'
import type { AnswerMap, AnswerValue, FieldOption, QuestionItem, QuestionOptionItem, TicketAnswerItem } from '@/types'

const props = defineProps<{
  questions: QuestionItem[]
  modelValue: AnswerMap
  /** Chyby zo servera podľa id otázky. */
  errors?: Record<number, string>
  currency?: string | null
  /**
   * Úprava účastníkom: otázky, ktoré organizátor zamkol, sa len zobrazia
   * (s uloženou odpoveďou). Organizátor mení všetko.
   */
  respectLocks?: boolean
  /** Uložené odpovede — pre zamknuté otázky a možnosti, ktoré už človek má. */
  saved?: TicketAnswerItem[]
}>()

const emit = defineEmits<{ 'update:modelValue': [value: AnswerMap] }>()

function set(question: QuestionItem, value: AnswerValue) {
  emit('update:modelValue', { ...props.modelValue, [question.id!]: value })
}

function isReadonly(question: QuestionItem): boolean {
  return Boolean(props.respectLocks) && !question.editableByAttendee
}

function readonlyDisplay(question: QuestionItem): string {
  return props.saved?.find(a => a.questionId === question.id)?.display ?? ''
}

function savedOptionIds(question: QuestionItem): number[] {
  return props.saved?.find(a => a.questionId === question.id)?.optionIds ?? []
}

/** Vypnutú možnosť ukážeme len tomu, kto ju už má zvolenú. */
function visibleOptions(question: QuestionItem): QuestionOptionItem[] {
  const saved = savedOptionIds(question)
  return question.options.filter(o => o.isActive || saved.includes(o.id!))
}

function soldOut(question: QuestionItem, option: QuestionOptionItem): boolean {
  return option.remainingCapacity === 0 && !savedOptionIds(question).includes(option.id!)
}

function optionDisabled(question: QuestionItem, option: QuestionOptionItem): boolean {
  if (!soldOut(question, option)) return false
  // Už zaškrtnutú (v tomto formulári) necháme odškrtnúť.
  const value = props.modelValue[question.id!]
  return !(Array.isArray(value) ? value.includes(option.id!) : value === option.id)
}

function optionLabel(option: QuestionOptionItem): string {
  return option.priceAmount
    ? `${option.label} (+${formatPrice(option.priceAmount, props.currency ?? 'EUR')})`
    : option.label
}

function optionHint(question: QuestionItem, option: QuestionOptionItem): string | undefined {
  if (soldOut(question, option)) return t('questionnaire.soldOut')
  if (option.remainingCapacity !== null && option.remainingCapacity !== undefined) {
    return t('questionnaire.remaining', { n: option.remainingCapacity })
  }
  return undefined
}

function fixedLabel(question: QuestionItem): string {
  const option = visibleOptions(question)[0]
  return option?.priceAmount ? formatPrice(option.priceAmount, props.currency ?? 'EUR') : option?.label ?? ''
}

function radioOptions(question: QuestionItem): FieldOption[] {
  const options: FieldOption[] = visibleOptions(question).map(option => ({
    value: option.id!,
    label: optionLabel(option),
    hint: optionHint(question, option),
    disabled: optionDisabled(question, option),
  }))

  // Nepovinný výber sa musí dať aj zrušiť — prepínač sám odškrtnúť nejde.
  if (!question.isRequired) options.push({ value: null, label: t('questionnaire.noAnswer') })

  return options
}

function singleValue(question: QuestionItem): number | null {
  const value = props.modelValue[question.id!]
  return typeof value === 'number' ? value : null
}

function multiValue(question: QuestionItem): number[] {
  const value = props.modelValue[question.id!]
  return Array.isArray(value) ? value : []
}

function toggle(question: QuestionItem, optionId: number, checked: boolean) {
  const current = multiValue(question).filter(id => id !== optionId)
  set(question, checked ? [...current, optionId] : current)
}

function scalarValue(question: QuestionItem): string | number | null {
  const value = props.modelValue[question.id!]
  return typeof value === 'string' || typeof value === 'number' ? value : null
}
</script>
