<template>
  <div class="auth-page">
    <div class="auth-card">
      <h1>{{ t('auth.verify.title') }}</h1>
      <p>{{ t('auth.verify.lead') }}</p>

      <div v-if="message" class="rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-700">{{ message }}</div>
      <div v-if="error" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ error }}</div>

      <form class="grid gap-3" @submit.prevent="resend">
        <FormField v-model="email" type="email" :label="t('auth.verify.email')" required />
        <button type="submit" class="btn btn-secondary" :disabled="loading">
          {{ loading ? t('auth.verify.resending') : t('auth.verify.resend') }}
        </button>
      </form>

      <small><RouterLink to="/login">{{ t('auth.verify.backToLogin') }}</RouterLink></small>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { resendVerification } from '@/api/auth'
import { t } from '@/i18n'
import { provideFormValidation } from '@/composables/useFormValidation'
import FormField from '@/components/FormField.vue'

const validation = provideFormValidation()

const email = ref('')
const message = ref<string | null>(null)
const error = ref<string | null>(null)
const loading = ref(false)

async function resend() {
  validation.markValidated()
  error.value = null
  message.value = null
  loading.value = true
  try {
    await resendVerification(email.value)
    message.value = t('auth.verify.sent')
  } catch {
    error.value = t('auth.verify.failed')
  } finally {
    loading.value = false
  }
}
</script>
