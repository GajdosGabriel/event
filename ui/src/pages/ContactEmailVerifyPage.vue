<template>
  <div class="flex min-h-[60vh] items-center justify-center p-4">
    <div class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-8 text-center shadow-sm">
      <p v-if="status === 'loading'" class="text-slate-600">Potvrdzujem adresu…</p>

      <template v-else-if="status === 'success'">
        <div class="mb-4 text-4xl">✓</div>
        <h1 class="mb-2 text-xl font-semibold text-slate-900">Adresa je potvrdená</h1>
        <p class="mb-2 text-sm text-slate-600">{{ message }}</p>
        <p v-if="subject" class="mb-6 text-sm text-slate-500">
          Kontakt: <strong class="text-slate-700">{{ subject }}</strong>
        </p>
        <RouterLink to="/" class="btn btn-primary">Späť na portál</RouterLink>
      </template>

      <template v-else>
        <div class="mb-4 text-4xl">✗</div>
        <h1 class="mb-2 text-xl font-semibold text-slate-900">Potvrdenie zlyhalo</h1>
        <p class="mb-6 text-sm text-red-600">{{ message }}</p>
        <RouterLink to="/" class="btn btn-secondary">Späť na portál</RouterLink>
      </template>
    </div>
  </div>
</template>

<script setup lang="ts">
/**
 * Cieľ odkazu z overovacieho e-mailu. Zámerne verejná stránka — adresu
 * potvrdzuje jej majiteľ, ktorý v portáli účet mať nemusí.
 */
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { verifyContactEmail } from '@/api/contactEmail'

const route = useRoute()
const status = ref<'loading' | 'success' | 'error'>('loading')
const message = ref('')
const subject = ref<string | null>(null)

onMounted(async () => {
  try {
    const res = await verifyContactEmail(route.params.token as string)
    message.value = res.message || 'E-mailová adresa je potvrdená. Ďakujeme!'
    subject.value = [res.name, res.email].filter(Boolean).join(' · ') || null
    status.value = 'success'
  } catch (e: unknown) {
    message.value =
      (e as { response?: { data?: { message?: string } } })?.response?.data?.message
      ?? 'Odkaz je neplatný alebo mu vypršala platnosť.'
    status.value = 'error'
  }
})
</script>
