<template>
  <div class="auth-page">
    <div class="auth-card">
      <h1>{{ t('auth.forgot.title') }}</h1>
      <p>{{ t('auth.forgot.lead') }}</p>

      <div v-if="error" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ error }}</div>

      <!-- Po odoslaní formulár zmizne. Nechať ho na stránke by lákalo klikať
           znova, a druhá požiadavka do minúty už žiadny e-mail nepošle
           (brokerov throttle) — vyzeralo by to ako chyba. -->
      <div v-if="sent" class="grid gap-3">
        <div class="rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-700">
          {{ t('auth.forgot.sent') }}
        </div>
        <p class="text-sm text-slate-600">{{ t('auth.forgot.sentHint') }}</p>
      </div>

      <form v-else class="grid gap-3" @submit.prevent="submit">
        <FormField v-model="email" type="email" :label="t('auth.forgot.email')" required autocomplete="email" />
        <button type="submit" class="btn btn-primary" :disabled="loading">
          {{ loading ? t('auth.forgot.submitting') : t('auth.forgot.submit') }}
        </button>
      </form>

      <small><RouterLink to="/login">{{ t('auth.forgot.backToLogin') }}</RouterLink></small>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useRoute } from 'vue-router'
import { forgotPassword } from '@/api/auth'
import { t } from '@/i18n'
import { provideFormValidation } from '@/composables/useFormValidation'
import FormField from '@/components/FormField.vue'

const route = useRoute()
const validation = provideFormValidation()

// Adresu predvyplní prihlásenie, z ktorého sem človek prišiel — už ju raz
// napísal a písať ju druhýkrát je zbytočná prekážka.
const email = ref(typeof route.query.email === 'string' ? route.query.email : '')
const sent = ref(false)
const error = ref<string | null>(null)
const loading = ref(false)

async function submit() {
  validation.markValidated()
  error.value = null
  loading.value = true
  try {
    await forgotPassword(email.value)
    sent.value = true
  } catch (e: unknown) {
    // Sem sa dostane len zamietnutie limiterom alebo výpadok — samotné
    // „adresa neexistuje" backend nikdy nevráti.
    error.value = (e as { response?: { data?: { message?: string } } })?.response?.data?.message
      ?? t('auth.forgot.failed')
  } finally {
    loading.value = false
  }
}
</script>
