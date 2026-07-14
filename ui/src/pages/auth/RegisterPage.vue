<template>
  <div class="auth-page">
    <div class="auth-card">
      <h1>Registrácia</h1>
      <p>Vytvorte si účet.</p>

      <div v-if="error" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ error }}</div>
      <div v-if="success" class="rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-700">
        Na uvedený email vám bol zaslaný overovací odkaz.
      </div>

      <form v-if="!success" class="grid gap-3" @submit.prevent="submit">
        <label class="grid gap-1.5 text-sm font-semibold text-slate-900">
          Meno
          <input v-model="form.display_name" type="text" class="form-input" required />
        </label>
        <label class="grid gap-1.5 text-sm font-semibold text-slate-900">
          Email
          <input v-model="form.email" type="email" class="form-input" required />
        </label>
        <label class="grid gap-1.5 text-sm font-semibold text-slate-900">
          Heslo
          <input v-model="form.password" type="password" class="form-input" required />
        </label>
        <label class="grid gap-1.5 text-sm font-semibold text-slate-900">
          Potvrdiť heslo
          <input v-model="form.password_confirmation" type="password" class="form-input" required />
        </label>
        <button type="submit" class="btn btn-primary" :disabled="loading">
          {{ loading ? 'Registrujem…' : 'Registrovať sa' }}
        </button>
      </form>

      <small>Máte účet? <RouterLink to="/login">Prihlásiť sa</RouterLink></small>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { register } from '@/api/auth'

const form = ref({ display_name: '', email: '', password: '', password_confirmation: '' })
const error = ref<string | null>(null)
const success = ref(false)
const loading = ref(false)

async function submit() {
  error.value = null
  loading.value = true
  try {
    await register(form.value)
    success.value = true
  } catch (e: unknown) {
    error.value = (e as { response?: { data?: { message?: string } } })?.response?.data?.message ?? 'Registrácia zlyhala.'
  } finally {
    loading.value = false
  }
}
</script>
