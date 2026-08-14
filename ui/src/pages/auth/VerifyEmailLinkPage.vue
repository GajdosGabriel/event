<template>
  <div class="flex min-h-screen items-center justify-center bg-slate-50 p-4">
    <div class="w-full max-w-sm rounded-2xl border border-slate-200 bg-white p-8 shadow-sm text-center">
      <p v-if="status === 'loading'" class="text-slate-600">{{ t('auth.verifyLink.checking') }}</p>

      <template v-else-if="status === 'success'">
        <div class="mb-4 text-4xl">✓</div>
        <h1 class="mb-2 text-xl font-semibold text-slate-900">{{ t('auth.verifyLink.successTitle') }}</h1>
        <p class="mb-6 text-sm text-slate-600">{{ message }}</p>
        <RouterLink to="/login" class="btn btn-primary">{{ t('auth.verifyLink.login') }}</RouterLink>
      </template>

      <template v-else>
        <div class="mb-4 text-4xl">✗</div>
        <h1 class="mb-2 text-xl font-semibold text-slate-900">{{ t('auth.verifyLink.failedTitle') }}</h1>
        <p class="mb-6 text-sm text-red-600">{{ message }}</p>
        <RouterLink to="/login" class="btn btn-secondary">{{ t('auth.verifyLink.back') }}</RouterLink>
      </template>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { verifyRegistrationLink } from '@/api/auth'
import { t } from '@/i18n'

const route = useRoute()
const status = ref<'loading' | 'success' | 'error'>('loading')
const message = ref('')

onMounted(async () => {
  const token = route.params.token as string
  try {
    const res = await verifyRegistrationLink(token)
    message.value = res.message ?? t('auth.verifyLink.successFallback')
    status.value = 'success'
  } catch (e: unknown) {
    message.value =
      (e as { response?: { data?: { message?: string } } })?.response?.data?.message ??
      t('auth.verifyLink.failedFallback')
    status.value = 'error'
  }
})
</script>
