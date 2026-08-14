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

      <div class="grid gap-2 mt-1">
        <button class="social-button" @click="socialLogin('google')">
          <span>{{ t('auth.login.google') }}</span>
        </button>
        <button class="social-button" @click="socialLogin('facebook')">
          <span>{{ t('auth.login.facebook') }}</span>
        </button>
      </div>

      <small>{{ t('auth.login.noAccount') }} <RouterLink to="/register">{{ t('auth.login.registerLink') }}</RouterLink></small>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { startSocialLogin } from '@/api/auth'
import { t } from '@/i18n'
import { provideFormValidation } from '@/composables/useFormValidation'
import FormField from '@/components/FormField.vue'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

const validation = provideFormValidation()

const form = ref({ email: '', password: '' })
const error = ref<string | null>(null)
const loading = ref(false)

async function submit() {
  validation.markValidated()
  error.value = null
  loading.value = true
  try {
    await auth.login(form.value.email, form.value.password)
    const redirect = typeof route.query.redirect === 'string' ? route.query.redirect : '/dashboard'
    router.push(redirect)
  } catch (e: unknown) {
    error.value = (e as { response?: { data?: { message?: string } } })?.response?.data?.message ?? t('auth.login.failed')
  } finally {
    loading.value = false
  }
}

function socialLogin(provider: 'google' | 'facebook') {
  startSocialLogin(provider)
}
</script>

<style scoped>
@reference "tailwindcss";

.social-button {
  @apply inline-flex h-10 items-center justify-center gap-3 rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50;
}
</style>
