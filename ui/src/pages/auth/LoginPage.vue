<template>
  <div class="auth-page">
    <div class="auth-card">
      <h1>Prihlásenie</h1>
      <p>Vitajte späť.</p>

      <div v-if="error" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ error }}</div>

      <form class="grid gap-3" @submit.prevent="submit">
        <FormField v-model="form.email" type="email" label="Email" required autocomplete="email" />
        <FormField v-model="form.password" type="password" label="Heslo" required autocomplete="current-password" />
        <button type="submit" class="btn btn-primary" :disabled="loading">
          {{ loading ? 'Prihlasujem…' : 'Prihlásiť sa' }}
        </button>
      </form>

      <div class="grid gap-2 mt-1">
        <button class="social-button" @click="socialLogin('google')">
          <span>Prihlásiť cez Google</span>
        </button>
        <button class="social-button" @click="socialLogin('facebook')">
          <span>Prihlásiť cez Facebook</span>
        </button>
      </div>

      <small>Nemáte účet? <RouterLink to="/register">Registrovať sa</RouterLink></small>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { startSocialLogin } from '@/api/auth'
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
    error.value = (e as { response?: { data?: { message?: string } } })?.response?.data?.message ?? 'Prihlásenie zlyhalo.'
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
