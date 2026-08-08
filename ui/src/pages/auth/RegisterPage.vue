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
        <FormField v-model="form.display_name" label="Meno" required />
        <FormField v-model="form.email" type="email" label="Email" required />
        <FormField v-model="form.password" type="password" label="Heslo" required autocomplete="new-password" />
        <FormField v-model="form.password_confirmation" type="password" label="Potvrdiť heslo" required autocomplete="new-password" />
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
import { useRoute } from 'vue-router'
import { register } from '@/api/auth'
import { provideFormValidation } from '@/composables/useFormValidation'
import FormField from '@/components/FormField.vue'

const route = useRoute()

const validation = provideFormValidation()

// Adresu vie predvyplniť ten, kto sem posiela (napr. pozvánka do tímu kanála) —
// tá sa musí zhodovať s adresou pozvánky, inak ju účet neprijme.
const prefillEmail = typeof route.query.email === 'string' ? route.query.email : ''

const form = ref({ display_name: '', email: prefillEmail, password: '', password_confirmation: '' })
const error = ref<string | null>(null)
const success = ref(false)
const loading = ref(false)

async function submit() {
  validation.markValidated()
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
