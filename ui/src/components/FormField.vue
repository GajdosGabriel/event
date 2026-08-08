<template>
  <!-- Zaškrtávacie pole má opačné poradie (ovládač, potom text) — vlastná vetva. -->
  <label v-if="type === 'checkbox'" class="form-check" :class="wrapperClass" :style="wrapperStyle">
    <input v-model="field" type="checkbox" class="form-checkbox" v-bind="controlAttrs" />
    <span>
      <slot name="label">{{ label }}</slot>
      <span v-if="hint" class="form-hint block">{{ hint }}</span>
    </span>
  </label>

  <label v-else class="form-label" :class="wrapperClass" :style="wrapperStyle">
    <span v-if="label || $slots.label">
      <slot name="label">{{ label }}</slot><span v-if="required" class="form-required" aria-hidden="true">*</span>
    </span>

    <!-- Vlastný ovládač (SearchableSelect, HtmlEditor…) dostane `invalid` aj
         zápis hodnoty, aby vyzeral a fungoval ako natívne pole. -->
    <slot :value="field" :invalid="invalid" :update="update">
      <textarea
        v-if="type === 'textarea'"
        v-model="text"
        class="form-textarea"
        :class="{ invalid }"
        :required="required"
        :aria-invalid="invalid || undefined"
        v-bind="controlAttrs"
      ></textarea>

      <select
        v-else-if="type === 'select'"
        v-model="field"
        class="form-input"
        :class="{ invalid }"
        :required="required"
        :aria-invalid="invalid || undefined"
        v-bind="controlAttrs"
      >
        <slot name="options">
          <option v-for="opt in options" :key="String(opt.value)" :value="opt.value">{{ opt.label }}</option>
        </slot>
      </select>

      <PasswordInput
        v-else-if="type === 'password'"
        v-model="text"
        class="form-input"
        :class="{ invalid }"
        :required="required"
        v-bind="controlAttrs"
      />

      <DateTimeInput
        v-else-if="type === 'datetime'"
        v-model="text"
        class="form-input"
        :class="{ invalid }"
        :required="required"
        :aria-invalid="invalid || undefined"
        v-bind="controlAttrs"
      />

      <input
        v-else
        v-model="field"
        :type="type"
        class="form-input"
        :class="{ invalid }"
        :required="required"
        :aria-invalid="invalid || undefined"
        v-bind="controlAttrs"
      />
    </slot>

    <span v-if="error" class="field-error">{{ error }}</span>
    <span v-else-if="hint" class="form-hint">{{ hint }}</span>
    <slot name="footer" />
  </label>
</template>

<script setup lang="ts" generic="T extends FieldValue">
/**
 * Jedno pole formulára: popiska + ovládač + chyba, všetko v jednom.
 *
 * Kľúčové pravidlo — povinné pole nie je červené hneď po otvorení formulára,
 * ale až po validácii:
 *   • chyba zo servera (`error`) zafarbí pole okamžite,
 *   • prázdne povinné pole až po pokuse o odoslanie (`provideFormValidation()`),
 *   • rozpísaný nezmysel (zlý e-mail, URL) až po odchode z poľa — to zariadi
 *     CSS `:user-invalid` nad natívnym `required`/`type`.
 */
import { computed, useAttrs, type StyleValue } from 'vue'
import DateTimeInput from '@/components/DateTimeInput.vue'
import PasswordInput from '@/components/PasswordInput.vue'
import { useFormValidation } from '@/composables/useFormValidation'
import type { FieldOption, FieldValue } from '@/types'

type FieldType =
  | 'text' | 'email' | 'url' | 'tel' | 'number' | 'search' | 'date' | 'time'
  | 'password' | 'datetime' | 'textarea' | 'select' | 'checkbox'

defineOptions({ inheritAttrs: false })

const props = withDefaults(defineProps<{
  label?: string
  type?: FieldType
  required?: boolean
  /** Chyba z validácie na serveri — pole je červené hneď, ako príde. */
  error?: string | null
  /** Vysvetlivka pod poľom. Chyba má prednosť. */
  hint?: string
  /** Možnosti pre `type="select"`; alternatívou je slot `options`. */
  options?: FieldOption[]
  /** Orezať biele znaky (ekvivalent `v-model.trim`). */
  trim?: boolean
  /**
   * Prebehla už validácia? Bežne to pole vie z `provideFormValidation()`.
   * Explicitne sa hodí, keď má jedna stránka viac nezávislých formulárov
   * (napr. modál nad hlavným formulárom) — každý má vlastný stav.
   */
  validated?: boolean
}>(), {
  type: 'text',
})

const model = defineModel<T>()

const validation = useFormValidation()

/**
 * `<input type="number">` vracia reťazec a prázdne pole ako `''` — API čaká
 * číslo alebo `null`, tak to prevedieme tu (ekvivalent `v-model.number`).
 */
function normalize(value: T | undefined): T | undefined {
  if (props.type === 'number') {
    if (value === '' || value === null || value === undefined) return null as unknown as T
    const parsed = Number(value)
    return (Number.isNaN(parsed) ? null : parsed) as unknown as T
  }

  if (props.trim && typeof value === 'string') return value.trim() as unknown as T

  return value
}

const field = computed<T | undefined>({
  get: () => model.value,
  set: (value) => { model.value = normalize(value) },
})

/** Ovládače, ktoré vedia pracovať len s reťazcom (textarea, DateTimeInput, PasswordInput). */
const text = computed<string>({
  get: () => (model.value === null || model.value === undefined ? '' : String(model.value)),
  set: (value) => { model.value = normalize(value as unknown as T) },
})

function update(value: T | undefined) {
  field.value = value
}

function isBlank(value: unknown): boolean {
  return value === null || value === undefined || value === ''
}

const invalid = computed(() => {
  if (props.error) return true

  const validated = props.validated ?? validation.validated.value

  return Boolean(props.required) && validated && isBlank(model.value)
})

// `class`/`style` patria obalu, zvyšok atribútov (placeholder, min, maxlength,
// autocomplete, disabled…) ovládaču — preto `inheritAttrs: false`.
const attrs = useAttrs()
const wrapperClass = computed(() => attrs['class'])
const wrapperStyle = computed(() => attrs['style'] as StyleValue)
const controlAttrs = computed(() => {
  const { class: _class, style: _style, ...rest } = attrs
  return rest
})
</script>
