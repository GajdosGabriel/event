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

    <!-- Návštevník už má registráciu → možnosť ju zrušiť -->
    <div v-else-if="viewerRegistered" class="rounded-lg bg-blue-50 p-4 text-sm text-blue-900">
      <p class="mb-3 font-semibold">Na toto podujatie si prihlásený.</p>

      <div v-if="confirmingCancel" class="space-y-2">
        <p class="text-blue-800">Naozaj zrušiť registráciu na toto podujatie?</p>
        <div class="flex flex-wrap gap-2">
          <button type="button" :disabled="cancelLoading"
            class="rounded-lg bg-red-600 px-4 py-2 text-xs font-semibold text-white hover:bg-red-700 disabled:opacity-60"
            @click="cancelRegistration">
            {{ cancelLoading ? 'Ruším…' : 'Áno, zrušiť registráciu' }}
          </button>
          <button type="button" class="text-xs font-medium text-slate-600 hover:text-slate-900"
            @click="confirmingCancel = false">Späť</button>
        </div>
      </div>

      <button v-else type="button"
        class="rounded-lg border border-red-300 bg-white px-4 py-2 text-xs font-semibold text-red-600 hover:bg-red-50"
        @click="confirmingCancel = true">
        Zrušiť rezerváciu / Odhlásiť sa
      </button>

      <p v-if="cancelError" class="mt-2 text-red-600">{{ cancelError }}</p>
    </div>

    <!-- Registrácia uzavretá -->
    <div v-else-if="closedReason" class="rounded-lg bg-slate-100 p-4 text-sm font-medium text-slate-600">
      {{ closedReason }}
    </div>

    <div v-else-if="!orderableTypes.length" class="rounded-lg bg-slate-100 p-4 text-sm font-medium text-slate-600">
      Pre toto podujatie zatiaľ nie sú v predaji žiadne lístky.
    </div>

    <form v-else class="space-y-4" @submit.prevent="submit">
      <!-- Výber typov lístkov -->
      <div v-for="type in orderableTypes" :key="type.id" class="rounded-lg border border-slate-200 p-3">
        <div class="flex items-start justify-between gap-2">
          <div>
            <p class="text-sm font-semibold text-slate-800">{{ type.name }}</p>
            <p v-if="timeLabel(type)" class="text-xs font-medium text-blue-700">{{ timeLabel(type) }}</p>
            <p v-if="type.description" class="text-xs text-slate-500">{{ type.description }}</p>
            <p class="mt-1 text-sm font-semibold" :class="type.priceAmount ? 'text-slate-800' : 'text-green-700'">
              {{ type.priceAmount ? formatPrice(type.priceAmount, type.priceCurrency) : 'Zdarma' }}
            </p>
            <p v-if="type.remainingCapacity !== null && type.remainingCapacity !== undefined" class="text-xs text-slate-400">
              Zostáva: {{ type.remainingCapacity }}
            </p>
          </div>

          <!-- Stepper sa ukáže až po aktivácii typu tlačidlom nižšie -->
          <div v-if="qty(type) > 0" class="flex items-center gap-2">
            <button type="button" :disabled="qty(type) <= 0"
              class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-300 text-lg leading-none text-slate-600 hover:bg-slate-50 disabled:opacity-40"
              @click="dec(type)">−</button>
            <span class="w-6 text-center text-sm font-semibold">{{ qty(type) }}</span>
            <button type="button" :disabled="qty(type) >= maxFor(type)"
              class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-300 text-lg leading-none text-slate-600 hover:bg-slate-50 disabled:opacity-40"
              @click="inc(type)">+</button>
          </div>
        </div>

        <button v-if="qty(type) === 0" type="button" :disabled="maxFor(type) === 0"
          class="mt-2 w-full rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-50"
          @click="activate(type)">
          {{ maxFor(type) === 0 ? 'Vypredané' : type.priceAmount ? 'Kúpiť' : 'Rezervovať' }}
        </button>

        <!-- Údaje ďalších účastníkov (1. vstupenka patrí objednávateľovi) -->
        <div v-if="extraSeatIndexes(type).length" class="mt-3 space-y-2">
          <p class="text-xs text-slate-500">
            Prvá vstupenka patrí tebe. Vyplň údaje ostatných účastníkov — každému pošleme jeho vstupenku e-mailom.
          </p>
          <div v-for="i in extraSeatIndexes(type)" :key="i" class="space-y-2 rounded-lg bg-slate-50 p-2">
            <p class="text-xs font-semibold text-slate-600">Vstupenka {{ i + 1 }}</p>
            <input v-model.trim="attendee(type, i).name" type="text" required maxlength="250"
              placeholder="Meno a priezvisko"
              class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
            <input v-model.trim="attendee(type, i).email" type="email" required maxlength="190"
              placeholder="E-mail"
              class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
          </div>
        </div>
      </div>

      <template v-if="totalSeats > 0">
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

        <button type="submit" :disabled="loading"
          class="w-full rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60">
          {{ loading ? 'Odosielam…' : actionLabel }}
        </button>

        <button v-if="auth.isAuthenticated" type="button"
          class="w-full text-center text-xs text-slate-500 hover:text-blue-600"
          @click="useOwnDetails = !useOwnDetails">
          {{ useOwnDetails ? 'Použiť údaje z môjho účtu' : 'Zadať iné údaje' }}
        </button>
      </template>
    </form>
  </div>
</template>

<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { requestTicket, cancelOwnRegistration } from '@/api/tickets'
import { useAuthStore } from '@/stores/auth'
import { fmtDayTimeRange } from '@/utils/dateFormat'
import type { TicketItem, TicketTypeItem } from '@/types'

const props = defineProps<{
  eventId: number
  /** Aktívne typy lístkov vrátane workshopov — načíta ich stránka eventu. */
  types: TicketTypeItem[]
  registrationDeadlineAt?: string | null
  endAt?: string | null
  /** Má prihlásený návštevník platnú registráciu na podujatie? */
  viewerRegistered?: boolean
}>()

const emit = defineEmits<{ changed: [] }>()

const auth = useAuthStore()

const form = reactive({
  holder_name: '',
  holder_email: '',
  holder_phone: '',
})

const types = computed(() => props.types)
const loading = ref(false)
const error = ref<string | null>(null)
const success = ref<TicketItem | null>(null)
const sentEmail = ref('')
const useOwnDetails = ref(false)

// Zrušenie vlastnej registrácie (keď už návštevník lístok má).
const confirmingCancel = ref(false)
const cancelLoading = ref(false)
const cancelError = ref<string | null>(null)

async function cancelRegistration() {
  cancelLoading.value = true
  cancelError.value = null
  try {
    await cancelOwnRegistration(props.eventId)
    confirmingCancel.value = false
    emit('changed')
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    cancelError.value = err.response?.data?.message ?? 'Registráciu sa nepodarilo zrušiť.'
  } finally {
    cancelLoading.value = false
  }
}

// Množstvá a údaje účastníkov podľa id typu.
const quantities = reactive<Record<number, number>>({})
const attendees = reactive<Record<number, { name: string; email: string }[]>>({})

const oneClick = computed(() => auth.isAuthenticated && !useOwnDetails.value)

const mainTypes = computed(() => types.value.filter(t => t.kind !== 'workshop'))

// Workshopy sa objednávajú v sekcii „Workshopy" na stránke podujatia, nie tu.
// Výnimka: podujatie len s workshopmi — vtedy sú samostatnou registráciou
// a objednávajú sa priamo v tomto formulári.
const orderableTypes = computed(() => (mainTypes.value.length ? mainTypes.value : types.value))

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
  return Math.max(0, Math.min(...caps))
}

function timeLabel(type: TicketTypeItem): string {
  return fmtDayTimeRange(type.startsAt, type.endsAt)
}

function attendee(type: TicketTypeItem, index: number): { name: string; email: string } {
  const list = attendees[type.id!] ?? (attendees[type.id!] = [])
  while (list.length <= index) list.push({ name: '', email: '' })
  return list[index]
}

/** Prvý vybraný typ — jeho prvá vstupenka patrí objednávateľovi. */
const firstSelectedId = computed(() => orderableTypes.value.find(t => qty(t) > 0)?.id ?? null)

/** Indexy vstupeniek typu, ku ktorým treba vyplniť údaje účastníka. */
function extraSeatIndexes(type: TicketTypeItem): number[] {
  const start = type.id === firstSelectedId.value ? 1 : 0
  const n = qty(type)
  return n > start ? Array.from({ length: n - start }, (_, i) => i + start) : []
}

/** „Rezervovať"/„Kúpiť" — aktivuje typ s predvoleným 1 miestom. */
function activate(type: TicketTypeItem) {
  if (maxFor(type) > 0) quantities[type.id!] = 1
}

function inc(type: TicketTypeItem) {
  if (qty(type) < maxFor(type)) quantities[type.id!] = qty(type) + 1
}

function dec(type: TicketTypeItem) {
  if (qty(type) > 0) quantities[type.id!] = qty(type) - 1
}

const totalSeats = computed(() => Object.values(quantities).reduce((a, b) => a + (b || 0), 0))

const currency = computed(() => types.value.find(t => t.priceAmount)?.priceCurrency ?? 'EUR')
const totalPrice = computed(() =>
  orderableTypes.value.reduce((sum, t) => sum + (t.priceAmount ?? 0) * qty(t), 0),
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
    const items = orderableTypes.value
      .filter(t => qty(t) > 0)
      .map(t => {
        const start = t.id === firstSelectedId.value ? 1 : 0
        return {
          ticket_type_id: t.id!,
          quantity: qty(t),
          attendees: Array.from({ length: qty(t) }, (_, i) =>
            i < start
              ? { name: null, email: null }
              : { name: attendee(t, i).name || null, email: attendee(t, i).email || null },
          ),
        }
      })

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
</script>
