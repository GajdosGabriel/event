<template>
  <FormField
    v-model="model"
    type="email"
    :label="label"
    :error="error"
    :maxlength="maxlength"
    trim
    v-bind="$attrs"
  >
    <!-- Stav overenia nie je chyba formulára — adresa sa potvrdzuje až po
         uložení, klikom na odkaz v e-maile. Preto pod poľom, nie červeno. -->
    <template #footer>
      <div v-if="status" class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs">
        <span class="inline-flex items-center gap-1.5" :class="status.textClass">
          <span class="inline-block h-1.5 w-1.5 shrink-0 rounded-full" :class="status.dotClass"></span>
          {{ status.label }}
        </span>

        <button
          v-if="canResend"
          type="button"
          class="text-blue-700 underline underline-offset-2 hover:text-blue-900 disabled:cursor-not-allowed disabled:text-slate-400 disabled:no-underline"
          :disabled="sending || state?.canResend === false"
          @click="resend"
        >
          {{ sending ? 'Posielam…' : 'Poslať overenie znova' }}
        </button>
      </div>
    </template>
  </FormField>
</template>

<script setup lang="ts">
/**
 * Kontaktný e-mail s overením.
 *
 * Jediné pole, cez ktoré sa v aplikácii zadáva kontaktná adresa kanála,
 * miesta, podujatia či organizátora — aby overený a neoverený stav vyzeral
 * všade rovnako a nedal sa nikde zabudnúť.
 *
 * Samotný e-mail posiela API po uložení formulára (nie odtiaľto), preto sa pri
 * rozpísanej zmene ukazuje len to, čo sa stane po uložení.
 */
import { computed, ref } from 'vue'
import FormField from '@/components/FormField.vue'
import { useToast } from '@/composables/useToast'
import { resendContactEmail } from '@/api/contactEmail'
import type { ContactEmailState, ContactEmailTarget } from '@/types'

defineOptions({ inheritAttrs: false })

const props = withDefaults(defineProps<{
  /** Typ modelu, ktorému adresa patrí. */
  target: ContactEmailTarget
  /** Id modelu; `null` pri zakladaní — vtedy ešte nie je čo overovať. */
  targetId?: number | null
  /** Stav overenia z API (`email_verification` v detaile modelu). */
  state?: ContactEmailState | null
  /** Adresa, ktorej sa stav týka — teda tá naposledy uložená. */
  savedEmail?: string | null
  label?: string
  error?: string | null
  maxlength?: number | string
}>(), {
  targetId: null,
  state: null,
  savedEmail: '',
  error: null,
  label: 'E-mail',
  maxlength: 150,
})

const emit = defineEmits<{ resent: [] }>()

const model = defineModel<string>({ default: '' })
const toast = useToast()
const sending = ref(false)

const currentEmail = computed(() => (model.value ?? '').trim())
const savedEmail = computed(() => (props.savedEmail ?? '').trim())

/** Rozpísaná zmena, ktorá ešte nie je uložená — stav z API na ňu neplatí. */
const isDirty = computed(() =>
  currentEmail.value.toLowerCase() !== savedEmail.value.toLowerCase()
)

const status = computed(() => {
  if (currentEmail.value === '') return null

  if (isDirty.value) {
    return {
      label: 'Po uložení pošleme na túto adresu žiadosť o potvrdenie.',
      textClass: 'text-slate-500',
      dotClass: 'bg-slate-400',
    }
  }

  if (props.state?.verified) {
    return {
      label: 'Adresa je overená.',
      textClass: 'text-green-600',
      dotClass: 'bg-green-500',
    }
  }

  return {
    label: props.state?.pending
      ? 'Neoverená — na adresu sme poslali žiadosť o potvrdenie.'
      : 'Neoverená — adresa zatiaľ nebola potvrdená.',
    textClass: 'text-amber-700',
    dotClass: 'bg-amber-500',
  }
})

/** Poslať znova má zmysel len na uloženej, ešte neoverenej adrese. */
const canResend = computed(() =>
  props.targetId != null
  && currentEmail.value !== ''
  && !isDirty.value
  && props.state !== null
  && props.state !== undefined
  && !props.state.verified
)

async function resend() {
  if (props.targetId == null || sending.value) return

  sending.value = true
  try {
    const res = await resendContactEmail(props.target, props.targetId)
    toast.success(res.message || 'Overovací e-mail sme poslali znova.')
    emit('resent')
  } catch (e: unknown) {
    const message = (e as { response?: { data?: { message?: string } } })?.response?.data?.message
    // 429 hlási globálny interceptor sám — dvakrát by to bolo len na obtiaž.
    const status = (e as { response?: { status?: number } })?.response?.status
    if (status !== 429) toast.error(message ?? 'E-mail sa nepodarilo poslať.')
  } finally {
    sending.value = false
  }
}
</script>
