<template>
  <span class="password-field relative block">
    <input
      ref="input"
      :type="visible ? 'text' : 'password'"
      :value="modelValue"
      class="pr-10"
      v-bind="$attrs"
      @input="emit('update:modelValue', ($event.target as HTMLInputElement).value)"
    />
    <!-- Tlačidlo býva vnútri <label>, preto potláčame default: inak label preposiela klik
         na input a fokus/klik sa nám „stratí“ ešte pred prepnutím. -->
    <button
      type="button"
      class="toggle absolute inset-y-0 right-0 grid w-10 place-items-center rounded-r-lg text-slate-400 hover:text-slate-700"
      :aria-label="visible ? t('fields.passwordHide') : t('fields.passwordShow')"
      :title="visible ? t('fields.passwordHide') : t('fields.passwordShow')"
      :aria-pressed="visible"
      tabindex="-1"
      @mousedown.prevent
      @click.stop.prevent="toggle"
    >
      <svg v-if="visible" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <path d="M3 3l18 18" />
        <path d="M10.6 10.6a2 2 0 002.8 2.8" />
        <path d="M9.9 5.2A9.7 9.7 0 0112 5c4.6 0 8.3 3.2 9.5 7a11.6 11.6 0 01-3.3 4.7M6.5 6.7A11.7 11.7 0 002.5 12c1.2 3.8 4.9 7 9.5 7 1.4 0 2.7-.3 3.9-.8" />
      </svg>
      <svg v-else class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <path d="M2.5 12C3.7 8.2 7.4 5 12 5s8.3 3.2 9.5 7c-1.2 3.8-4.9 7-9.5 7s-8.3-3.2-9.5-7z" />
        <circle cx="12" cy="12" r="3" />
      </svg>
    </button>
  </span>
</template>

<script setup lang="ts">
import { nextTick, ref, useTemplateRef } from 'vue'
import { t } from '@/i18n'

defineOptions({ inheritAttrs: false })

defineProps<{
  modelValue: string
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', value: string): void
}>()

const input = useTemplateRef<HTMLInputElement>('input')
const visible = ref(false)

// Zmena type() input v prehliadači znovu vykreslí, tak mu vrátime fokus aj pozíciu kurzora.
async function toggle() {
  const el = input.value
  const start = el?.selectionStart ?? null
  const focused = document.activeElement === el

  visible.value = !visible.value

  if (!focused) return
  await nextTick()
  input.value?.focus()
  if (start !== null) {
    try {
      input.value?.setSelectionRange(start, start)
    } catch {
      // niektoré typy inputov selection nepodporujú — nevadí
    }
  }
}
</script>

<style scoped>
/* Edge/IE kreslí nad pole vlastné oko na tom istom mieste; tam sa heslo ukáže len počas
   držania myši, čo pôsobí ako pokazený prepínač. Necháme len ten náš. */
.password-field input::-ms-reveal,
.password-field input::-ms-clear {
  display: none;
}
</style>
