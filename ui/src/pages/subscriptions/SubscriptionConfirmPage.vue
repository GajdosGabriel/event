<template>
  <div class="mx-auto w-full max-w-md px-4 py-8">
    <div v-if="loading" class="flex items-center justify-center gap-2 py-16 text-slate-500">
      <span class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-slate-300 border-t-blue-600" />
      {{ t('subscriptionConfirm.loading') }}
    </div>

    <div v-else-if="notFound" class="rounded-2xl border border-red-200 bg-red-50 p-6 text-center">
      <p class="mb-2 text-lg font-semibold text-red-700">{{ t('subscriptionConfirm.invalidTitle') }}</p>
      <p class="mb-4 text-sm text-red-600">{{ t('subscriptionConfirm.invalidLead') }}</p>
      <RouterLink to="/" class="text-sm text-blue-600 hover:underline">{{ t('subscriptionConfirm.home') }}</RouterLink>
    </div>

    <div v-else class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
      <div class="bg-slate-800 p-6 text-white">
        <p class="text-xs font-semibold uppercase tracking-wider opacity-80">{{ t('subscriptionConfirm.kicker') }}</p>
        <h1 class="mt-1 text-2xl font-bold">{{ info?.event || t('subscriptionConfirm.fallback') }}</h1>
      </div>

      <div class="space-y-5 p-6">
        <!-- Potvrdené. Rovnaká obrazovka aj pri druhom kliku — potvrdenie je
             idempotentné. Odhlásený odber sa neoživuje (active = false). -->
        <div v-if="info?.confirmed" role="status" class="rounded-lg bg-green-50 p-4 text-sm text-green-800">
          <p class="mb-1 font-semibold">{{ t('subscriptionConfirm.doneTitle') }}</p>
          <p>{{ t('subscriptionConfirm.doneLead') }}</p>
        </div>
        <div v-else class="rounded-lg bg-amber-50 p-4 text-sm text-amber-800">
          <p class="mb-1 font-semibold">{{ t('subscriptionConfirm.inactiveTitle') }}</p>
          <p>{{ t('subscriptionConfirm.inactiveLead') }}</p>
        </div>
        <RouterLink
          :to="PUBLIC_EVENTS"
          class="block rounded-lg border border-slate-300 px-4 py-2 text-center text-sm font-semibold text-slate-700 no-underline hover:bg-slate-50"
        >{{ t('subscriptionConfirm.browse') }}</RouterLink>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useHead } from '@unhead/vue'
import { confirmSubscription, type SubscriptionConfirmation } from '@/api/subscriptions'
import { PUBLIC_EVENTS } from '@/utils/publicUrl'
import { t } from '@/i18n'

/**
 * Klik na „Potvrdiť sledovanie" v e-maile (dvojité potvrdenie,
 * CanalSubscriptionController). Potvrdzuje sa hneď po otvorení — kliknutie
 * v e-maile JE ten súhlas, druhé tlačidlo by bolo len trenie. Samotné
 * prednačítanie odkazu poštovým klientom nič nepotvrdí: na to treba spustiť
 * JavaScript stránky, ktorý pošle POST.
 */
const route = useRoute()

const loading = ref(true)
const notFound = ref(false)
const info = ref<SubscriptionConfirmation | null>(null)

// Stránka na jedno použitie z e-mailu — vo vyhľadávaní nemá čo robiť.
useHead({ meta: [{ name: 'robots', content: 'noindex, nofollow' }] })

onMounted(async () => {
  try {
    info.value = await confirmSubscription(String(route.params.token ?? ''))
  } catch {
    notFound.value = true
  } finally {
    loading.value = false
  }
})
</script>
