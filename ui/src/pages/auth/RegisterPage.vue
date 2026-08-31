<template>
  <div class="auth-page">
    <div class="auth-card">
      <h1>{{ t('auth.register.title') }}</h1>
      <p>{{ t('auth.register.lead') }}</p>

      <div v-if="error" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ error }}</div>
      <div v-if="success" class="rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-700">
        {{ t('auth.register.sent') }}
      </div>

      <template v-if="!success">
        <form class="grid gap-3" @submit.prevent="submit">
          <FormField v-model="form.display_name" :label="t('auth.register.name')" required />
          <FormField v-model="form.email" type="email" :label="t('auth.register.email')" required />
          <FormField v-model="form.password" type="password" :label="t('auth.register.password')" required autocomplete="new-password" />
          <FormField v-model="form.password_confirmation" type="password" :label="t('auth.register.passwordConfirm')" required autocomplete="new-password" />

          <TermsConsentField v-model="form.terms_accepted" :error="termsError" />

          <button type="submit" class="btn btn-primary" :disabled="loading">
            {{ loading ? t('auth.register.submitting') : t('auth.register.submit') }}
          </button>
        </form>

        <div class="social-auth grid gap-2">
          <div class="social-divider"><span>{{ t('auth.social.or') }}</span></div>
          <GoogleSignInButton context="signup" @credential="onGoogleCredential" />
        </div>
      </template>

      <small>{{ t('auth.register.hasAccount') }} <RouterLink to="/login">{{ t('auth.register.loginLink') }}</RouterLink></small>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { register } from '@/api/auth'
import { useAuthStore } from '@/stores/auth'
import { t } from '@/i18n'
import { provideFormValidation } from '@/composables/useFormValidation'
import FormField from '@/components/FormField.vue'
import GoogleSignInButton from '@/components/auth/GoogleSignInButton.vue'
import TermsConsentField from '@/components/TermsConsentField.vue'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const validation = provideFormValidation()

// Adresu vie predvyplniť ten, kto sem posiela (napr. pozvánka do tímu kanála) —
// tá sa musí zhodovať s adresou pozvánky, inak ju účet neprijme.
const prefillEmail = typeof route.query.email === 'string' ? route.query.email : ''

const form = ref({ display_name: '', email: prefillEmail, password: '', password_confirmation: '', terms_accepted: false })
const error = ref<string | null>(null)
const success = ref(false)
const loading = ref(false)

// Prehliadač nezaškrtnuté políčko cez natívne `required` nepustí ďalej, no
// bez JavaScriptu alebo pri obídení formulára to odmietne až API — jeho
// odpoveď preto priradíme priamo k poľu, nielen do hlavičky formulára.
const termsError = ref<string | null>(null)

async function submit() {
  validation.markValidated()
  error.value = null
  termsError.value = null

  if (!form.value.terms_accepted) {
    termsError.value = t('auth.register.termsRequired')
    return
  }

  loading.value = true
  try {
    await register(form.value)
    success.value = true
  } catch (e: unknown) {
    const response = (e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } })?.response
    if (response?.data?.errors?.['terms_accepted']) termsError.value = t('auth.register.termsRequired')
    error.value = response?.data?.message ?? t('auth.register.failed')
  } finally {
    loading.value = false
  }
}

// Registrácia cez Google. Zaškrtnutie súhlasu pred otvorením Google okna
// vynútiť nevieme, tak ho skontrolujeme po návrate s tokenom.
async function onGoogleCredential(credential: string) {
  error.value = null
  termsError.value = null

  if (!form.value.terms_accepted) {
    termsError.value = t('auth.register.termsRequired')
    return
  }

  loading.value = true
  try {
    await auth.socialLogin('register', 'google', { id_token: credential, terms_accepted: true })
    router.push('/dashboard')
  } catch (e: unknown) {
    error.value = (e as { response?: { data?: { message?: string } } })?.response?.data?.message ?? t('auth.register.failed')
  } finally {
    loading.value = false
  }
}
</script>
