<template>
  <div class="mx-auto w-full max-w-md px-4 py-8">
    <div v-if="loading" class="flex items-center justify-center gap-2 py-16 text-slate-500">
      <span class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-slate-300 border-t-blue-600" />
      {{ t('unsubscribe.loading') }}
    </div>

    <div v-else-if="notFound" class="rounded-2xl border border-red-200 bg-red-50 p-6 text-center">
      <p class="mb-2 text-lg font-semibold text-red-700">{{ t('unsubscribe.invalidTitle') }}</p>
      <p class="mb-4 text-sm text-red-600">{{ t('unsubscribe.invalidLead') }}</p>
      <RouterLink to="/" class="text-sm text-blue-600 hover:underline">{{ t('unsubscribe.home') }}</RouterLink>
    </div>

    <div v-else class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
      <div class="bg-slate-800 p-6 text-white">
        <p class="text-xs font-semibold uppercase tracking-wider opacity-80">{{ t('unsubscribe.kicker') }}</p>
        <h1 class="mt-1 text-2xl font-bold">{{ info?.event || t('unsubscribe.eventFallback') }}</h1>
      </div>

      <div class="space-y-5 p-6">
        <!-- Odhlásené. Rovnaká obrazovka aj pri druhom otvorení odkazu —
             odhlásenie je idempotentné, takže sa z neho nemá stať chyba. -->
        <template v-if="done">
          <div class="rounded-lg bg-green-50 p-4 text-sm text-green-800">
            <p class="mb-1 font-semibold">{{ t('unsubscribe.doneTitle') }}</p>
            <p>{{ t('unsubscribe.doneLead') }}</p>
          </div>
          <RouterLink
            :to="PUBLIC_EVENTS"
            class="block rounded-lg border border-slate-300 px-4 py-2 text-center text-sm font-semibold text-slate-700 no-underline hover:bg-slate-50"
          >{{ t('unsubscribe.browse') }}</RouterLink>
        </template>

        <template v-else>
          <p class="text-sm text-slate-600">{{ t('unsubscribe.lead') }}</p>

          <p v-if="error" class="text-sm text-red-600">{{ error }}</p>

          <button
            type="button"
            :disabled="working"
            class="w-full rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 disabled:opacity-60"
            @click="confirm"
          >
            {{ working ? t('unsubscribe.working') : t('unsubscribe.confirm') }}
          </button>

          <!-- Cesta späť pre toho, kto sem klikol omylom. -->
          <RouterLink
            :to="PUBLIC_EVENTS"
            class="block text-center text-sm text-slate-500 no-underline hover:underline"
          >{{ t('unsubscribe.keep') }}</RouterLink>
        </template>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useHead } from '@vueuse/head'
import { showSubscription, unsubscribe, type SubscriptionInfo } from '@/api/subscriptions'
import { PUBLIC_EVENTS } from '@/utils/publicUrl'
import { t } from '@/i18n'

const route = useRoute()

const loading = ref(true)
const working = ref(false)
const notFound = ref(false)
const done = ref(false)
const error = ref<string | null>(null)
const info = ref<SubscriptionInfo | null>(null)

// Stránka na jedno použitie z e-mailu — vo vyhľadávaní nemá čo robiť.
useHead({ meta: [{ name: 'robots', content: 'noindex, nofollow' }] })

const token = String(route.params.token ?? '')

onMounted(async () => {
  try {
    info.value = await showSubscription(token)
    // Odkaz otvorený druhýkrát: odber už neexistuje, takže rovno hotovo.
    done.value = !info.value.active
  } catch {
    notFound.value = true
  } finally {
    loading.value = false
  }
})

async function confirm() {
  working.value = true
  error.value = null

  try {
    info.value = await unsubscribe(token)
    done.value = true
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    error.value = err.response?.data?.message ?? t('unsubscribe.failed')
  } finally {
    working.value = false
  }
}
</script>
