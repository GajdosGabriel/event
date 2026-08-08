import { inject, provide, ref, type InjectionKey, type Ref } from 'vue'

/**
 * Kedy smie pole zčervenieť.
 *
 * Prázdny formulár po otvorení má vyzerať prázdny, nie pokazený — povinné pole
 * sa preto nefarbí, kým sa ho človek nedotkol alebo kým neprišiel pokus
 * o odoslanie. Natívne `<input required>` to zvládne samo cez CSS `:user-invalid`;
 * tento príznak je pre ovládače bez natívnej validácie (SearchableSelect,
 * HtmlEditor) a pre formuláre, ktoré sa odosielajú tlačidlom mimo `<form>`.
 */
export interface FormValidation {
  /** Prebehol už pokus o odoslanie? Pred ním sa prázdne povinné pole nefarbí. */
  validated: Ref<boolean>
  /** Volá formulár na začiatku submitu — odtiaľ sa chyby ukazujú. */
  markValidated: () => void
  /** Späť do „ešte sa nevalidovalo" — napr. po otvorení modálu načisto. */
  reset: () => void
}

const FORM_VALIDATION: InjectionKey<FormValidation> = Symbol('formValidation')

/** Mimo formulára ostáva stav natrvalo „ešte sa nevalidovalo". */
const NEVER_VALIDATED: FormValidation = {
  validated: ref(false),
  markValidated: () => {},
  reset: () => {},
}

/** Zavolá formulár (rodič polí) v `setup()`. */
export function provideFormValidation(): FormValidation {
  const validated = ref(false)

  const api: FormValidation = {
    validated,
    markValidated: () => { validated.value = true },
    reset: () => { validated.value = false },
  }

  provide(FORM_VALIDATION, api)

  return api
}

/** Zavolá pole (FormField). */
export function useFormValidation(): FormValidation {
  return inject(FORM_VALIDATION, NEVER_VALIDATED)
}
