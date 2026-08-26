<template>
  <div>
    <button
      type="button"
      :class="variant === 'primary'
        ? 'flex w-full items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700'
        : 'flex w-full items-center justify-center gap-2 rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50'"
      @click="openForm"
    >
      <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
      </svg>
      {{ t('public.remind.button') }}
    </button>

    <Teleport to="body">
      <Transition
        enter-active-class="transition duration-150" enter-from-class="opacity-0" enter-to-class="opacity-100"
        leave-active-class="transition duration-100" leave-from-class="opacity-100" leave-to-class="opacity-0"
      >
        <div v-if="open" class="fixed inset-0 z-9999 flex items-center justify-center bg-black/50 p-4" @click.self="close">
          <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl">
            <div class="mb-4 flex items-start justify-between gap-2">
              <div>
                <h2 class="text-lg font-semibold text-slate-900">{{ t('public.remind.title') }}</h2>
                <p v-if="eventName" class="mt-0.5 text-sm text-slate-500">{{ eventName }}</p>
              </div>
              <button
                type="button"
                class="rounded-full p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
                :aria-label="t('public.remind.close')"
                @click="close"
              >
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>

            <!-- Hotovo -->
            <div v-if="done" class="rounded-lg bg-green-50 p-4 text-sm text-green-800">
              <p class="mb-1 font-semibold">{{ t('public.remind.doneTitle') }}</p>
              <p>{{ t('public.remind.doneLead') }}</p>
              <button type="button" class="mt-3 text-sm font-medium text-green-700 hover:underline" @click="close">
                {{ t('public.remind.close') }}
              </button>
            </div>

            <form v-else class="space-y-4" @submit.prevent="submit">
              <!-- Hlavný sľub je zmena a zrušenie, nie „novinky". To je dôvod,
                   ktorý ľudia prijmú bez váhania — pripomienka je až bonus. -->
              <p class="text-sm text-slate-600">{{ t('public.remind.lead') }}</p>

              <!-- Prihlásený → adresu netreba pýtať, je v účte. -->
              <div v-if="useAccount" class="rounded-lg bg-blue-50 px-3 py-2 text-sm text-blue-800">
                {{ t('public.remind.account') }} <strong>{{ auth.email }}</strong>.
              </div>

              <FormField
                v-else
                v-model="email"
                type="email"
                :label="t('public.remind.email')"
                required
                trim
                autocomplete="email"
                maxlength="190"
                :placeholder="t('public.remind.emailPlaceholder')"
              />

              <!-- Pasca na roboty: mimo obrazovky, bez tabulátora a skrytá pre
                   čítačky, takže človek do nej nemá ako napísať. -->
              <div class="absolute left-[-9999px]" aria-hidden="true">
                <label>
                  Website
                  <input v-model="website" type="text" tabindex="-1" autocomplete="off" />
                </label>
              </div>

              <p class="text-xs text-slate-500">{{ t('public.remind.privacy') }}</p>

              <!-- Adresa účtu nemusí byť tá, na ktorej to človek chce mať. -->
              <button
                v-if="hasAccountEmail"
                type="button"
                class="text-xs font-medium text-slate-500 hover:text-blue-600"
                @click="useOther = !useOther"
              >
                {{ useOther ? t('public.remind.useAccount') : t('public.remind.useOther') }}
              </button>

              <p v-if="error" class="text-sm text-red-600">{{ error }}</p>

              <button
                type="submit"
                :disabled="loading"
                class="w-full rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60"
              >
                {{ loading ? t('public.remind.submitting') : t('public.remind.submit') }}
              </button>
            </form>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { subscribeToEvent, subscriptionTicket } from '@/api/subscriptions'
import { t, currentLocale } from '@/i18n'
import { useWindowKeydown } from '@/composables/useWindowKeydown'
import { provideFormValidation } from '@/composables/useFormValidation'
import { useAuthStore } from '@/stores/auth'
import FormField from '@/components/FormField.vue'

const props = withDefaults(defineProps<{
  eventId: number
  /** Názov podujatia v hlavičke modálu (nepovinný). */
  eventName?: string
  /** `primary` v sidebare a v mobilnej lište, `ghost` vedľa iného hlavného tlačidla. */
  variant?: 'primary' | 'ghost'
}>(), {
  eventName: '',
  variant: 'primary',
})

const auth = useAuthStore()
const validation = provideFormValidation()

const open = ref(false)
const loading = ref(false)
const done = ref(false)
const error = ref<string | null>(null)
const email = ref('')
const website = ref('')
/** Prihlásený si vyžiadal inú adresu než tú z účtu. */
const useOther = ref(false)
const ticket = ref('')

// Prihlásený má adresu v účte — pýtať sa naň znova je zbytočné trenie.
// Kým sa identita načíta, adresa je prázdna a formulár sa správa ako pre hosťa.
const hasAccountEmail = computed(() => auth.isAuthenticated && auth.email !== '')
const useAccount = computed(() => hasAccountEmail.value && !useOther.value)

/**
 * Známka sa pýta až pri otvorení formulára, nie pri načítaní stránky. Backend
 * jej predpisuje minimálny vek (obrana proti botovi, ktorý POST nájde a búši
 * doň), a keby vznikla pri načítaní detailu, počítal by sa čas čítania stránky
 * namiesto času vypĺňania — čo je presne naopak, než sa kontroluje.
 */
async function openForm() {
  open.value = true

  // Detail podujatia sa dá otvoriť aj mimo PublicLayout (a rovno na odkaz),
  // takže identita nemusí byť načítaná — bez nej by sme adresu pýtali zbytočne.
  if (auth.isAuthenticated && auth.identity === null) {
    auth.fetchIdentity()
  }

  if (ticket.value !== '') return

  try {
    ticket.value = await subscriptionTicket(props.eventId)
  } catch {
    // Ticha chyba: odoslanie ju zopakuje s hláškou, ktorú sa oplatí ukázať.
    ticket.value = ''
  }
}

function close() {
  open.value = false

  if (done.value) {
    done.value = false
    email.value = ''
    useOther.value = false
  }

  error.value = null
  validation.reset()
}

useWindowKeydown((event) => {
  if (event.key === 'Escape' && open.value) {
    close()
  }
})

async function submit() {
  validation.markValidated()
  loading.value = true
  error.value = null

  try {
    if (ticket.value === '') {
      ticket.value = await subscriptionTicket(props.eventId)
    }

    await subscribeToEvent(props.eventId, {
      email: useAccount.value ? auth.email : email.value,
      ticket: ticket.value,
      locale: currentLocale(),
      website: website.value,
    })

    done.value = true
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    error.value = err.response?.data?.message ?? t('public.remind.failed')
    // Známka je jednorazová v tom zmysle, že po odmietnutí (typicky „príliš
    // rýchlo") má ďalší pokus začať s čerstvou.
    ticket.value = ''
  } finally {
    loading.value = false
  }
}
</script>
