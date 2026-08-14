<template>
  <div>
    <!-- Úspešná objednávka -->
    <div v-if="success" class="rounded-lg bg-green-50 p-4 text-sm text-green-800">
      <p class="mb-2 font-semibold">{{ success.priceAmount ? t('tickets.request.successPaid') : t('tickets.request.successFree') }}</p>
      <p class="mb-1">{{ t('tickets.request.count') }} <strong>{{ success.admissionsTotal }}</strong></p>
      <p v-if="sentEmail" class="mb-3">{{ t('tickets.request.sentTo') }} <strong>{{ sentEmail }}</strong>.</p>
      <p v-else class="mb-3">{{ t('tickets.request.sentToAccount') }}</p>
      <RouterLink :to="`/tickets/${success.uuid}`" class="inline-block rounded-lg bg-green-700 px-4 py-2 font-medium text-white hover:bg-green-800">
        {{ t('tickets.request.show') }}
      </RouterLink>
    </div>

    <!-- Návštevník už má registráciu → možnosť ju zrušiť -->
    <div v-else-if="viewerRegistered" class="rounded-lg bg-blue-50 p-4 text-sm text-blue-900">
      <p class="mb-3 font-semibold">{{ t('tickets.request.registered') }}</p>

      <div v-if="confirmingCancel" class="space-y-2">
        <p class="text-blue-800">{{ t('tickets.request.cancelConfirm') }}</p>
        <div class="flex flex-wrap gap-2">
          <button type="button" :disabled="cancelLoading"
            class="rounded-lg bg-red-600 px-4 py-2 text-xs font-semibold text-white hover:bg-red-700 disabled:opacity-60"
            @click="cancelRegistration">
            {{ cancelLoading ? t('tickets.request.cancelling') : t('tickets.request.cancelYes') }}
          </button>
          <button type="button" class="text-xs font-medium text-slate-600 hover:text-slate-900"
            @click="confirmingCancel = false">{{ t('tickets.request.back') }}</button>
        </div>
      </div>

      <button v-else type="button"
        class="rounded-lg border border-red-300 bg-white px-4 py-2 text-xs font-semibold text-red-600 hover:bg-red-50"
        @click="confirmingCancel = true">
        {{ t('tickets.request.cancel') }}
      </button>

      <p v-if="cancelError" class="mt-2 text-red-600">{{ cancelError }}</p>
    </div>

    <!-- Registrácia uzavretá -->
    <div v-else-if="closedReason" class="rounded-lg bg-slate-100 p-4 text-sm font-medium text-slate-600">
      {{ closedReason }}
    </div>

    <div v-else-if="!orderableTypes.length" class="rounded-lg bg-slate-100 p-4 text-sm font-medium text-slate-600">
      {{ t('tickets.request.none') }}
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
              {{ type.priceAmount ? formatPrice(type.priceAmount, type.priceCurrency) : t('tickets.request.free') }}
            </p>
            <p v-if="type.remainingCapacity !== null && type.remainingCapacity !== undefined" class="text-xs text-slate-400">
              {{ t('tickets.request.remaining', { n: type.remainingCapacity }) }}
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
          {{ maxFor(type) === 0
            ? t('tickets.request.soldOut')
            : type.priceAmount ? t('tickets.request.buy') : t('tickets.request.reserve') }}
        </button>

        <!-- Údaje ďalších účastníkov (1. vstupenka patrí objednávateľovi) -->
        <div v-if="extraSeatIndexes(type).length" class="mt-3 space-y-2">
          <p class="text-xs text-slate-500">
            {{ t('tickets.request.seatsHint') }}
          </p>
          <div v-for="i in extraSeatIndexes(type)" :key="i" class="space-y-2 rounded-lg bg-slate-50 p-2">
            <div class="flex items-center justify-between gap-2">
              <p class="text-xs font-semibold text-slate-600">{{ t('tickets.request.seat', { n: i + 1 }) }}</p>
              <!-- Zruší práve túto vstupenku; „−" v stepperi vždy uberá poslednú. -->
              <button type="button"
                class="-mr-0.5 rounded-md p-1 text-slate-400 hover:bg-slate-200 hover:text-red-600"
                :title="t('tickets.request.seatRemove', { n: i + 1 })"
                :aria-label="t('tickets.request.seatRemove', { n: i + 1 })"
                @click="removeSeat(type, i)">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
              </button>
            </div>
            <FormField v-model="attendee(type, i).name" required trim maxlength="250" :placeholder="t('tickets.request.attendeeName')" />
            <FormField v-model="attendee(type, i).email" type="email" required trim maxlength="190" :placeholder="t('tickets.request.attendeeEmail')" />
          </div>
        </div>
      </div>

      <template v-if="totalSeats > 0">
        <div class="flex items-center justify-between text-sm font-semibold text-slate-800">
          <span>{{ t('tickets.request.total', { seats: plural('tickets.request.counts.seats', totalSeats) }) }}</span>
          <span>{{ totalPrice ? formatPrice(totalPrice, currency) : t('tickets.request.free') }}</span>
        </div>

        <!-- Prihlásený → one-click -->
        <div v-if="oneClick" class="rounded-lg bg-blue-50 px-3 py-2 text-xs text-blue-800">
          {{ t('tickets.request.oneClickPrefix') }} <strong>{{ auth.displayName }}</strong>. {{ t('tickets.request.oneClickSuffix') }}
        </div>

        <!-- Údaje objednávateľa (hosť alebo „iné údaje") -->
        <template v-if="!oneClick">
          <FormField v-model="form.holder_name" :label="t('tickets.request.holderName')" required trim maxlength="250" />
          <FormField v-model="form.holder_email" type="email" :label="t('tickets.request.holderEmail')" required trim maxlength="190" />
          <FormField v-model="form.holder_phone" type="tel" :label="t('tickets.request.holderPhone')" trim maxlength="30" />
        </template>

        <p v-if="error" class="text-sm text-red-600">{{ error }}</p>

        <button type="submit" :disabled="loading"
          class="w-full rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60">
          {{ loading ? t('tickets.request.submitting') : actionLabel }}
        </button>

        <button v-if="auth.isAuthenticated" type="button"
          class="w-full text-center text-xs text-slate-500 hover:text-blue-600"
          @click="useOwnDetails = !useOwnDetails">
          {{ useOwnDetails ? t('tickets.request.useAccount') : t('tickets.request.useOther') }}
        </button>
      </template>
    </form>
  </div>
</template>

<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { requestTicket, cancelOwnRegistration } from '@/api/tickets'
import { t, plural } from '@/i18n'
import { useAuthStore } from '@/stores/auth'
import { provideFormValidation } from '@/composables/useFormValidation'
import { fmtDayTimeRange } from '@/utils/dateFormat'
import { formatPrice } from '@/utils/money'
import FormField from '@/components/FormField.vue'
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
const validation = provideFormValidation()

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
    cancelError.value = err.response?.data?.message ?? t('tickets.request.cancelFailed')
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
    return t('tickets.request.closedPast')
  }
  if (props.registrationDeadlineAt && new Date(props.registrationDeadlineAt).getTime() < now) {
    return t('tickets.request.closedDeadline')
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

/**
 * Zruší konkrétnu vstupenku — na rozdiel od „−" nezmaže poslednú, ale tú
 * vybranú, a údaje ďalších účastníkov sa posunú o miesto vyššie.
 */
function removeSeat(type: TicketTypeItem, index: number) {
  const id = type.id!
  const list = attendees[id]
  if (list && index < list.length) list.splice(index, 1)

  const left = Math.max(0, qty(type) - 1)
  quantities[id] = left
  if (left === 0) delete attendees[id]
}

const totalSeats = computed(() => Object.values(quantities).reduce((a, b) => a + (b || 0), 0))

const currency = computed(() => types.value.find(t => t.priceAmount)?.priceCurrency ?? 'EUR')
const totalPrice = computed(() =>
  orderableTypes.value.reduce((sum, t) => sum + (t.priceAmount ?? 0) * qty(t), 0),
)

const actionLabel = computed(() =>
  totalPrice.value > 0 ? t('tickets.request.submitPaid') : t('tickets.request.submitFree'),
)

async function submit() {
  if (totalSeats.value === 0) return
  validation.markValidated()
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
    error.value = err.response?.data?.message ?? t('tickets.request.failed')
  } finally {
    loading.value = false
  }
}
</script>
