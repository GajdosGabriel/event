<template>
  <div class="auth-page">
    <div class="auth-card">
      <h1>{{ t('auth.login.title') }}</h1>
      <p>{{ t('auth.login.lead') }}</p>

      <div v-if="error" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ error }}</div>

      <form class="grid gap-3" @submit.prevent="submit">
        <FormField v-model="form.email" type="email" :label="t('auth.login.email')" required autocomplete="email" />
        <FormField v-model="form.password" type="password" :label="t('auth.login.password')" required autocomplete="current-password" />
        <button type="submit" class="btn btn-primary" :disabled="loading">
          {{ loading ? t('auth.login.submitting') : t('auth.login.submit') }}
        </button>
      </form>

      <div class="social-auth grid gap-2">
        <div class="social-divider"><span>{{ t('auth.social.or') }}</span></div>
        <GoogleSignInButton context="signin" @credential="onGoogleCredential" />

        <div v-if="needsTerms" class="grid gap-2 rounded-lg border border-amber-200 bg-amber-50 p-3">
          <p class="text-sm text-amber-800">{{ t('auth.social.termsForNew') }}</p>
          <TermsConsentField v-model="socialTerms" :error="socialTermsError" />
          <button class="btn btn-primary" :disabled="socialLoading" @click="continueWithTerms">
            {{ socialLoading ? t('auth.login.submitting') : t('auth.social.continue') }}
          </button>
        </div>
      </div>

      <small>{{ t('auth.login.noAccount') }} <RouterLink to="/register">{{ t('auth.login.registerLink') }}</RouterLink></small>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { t } from '@/i18n'
import { provideFormValidation } from '@/composables/useFormValidation'
import FormField from '@/components/FormField.vue'
import GoogleSignInButton from '@/components/auth/GoogleSignInButton.vue'
import TermsConsentField from '@/components/TermsConsentField.vue'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

const validation = provideFormValidation()

const form = ref({ email: '', password: '' })
const error = ref<string | null>(null)
const loading = ref(false)

function redirectTarget() {
  return typeof route.query.redirect === 'string' ? route.query.redirect : '/dashboard'
}

async function submit() {
  validation.markValidated()
  error.value = null
  loading.value = true
  try {
    await auth.login(form.value.email, form.value.password)
    router.push(redirectTarget())
  } catch (e: unknown) {
    error.value = (e as { response?: { data?: { message?: string } } })?.response?.data?.message ?? t('auth.login.failed')
  } finally {
    loading.value = false
  }
}

// Prihlásenie cez Google. Ak Google účet ešte nemá profil v portáli, backend
// vzniknutie účtu odmietne s `code: 'terms_required'` — vtedy doplníme súhlas
// a ten istý token pošleme znova (JWT z GSI platí ~hodinu).
const needsTerms = ref(false)
const socialTerms = ref(false)
const socialTermsError = ref<string | null>(null)
const socialLoading = ref(false)
let pendingCredential = ''

async function onGoogleCredential(credential: string) {
  pendingCredential = credential
  await runGoogleLogin(false)
}

async function continueWithTerms() {
  if (!socialTerms.value) {
    socialTermsError.value = t('auth.register.termsRequired')
    return
  }
  await runGoogleLogin(true)
}

async function runGoogleLogin(termsAccepted: boolean) {
  error.value = null
  socialTermsError.value = null
  socialLoading.value = true
  try {
    await auth.socialLogin('login', 'google', {
      id_token: pendingCredential,
      terms_accepted: termsAccepted || undefined,
    })
    router.push(redirectTarget())
  } catch (e: unknown) {
    const res = (e as { response?: { data?: { message?: string; code?: string } } })?.response
    if (res?.data?.code === 'terms_required') {
      needsTerms.value = true
    } else {
      error.value = res?.data?.message ?? t('auth.login.failed')
    }
  } finally {
    socialLoading.value = false
  }
}
</script>
