<template>
  <div>
    <div v-if="success" class="rounded-lg bg-green-50 p-4 text-sm text-green-800">
      <p class="mb-2 font-semibold">Lístok bol vytvorený!</p>
      <p class="mb-3">Poslali sme potvrdenie na e-mail <strong>{{ form.holder_email }}</strong>.</p>
      <RouterLink :to="`/tickets/${success.uuid}`" class="inline-block rounded-lg bg-green-700 px-4 py-2 font-medium text-white hover:bg-green-800">
        Zobraziť lístok a QR kód →
      </RouterLink>
    </div>

    <div v-else-if="isSoldOut" class="rounded-lg bg-slate-100 p-4 text-sm font-medium text-slate-600">
      Kapacita je naplnená — event je plný.
    </div>

    <form v-else class="space-y-3" @submit.prevent="submit">
      <div v-if="remainingCapacity !== null" class="text-xs text-slate-500">
        Voľných miest: <strong>{{ remainingCapacity }}</strong>
      </div>
      <div v-if="priceAmount" class="text-sm font-semibold text-slate-800">
        Cena: {{ formattedPrice }}
      </div>
      <div v-else class="text-sm font-semibold text-green-700">Zdarma</div>

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

      <p v-if="error" class="text-sm text-red-600">{{ error }}</p>

      <button type="submit" :disabled="loading"
        class="w-full rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60">
        {{ loading ? 'Odosielam…' : 'Získať lístok' }}
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
}>()

const auth = useAuthStore()

const form = reactive({
  holder_name: auth.isAuthenticated ? auth.displayName : '',
  holder_email: '',
  holder_phone: '',
})

const loading = ref(false)
const error = ref<string | null>(null)
const success = ref<TicketItem | null>(null)

const isSoldOut = computed(() => props.remainingCapacity !== null && props.remainingCapacity <= 0)

const formattedPrice = computed(() => {
  if (!props.priceAmount) return ''
  return new Intl.NumberFormat('sk-SK', { style: 'currency', currency: props.priceCurrency ?? 'EUR' })
    .format(props.priceAmount / 100)
})

async function submit() {
  loading.value = true
  error.value = null
  try {
    success.value = await requestTicket(props.eventId, {
      holder_name: form.holder_name,
      holder_email: form.holder_email,
      holder_phone: form.holder_phone || undefined,
    })
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    error.value = err.response?.data?.message ?? 'Registráciu sa nepodarilo dokončiť.'
  } finally {
    loading.value = false
  }
}
</script>
