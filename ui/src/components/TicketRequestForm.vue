<template>
  <div>
    <!-- Úspešná objednávka -->
    <div v-if="success" class="rounded-lg bg-green-50 p-4 text-sm text-green-800">
      <p class="mb-2 font-semibold">{{ success.priceAmount ? 'Lístky boli vytvorené!' : 'Miesta sú rezervované!' }}</p>
      <p class="mb-1">Počet vstupeniek: <strong>{{ success.admissionsTotal }}</strong></p>
      <p v-if="sentEmail" class="mb-3">Potvrdenie sme poslali na e-mail <strong>{{ sentEmail }}</strong>.</p>
      <p v-else class="mb-3">Potvrdenie sme poslali na e-mail tvojho účtu.</p>
      <RouterLink :to="`/tickets/${success.uuid}`" class="inline-block rounded-lg bg-green-700 px-4 py-2 font-medium text-white hover:bg-green-800">
        Zobraziť lístky a QR kódy →
      </RouterLink>
    </div>

    <!-- Registrácia uzavretá -->
    <div v-else-if="closedReason" class="rounded-lg bg-slate-100 p-4 text-sm font-medium text-slate-600">
      {{ closedReason }}
    </div>

    <!-- Vypredané -->
    <div v-else-if="soldOutBlocked" class="rounded-lg bg-slate-100 p-4 text-sm font-medium text-slate-600">
      Kapacita je naplnená — event je plný.
    </div>

    <div v-else-if="!types.length" class="rounded-lg bg-slate-100 p-4 text-sm font-medium text-slate-600">
      Pre toto podujatie zatiaľ nie sú v predaji žiadne lístky.
    </div>

    <form v-else class="space-y-4" @submit.prevent="submit">
      <div v-if="remainingCapacity !== null" class="text-xs text-slate-500">
        Voľných miest: <strong>{{ remainingCapacity }}</strong>
      </div>

      <!-- Výber typov lístkov -->
      <div v-for="type in mainTypes" :key="type.id" class="rounded-lg border border-slate-200 p-3">
        <div class="flex items-start justify-between gap-2">
          <div>
            <p class="text-sm font-semibold text-slate-800">{{ type.name }}</p>
            <p v-if="type.description" class="text-xs text-slate-500">{{ type.description }}</p>
            <p class="mt-1 text-sm font-semibold" :class="type.priceAmount ? 'text-slate-800' : 'text-green-700'">
              {{ type.priceAmount ? formatPrice(type.priceAmount, type.priceCurrency) : 'Zdarma' }}
            </p>
            <p v-if="type.remainingCapacity !== null && type.remainingCapacity !== undefined" class="text-xs text-slate-400">
              Zostáva: {{ type.remainingCapacity }}
            </p>
          </div>
          <div class="flex items-center gap-2">
            <button type="button" :disabled="qty(type) <= 0"
              class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-300 text-lg leading-none text-slate-600 hover:bg-slate-50 disabled:opacity-40"
              @click="dec(type)">−</button>
            <span class="w-6 text-center text-sm font-semibold">{{ qty(type) }}</span>
            <button type="button" :disabled="qty(type) >= maxFor(type)"
              class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-300 text-lg leading-none text-slate-600 hover:bg-slate-50 disabled:opacity-40"
              @click="inc(type)">+</button>
          </div>
        </div>

        <!-- Mená účastníkov (ak to typ vyžaduje) -->
        <div v-if="type.requiresAttendeeName && qty(type) > 0" class="mt-3 space-y-2">
          <div v-for="n in qty(type)" :key="n">
            <label class="mb-1 block text-xs font-medium text-slate-600">Meno účastníka {{ n }}</label>
            <input v-model.trim="attendeeName(type, n - 1).value" type="text" maxlength="250"
              class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
          </div>
        </div>
      </div>

      <!-- Workshopy -->
      <template v-if="workshops.length">
        <div class="pt-1">
          <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Workshopy</p>
          <p v-if="!workshopsUnlocked" class="mt-1 text-xs text-slate-500">
            Najprv si vyber vstupenku — workshopy sú len pre registrovaných účastníkov.
          </p>
        </div>

        <div v-for="type in workshops" :key="type.id" class="rounded-lg border border-slate-200 p-3"
          :class="{ 'opacity-60': !workshopsUnlocked }">
          <div class="flex items-start justify-between gap-2">
            <div>
              <p class="text-sm font-semibold text-slate-800">{{ type.name }}</p>
              <p v-if="workshopTimeLabel(type)" class="text-xs font-medium text-blue-700">{{ workshopTimeLabel(type) }}</p>
              <p v-if="type.description" class="text-xs text-slate-500">{{ type.description }}</p>
              <p class="mt-1 text-sm font-semibold" :class="type.priceAmount ? 'text-slate-800' : 'text-green-700'">
                {{ type.priceAmount ? formatPrice(type.priceAmount, type.priceCurrency) : 'Zdarma' }}
              </p>
              <p v-if="type.remainingCapacity !== null && type.remainingCapacity !== undefined" class="text-xs text-slate-400">
                Zostáva: {{ type.remainingCapacity }}
              </p>
            </div>
            <div class="flex items-center gap-2">
              <button type="button" :disabled="qty(type) <= 0"
                class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-300 text-lg leading-none text-slate-600 hover:bg-slate-50 disabled:opacity-40"
                @click="dec(type)">−</button>
              <span class="w-6 text-center text-sm font-semibold">{{ qty(type) }}</span>
              <button type="button" :disabled="!workshopsUnlocked || qty(type) >= maxFor(type)"
                class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-300 text-lg leading-none text-slate-600 hover:bg-slate-50 disabled:opacity-40"
                @click="inc(type)">+</button>
            </div>
          </div>

          <!-- Mená účastníkov (ak to typ vyžaduje) -->
          <div v-if="type.requiresAttendeeName && qty(type) > 0" class="mt-3 space-y-2">
            <div v-for="n in qty(type)" :key="n">
              <label class="mb-1 block text-xs font-medium text-slate-600">Meno účastníka {{ n }}</label>
              <input v-model.trim="attendeeName(type, n - 1).value" type="text" maxlength="250"
                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
            </div>
          </div>
        </div>
      </template>

      <div class="flex items-center justify-between text-sm font-semibold text-slate-800">
        <span>Spolu ({{ totalSeats }} {{ totalSeats === 1 ? 'lístok' : 'ks' }})</span>
        <span>{{ totalPrice ? formatPrice(totalPrice, currency) : 'Zdarma' }}</span>
      </div>

      <!-- Prihlásený → one-click -->
      <div v-if="oneClick" class="rounded-lg bg-blue-50 px-3 py-2 text-xs text-blue-800">
        Objednávaš ako <strong>{{ auth.displayName }}</strong>. Potvrdenie pošleme na e-mail tvojho účtu.
      </div>

      <!-- Údaje objednávateľa (hosť alebo „iné údaje") -->
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

      <p v-if="error" class="text-sm text-red-600">{{ error }}</p>

      <button type="submit" :disabled="loading || totalSeats === 0"
        class="w-full rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60">
        {{ loading ? 'Odosielam…' : actionLabel }}
      </button>

      <button v-if="auth.isAuthenticated" type="button"
        class="w-full text-center text-xs text-slate-500 hover:text-blue-600"
        @click="useOwnDetails = !useOwnDetails">
        {{ useOwnDetails ? 'Použiť údaje z môjho účtu' : 'Zadať iné údaje' }}
      </button>
    </form>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { requestTicket } from '@/api/tickets'
import { useAuthStore } from '@/stores/auth'
import { fmtDayTimeRange } from '@/utils/dateFormat'
import type { TicketItem, TicketTypeItem } from '@/types'

const props = defineProps<{
  eventId: number
  remainingCapacity: number | null
  /** Aktívne typy lístkov vrátane workshopov — načíta ich stránka eventu. */
  types: TicketTypeItem[]
  viewerRegistered?: boolean
  registrationDeadlineAt?: string | null
  endAt?: string | null
}>()

const auth = useAuthStore()

const form = reactive({
  holder_name: '',
  holder_email: '',
  holder_phone: '',
})

const types = computed(() => props.types)
const viewerRegistered = computed(() => props.viewerRegistered ?? false)
const loading = ref(false)
const error = ref<string | null>(null)
const success = ref<TicketItem | null>(null)
const sentEmail = ref('')
const useOwnDetails = ref(false)

// Množstvá a mená účastníkov podľa id typu.
const quantities = reactive<Record<number, number>>({})
const attendees = reactive<Record<number, { value: string }[]>>({})

const isSoldOut = computed(() => props.remainingCapacity !== null && props.remainingCapacity <= 0)
const oneClick = computed(() => auth.isAuthenticated && !useOwnDetails.value)

const mainTypes = computed(() => types.value.filter(t => t.kind !== 'workshop'))
const workshops = computed(() => types.value.filter(t => t.kind === 'workshop'))

// Miesta na hlavných vstupenkách vybrané v tejto objednávke.
const mainSeatsSelected = computed(() => mainTypes.value.reduce((sum, t) => sum + qty(t), 0))

// Workshopy sú odomknuté pre registrovaného návštevníka alebo po výbere vstupenky.
const workshopsUnlocked = computed(() => viewerRegistered.value || mainSeatsSelected.value > 0)

// Plný event neblokuje formulár, ak si registrovaný účastník chce doobjednať workshop.
const soldOutBlocked = computed(
  () => isSoldOut.value && !(viewerRegistered.value && workshops.value.length > 0),
)

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

function qty(type: TicketTypeItem): number {
  return quantities[type.id!] ?? 0
}

function maxFor(type: TicketTypeItem): number {
  const caps = [type.maxPerOrder]
  if (type.remainingCapacity !== null && type.remainingCapacity !== undefined) caps.push(type.remainingCapacity)
  if (type.kind === 'workshop') {
    // Kapacita eventu sa workshopov netýka; bez registrácie limituje počet
    // vybraných vstupeniek (presný nárok registrovaného stráži backend).
    if (!viewerRegistered.value) caps.push(mainSeatsSelected.value)
  } else if (props.remainingCapacity !== null) {
    caps.push(props.remainingCapacity - mainSeatsSelected.value + qty(type))
  }
  return Math.max(0, Math.min(...caps))
}

function workshopTimeLabel(type: TicketTypeItem): string {
  return fmtDayTimeRange(type.startsAt, type.endsAt)
}

function attendeeName(type: TicketTypeItem, index: number): { value: string } {
  const list = attendees[type.id!] ?? (attendees[type.id!] = [])
  while (list.length <= index) list.push({ value: '' })
  return list[index]
}

function inc(type: TicketTypeItem) {
  if (qty(type) < maxFor(type)) quantities[type.id!] = qty(type) + 1
}

function dec(type: TicketTypeItem) {
  if (qty(type) > 0) quantities[type.id!] = qty(type) - 1
}

const totalSeats = computed(() => Object.values(quantities).reduce((a, b) => a + (b || 0), 0))

// Po ubratí vstupeniek stiahni aj miesta na workshopoch nad nový limit.
watch(mainSeatsSelected, () => {
  if (viewerRegistered.value) return
  for (const w of workshops.value) {
    if (qty(w) > maxFor(w)) quantities[w.id!] = maxFor(w)
  }
})
const currency = computed(() => types.value.find(t => t.priceAmount)?.priceCurrency ?? 'EUR')
const totalPrice = computed(() =>
  types.value.reduce((sum, t) => sum + (t.priceAmount ?? 0) * qty(t), 0),
)

const actionLabel = computed(() => {
  if (totalPrice.value > 0) return 'Získať lístky'
  return 'Rezervovať miesta'
})

function formatPrice(amount: number, curr: string | null) {
  return new Intl.NumberFormat('sk-SK', { style: 'currency', currency: curr ?? 'EUR' }).format(amount / 100)
}

async function submit() {
  if (totalSeats.value === 0) return
  loading.value = true
  error.value = null
  try {
    const items = types.value
      .filter(t => qty(t) > 0)
      .map(t => ({
        ticket_type_id: t.id!,
        quantity: qty(t),
        attendees: t.requiresAttendeeName
          ? Array.from({ length: qty(t) }, (_, i) => ({ name: attendeeName(t, i).value || null }))
          : undefined,
      }))

    const payload = oneClick.value
      ? { items }
      : {
          holder_name: form.holder_name,
          holder_email: form.holder_email,
          holder_phone: form.holder_phone || undefined,
          items,
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

onMounted(() => {
  // Predvyplň jeden lístok, ak je len jeden hlavný typ (bez workshopov).
  if (types.value.length === 1 && mainTypes.value.length === 1 && maxFor(mainTypes.value[0]) > 0) {
    quantities[mainTypes.value[0].id!] = 1
  }
})
</script>
