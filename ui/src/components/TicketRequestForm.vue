<template>
  <div>
    <!-- Úspešná rezervácia -->
    <div v-if="success" class="rounded-lg bg-green-50 p-4 text-sm text-green-800">
      <p class="mb-2 font-semibold">{{ isPaid ? 'Lístok bol vytvorený!' : 'Miesto je rezervované!' }}</p>
      <p v-if="(success.quantity ?? 1) > 1" class="mb-1">
        Počet miest: <strong>{{ success.quantity }}</strong>
      </p>
      <p v-if="sentEmail" class="mb-3">Potvrdenie sme poslali na e-mail <strong>{{ sentEmail }}</strong>.</p>
      <p v-else class="mb-3">Potvrdenie sme poslali na e-mail tvojho účtu.</p>
      <RouterLink :to="`/tickets/${success.uuid}`" class="inline-block rounded-lg bg-green-700 px-4 py-2 font-medium text-white hover:bg-green-800">
        Zobraziť lístok a QR kód →
      </RouterLink>
    </div>

    <!-- Registrácia uzavretá (event skončil / uplynul termín) -->
    <div v-else-if="closedReason" class="rounded-lg bg-slate-100 p-4 text-sm font-medium text-slate-600">
      {{ closedReason }}
    </div>

    <!-- Vypredané -->
    <div v-else-if="isSoldOut" class="rounded-lg bg-slate-100 p-4 text-sm font-medium text-slate-600">
      Kapacita je naplnená — event je plný.
    </div>

    <form v-else class="space-y-3" @submit.prevent="submit">
      <div v-if="remainingCapacity !== null" class="text-xs text-slate-500">
        Voľných miest: <strong>{{ remainingCapacity }}</strong>
      </div>
      <div v-if="isPaid" class="text-sm font-semibold text-slate-800">
        Cena: {{ formattedPrice }}<span v-if="quantity > 1" class="text-slate-500"> × {{ quantity }} = {{ formattedTotal }}</span>
      </div>
      <div v-else class="text-sm font-semibold text-green-700">Zdarma</div>

      <!-- Prihlásený → one-click rezervácia -->
      <div v-if="oneClick" class="rounded-lg bg-blue-50 px-3 py-2 text-xs text-blue-800">
        Rezervuješ ako <strong>{{ auth.displayName }}</strong>. Potvrdenie pošleme na e-mail tvojho účtu.
      </div>

      <!-- Formulár údajov (hosť alebo „zadať iné údaje") -->
      <template v-if="!oneClick">
        <div>
          <label class="mb-1 block text-xs font-medium text-slate-600">Meno a priezvisko</label>
          <input v-model.trim="form.holder_name" type="text" required maxlength="250"
            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
        </div>
        <div>
          <label class="mb-1 block text-xs font-medium text-slate-600">E-mail</label>
          <input v-model.trim="form.holder_email" type="email" required maxlength="190"
            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
        </div>
        <div>
          <label class="mb-1 block text-xs font-medium text-slate-600">Telefón (nepovinné)</label>
          <input v-model.trim="form.holder_phone" type="tel" maxlength="30"
            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
        </div>
      </template>

      <!-- Počet miest -->
      <div>
        <label class="mb-1 block text-xs font-medium text-slate-600">Počet miest</label>
        <div class="flex items-center gap-2">
          <button type="button" :disabled="quantity <= 1"
            class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-300 text-lg leading-none text-slate-600 hover:bg-slate-50 disabled:opacity-40"
            @click="quantity > 1 && quantity--">−</button>
          <span class="w-8 text-center text-sm font-semibold">{{ quantity }}</span>
          <button type="button" :disabled="quantity >= maxQuantity"
            class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-300 text-lg leading-none text-slate-600 hover:bg-slate-50 disabled:opacity-40"
            @click="quantity < maxQuantity && quantity++">+</button>
        </div>
      </div>

      <p v-if="error" class="text-sm text-red-600">{{ error }}</p>

      <button type="submit" :disabled="loading"
        class="w-full rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60">
        {{ loading ? 'Odosielam…' : actionLabel }}
      </button>

      <!-- Prepnutie medzi one-click a vlastnými údajmi (len pre prihlásených) -->
      <button v-if="auth.isAuthenticated" type="button"
        class="w-full text-center text-xs text-slate-500 hover:text-blue-600"
        @click="useOwnDetails = !useOwnDetails">
        {{ useOwnDetails ? 'Použiť údaje z môjho účtu' : 'Zadať iné údaje' }}
      </button>
    </form>
  </div>
</template>

<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { requestTicket } from '@/api/tickets'
import { useAuthStore } from '@/stores/auth'
import type { TicketItem } from '@/types'

const props = defineProps<{
  eventId: number
  remainingCapacity: number | null
  priceAmount: number | null
  priceCurrency: string | null
  registrationDeadlineAt?: string | null
  endAt?: string | null
}>()

const auth = useAuthStore()

const form = reactive({
  holder_name: '',
  holder_email: '',
  holder_phone: '',
})

const loading = ref(false)
const error = ref<string | null>(null)
const success = ref<TicketItem | null>(null)
const sentEmail = ref('')
const useOwnDetails = ref(false)
const quantity = ref(1)

const isPaid = computed(() => (props.priceAmount ?? 0) > 0)
const isSoldOut = computed(() => props.remainingCapacity !== null && props.remainingCapacity <= 0)

// Prihlásený a nechce zadávať iné údaje → rezervácia na jedno kliknutie.
const oneClick = computed(() => auth.isAuthenticated && !useOwnDetails.value)

const closedReason = computed(() => {
  const now = Date.now()
  if (props.endAt && new Date(props.endAt).getTime() < now) {
    return 'Podujatie už prebehlo — registrácia nie je možná.'
  }
  if (props.registrationDeadlineAt && new Date(props.registrationDeadlineAt).getTime() < now) {
    return 'Termín registrácie už uplynul.'
  }
  return null
})

const maxQuantity = computed(() => {
  const cap = props.remainingCapacity !== null ? props.remainingCapacity : 10
  return Math.max(1, Math.min(cap, 10))
})

const actionLabel = computed(() => {
  if (isPaid.value) return quantity.value > 1 ? 'Získať lístky' : 'Získať lístok'
  return quantity.value > 1 ? 'Rezervovať miesta' : 'Rezervovať miesto'
})

const formattedPrice = computed(() => {
  if (!props.priceAmount) return ''
  return new Intl.NumberFormat('sk-SK', { style: 'currency', currency: props.priceCurrency ?? 'EUR' })
    .format(props.priceAmount / 100)
})

const formattedTotal = computed(() => {
  if (!props.priceAmount) return ''
  return new Intl.NumberFormat('sk-SK', { style: 'currency', currency: props.priceCurrency ?? 'EUR' })
    .format((props.priceAmount * quantity.value) / 100)
})

async function submit() {
  loading.value = true
  error.value = null
  try {
    const payload = oneClick.value
      ? { quantity: quantity.value }
      : {
          holder_name: form.holder_name,
          holder_email: form.holder_email,
          holder_phone: form.holder_phone || undefined,
          quantity: quantity.value,
        }
    sentEmail.value = oneClick.value ? '' : form.holder_email
    success.value = await requestTicket(props.eventId, payload)
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    error.value = err.response?.data?.message ?? 'Registráciu sa nepodarilo dokončiť.'
  } finally {
    loading.value = false
  }
}
</script>
