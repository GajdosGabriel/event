<template>
  <div class="auth-page">
    <div class="auth-card">
      <h1>Overenie emailu</h1>
      <p>Skontrolujte váš email a kliknite na overovací odkaz.</p>

      <div v-if="message" class="rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-700">{{ message }}</div>
      <div v-if="error" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ error }}</div>

      <form class="grid gap-3" @submit.prevent="resend">
        <label class="grid gap-1.5 text-sm font-semibold text-slate-900">
          Email
          <input v-model="email" type="email" class="form-input" required />
        </label>
        <button type="submit" class="btn btn-secondary" :disabled="loading">
          {{ loading ? 'Posielam…' : 'Znova odoslať overovací email' }}
        </button>
      </form>

      <small><RouterLink to="/login">Späť na prihlásenie</RouterLink></small>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { resendVerification } from '@/api/auth'

const email = ref('')
const message = ref<string | null>(null)
const error = ref<string | null>(null)
const loading = ref(false)

async function resend() {
  error.value = null
  message.value = null
  loading.value = true
  try {
    await resendVerification(email.value)
    message.value = 'Overovací email bol odoslaný.'
  } catch {
    error.value = 'Odoslanie zlyhalo.'
  } finally {
    loading.value = false
  }
}
</script>
