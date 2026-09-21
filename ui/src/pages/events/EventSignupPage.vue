<template>
  <div class="mx-auto my-10 w-full max-w-lg px-4">
    <div class="show-card">
      <h1 class="text-xl font-bold text-slate-900">{{ t('eventSignup.title') }}</h1>

      <p v-if="busy" class="mt-3 text-slate-600">{{ t('eventSignup.loading') }}</p>

      <div v-else-if="error" class="mt-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ error }}</div>

      <EventSignupOutcome v-else-if="result" class="mt-4" :result="result" />

      <!-- Neprihlásený: prihlásenie alebo registrácia, oboje s návratom sem. -->
      <template v-else-if="!auth.isAuthenticated">
        <p class="mt-3 text-slate-700">{{ t('eventSignup.guestLead', { event: eventLabel }) }}</p>
        <div class="mt-4 flex flex-wrap gap-2">
          <RouterLink :to="{ name: 'login', query: { event: eventId } }" class="btn btn-primary">{{ t('eventSignup.login') }}</RouterLink>
          <RouterLink :to="{ name: 'register', query: { event: eventId } }" class="btn btn-secondary">{{ t('eventSignup.register') }}</RouterLink>
        </div>
      </template>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { showPublicEvent } from '@/api/events'
import { signupForEvent, type EventSignupResult } from '@/api/tickets'
import EventSignupOutcome from '@/components/EventSignupOutcome.vue'
import { useAuthStore } from '@/stores/auth'
import { t } from '@/i18n'

/**
 * „Prihlásiť sa" na akciu (hlascirkvi.sk → portál). Prihlásenému rovno
 * rezervuje miesto (pri platenej akcii zaznamená záujem), neprihlásenému
 * ponúkne prihlásenie alebo registráciu — tie ho sem po úspechu vrátia.
 */
const route = useRoute()
const auth = useAuthStore()

const eventId = computed(() => String(route.params.id ?? ''))
const eventName = ref<string | null>(null)
const eventLabel = computed(() => (eventName.value ? `„${eventName.value}"` : t('eventSignup.eventFallback')))

const busy = ref(true)
const error = ref<string | null>(null)
const result = ref<EventSignupResult | null>(null)

onMounted(async () => {
  document.title = t('eventSignup.title')

  if (auth.isAuthenticated && !auth.identity) {
    await auth.fetchIdentity()
  }

  try {
    if (auth.isAuthenticated) {
      try {
        result.value = await signupForEvent(Number(eventId.value))
        return
      } catch (e: unknown) {
        // Token v prehliadači už neplatí → ponúkneme prihlásenie ako hosťovi.
        if ((e as { response?: { status?: number } })?.response?.status !== 401) throw e
        auth.clear()
      }
    }
    eventName.value = (await showPublicEvent(eventId.value)).name ?? null
  } catch (e: unknown) {
    error.value = (e as { response?: { data?: { message?: string } } })?.response?.data?.message ?? t('eventSignup.failed')
  } finally {
    busy.value = false
  }
})
</script>
