<template>
  <div class="auth-page">
    <div class="auth-card">
      <h1>{{ t('auth.reset.title') }}</h1>
      <p>{{ t('auth.reset.lead') }}</p>

      <div v-if="error" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
        {{ error }}
        <!-- Vypršaný odkaz je najčastejší koniec tohto toku, preto z chyby
             rovno vedie cesta ďalej namiesto slepej uličky. -->
        <RouterLink v-if="expired" class="mt-1 block font-semibold underline" :to="forgotLink">
          {{ t('auth.reset.requestNew') }}
        </RouterLink>
      </div>

      <div v-if="done" class="grid gap-3">
        <div class="rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-700">
          {{ t('auth.reset.done') }}
        </div>
        <RouterLink to="/login" class="btn btn-primary">{{ t('auth.reset.loginNow') }}</RouterLink>
      </div>

      <form v-else class="grid gap-3" @submit.prevent="submit">
        <FormField v-model="email" type="email" :label="t('auth.reset.email')" required autocomplete="username" />
        <FormField
          v-model="password"
          type="password"
          :label="t('auth.reset.password')"
          :hint="t('auth.reset.passwordHint')"
          required
          autocomplete="new-password"
        />
        <FormField
          v-model="passwordConfirmation"
          type="password"
          :label="t('auth.reset.passwordConfirm')"
          required
          autocomplete="new-password"
        />
        <button type="submit" class="btn btn-primary" :disabled="loading">
          {{ loading ? t('auth.reset.submitting') : t('auth.reset.submit') }}
        </button>
      </form>

      <small><RouterLink to="/login">{{ t('auth.reset.backToLogin') }}</RouterLink></small>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRoute } from 'vue-router'
import { resetPassword } from '@/api/auth'
import { t } from '@/i18n'
import { provideFormValidation } from '@/composables/useFormValidation'
import FormField from '@/components/FormField.vue'

const route = useRoute()
const validation = provideFormValidation()

// Token je v ceste, adresa v query — obe z odkazu v e-maile. Adresa je len
// predvyplnenie poľa: bez zhody s tokenom ju backend aj tak neuzná, a keď si
// ju človek prepíše, dozvie sa to z odpovede.
const token = String(route.params.token ?? '')
const email = ref(typeof route.query.email === 'string' ? route.query.email : '')

const password = ref('')
const passwordConfirmation = ref('')
const done = ref(false)
const expired = ref(false)
const error = ref<string | null>(null)
const loading = ref(false)

const forgotLink = computed(() => ({
  path: '/zabudnute-heslo',
  query: email.value ? { email: email.value } : undefined,
}))

async function submit() {
  validation.markValidated()
  error.value = null
  expired.value = false

  if (password.value !== passwordConfirmation.value) {
    error.value = t('auth.reset.mismatch')
    return
  }

  loading.value = true
  try {
    await resetPassword({
      token,
      email: email.value,
      password: password.value,
      password_confirmation: passwordConfirmation.value,
    })
    done.value = true
  } catch (e: unknown) {
    const response = (e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } })?.response
    expired.value = Boolean(response?.data?.errors?.['token'])
    error.value = response?.data?.message ?? t('auth.reset.failed')
  } finally {
    loading.value = false
  }
}
</script>
