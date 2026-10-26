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
            <!-- Pri dotazníku s poplatkami cenu vopred nestrašíme — skutočnú sumu
                 ukáže rozpis pod formulárom podľa toho, čo si človek vyberie. -->
            <p v-if="!hasPricedQuestionnaire(type)" class="mt-1 text-sm font-semibold" :class="type.priceAmount ? 'text-slate-800' : 'text-green-700'">
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

        <button v-if="qty(type) === 0" type="button" :disabled="!orderable(type)"
          class="mt-2 w-full rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-50"
          @click="activate(type)">
          {{ !orderable(type)
            ? t('tickets.request.soldOut')
            : type.priceAmount ? t('tickets.request.buy') : t('tickets.request.reserve') }}
        </button>

        <!-- Zoznam vstupeniek: 1. patrí objednávateľovi (needitovateľná, aby
             bolo vidieť, na koho je), ostatné si pýtajú údaje účastníka. -->
        <div v-if="qty(type) > 0" class="mt-3 space-y-2">
          <!-- Pri jednej vstupenke niet čie údaje dopĺňať, hint by len mátal. -->
          <p v-if="qty(type) > 1" class="text-xs text-slate-500">
            {{ t('tickets.request.seatsHint') }}
          </p>
          <div :class="wide ? 'grid items-start gap-3 md:grid-cols-2' : 'space-y-2'">
          <div v-for="i in seatIndexes(type)" :key="i" class="space-y-2 rounded-lg bg-slate-50 p-2" :class="{ 'p-3': wide }">
            <div class="flex items-center justify-between gap-2">
              <p class="text-xs font-semibold text-slate-600">
                {{ t('tickets.request.seat', { n: i + 1 }) }}
                <span v-if="isHolderSeat(type, i)" class="font-normal text-slate-400">
                  — {{ t('tickets.request.seatYours') }}
                </span>
              </p>
              <!-- Zruší práve túto vstupenku; „−" v stepperi vždy uberá poslednú.
                   Vstupenka objednávateľa sa takto zrušiť nedá — uberá sa stepperom. -->
              <button v-if="!isHolderSeat(type, i)" type="button"
                class="-mr-0.5 rounded-md p-1 text-slate-400 hover:bg-slate-200 hover:text-red-600"
                :title="t('tickets.request.seatRemove', { n: i + 1 })"
                :aria-label="t('tickets.request.seatRemove', { n: i + 1 })"
                @click="removeSeat(type, i)">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
              </button>
            </div>

            <!-- Údaje objednávateľa sa berú z účtu alebo z polí nižšie — tu sa
                 len ukazujú. -->
            <template v-if="isHolderSeat(type, i)">
              <FormField :model-value="holderName" disabled :label="t('tickets.request.attendeeName')" :placeholder="t('tickets.request.attendeeName')" />
              <FormField :model-value="holderEmail" type="email" disabled :label="t('tickets.request.attendeeEmail')" :placeholder="t('tickets.request.attendeeEmail')" />
            </template>
            <template v-else>
              <FormField v-model="attendee(type, i).name" required trim maxlength="250" :label="t('tickets.request.attendeeName')" :placeholder="t('tickets.request.attendeeName')" />
              <FormField v-model="attendee(type, i).email" type="email" required trim maxlength="190" :label="t('tickets.request.attendeeEmail')" :placeholder="t('tickets.request.attendeeEmail')" />
            </template>

            <!-- Dotazník za účastníka (strava, ubytovanie…) -->
            <QuestionnaireFields
              v-if="seatQuestions(type).length"
              class="border-t border-slate-200 pt-2"
              :questions="seatQuestions(type)"
              :model-value="seatAnswers(type, i)"
              :errors="seatErrors(type, i)"
              :currency="type.priceCurrency"
              @update:model-value="setSeatAnswers(type, i, $event)"
            />
          </div>
          </div>

          <!-- Dotazník za objednávku (raz, nie za každého) -->
          <div v-if="orderQs.length" class="space-y-2 rounded-lg bg-slate-50 p-2">
            <p class="text-xs font-semibold text-slate-600">{{ t('questionnaire.orderTitle') }}</p>
            <QuestionnaireFields v-model="orderAnswers" :questions="orderQs" :errors="orderErrors" :currency="type.priceCurrency" />
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

          <!-- Rozpis ceny — pri viacerých platených položkách nech je jasné, z čoho je súčet. -->
          <ul v-if="breakdown(type).length > 1" class="space-y-1 border-t border-slate-200 pt-2 text-sm text-slate-600">
            <li v-for="line in breakdown(type)" :key="line.key" class="flex items-baseline justify-between gap-3">
              <span class="min-w-0">{{ line.label }}<span v-if="line.count > 1" class="text-slate-400"> × {{ line.count }}</span></span>
              <span class="shrink-0 tabular-nums">{{ line.amount ? formatPrice(line.amount, type.priceCurrency) : t('tickets.request.free') }}</span>
            </li>
          </ul>

          <div class="flex items-center justify-between border-t border-slate-200 pt-2 text-sm font-semibold text-slate-800">
            <span>{{ t('tickets.request.total', { seats: plural('tickets.request.counts.seats', qty(type)) }) }}</span>
            <span>{{ priceFor(type) ? formatPrice(priceFor(type), type.priceCurrency) : t('tickets.request.free') }}</span>
          </div>

          <p v-if="error" class="text-sm text-red-600">{{ error }}</p>

          <!-- Odoslanie patrí do karty typu: jedno spoločné tlačidlo pod
               všetkými kartami sa nedalo spárovať s tým, čo si návštevník
               práve vybral, a pri viacerých typoch mu vypadlo z obrazovky. -->
          <button type="submit" :disabled="loading"
            class="w-full rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60">
            {{ loading ? t('tickets.request.submitting') : submitLabel(type) }}
          </button>

          <button v-if="auth.isAuthenticated" type="button"
            class="w-full text-center text-xs text-slate-500 hover:text-blue-600"
            @click="useOwnDetails = !useOwnDetails">
            {{ useOwnDetails ? t('tickets.request.useAccount') : t('tickets.request.useOther') }}
          </button>
        </div>
      </div>
    </form>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { requestTicket, cancelOwnRegistration } from '@/api/tickets'
import { answerErrors, answersPrice, appliesToType, emptyAnswers, hasOptionalExtras, hasOptions, orderQuestions, publicQuestionnaire, requiredFee } from '@/api/questionnaire'
import { t, plural } from '@/i18n'
import { useAuthStore } from '@/stores/auth'
import { provideFormValidation } from '@/composables/useFormValidation'
import { fmtDayTimeRange } from '@/utils/dateFormat'
import { formatPrice } from '@/utils/money'
import FormField from '@/components/FormField.vue'
import QuestionnaireFields from '@/components/QuestionnaireFields.vue'
import type { AnswerMap, QuestionItem, TicketItem, TicketTypeItem } from '@/types'

const props = defineProps<{
  eventId: number
  /** Aktívne typy lístkov vrátane workshopov — načíta ich stránka eventu. */
  types: TicketTypeItem[]
  registrationDeadlineAt?: string | null
  endAt?: string | null
  /** Má prihlásený návštevník platnú registráciu na podujatie? */
  viewerRegistered?: boolean
  /** Formulár stojí v hlavnom stĺpci (dlhý dotazník) — miesta sú vedľa seba. */
  wide?: boolean
  /** Dotazník, ak ho stránka už načítala (kvôli cene v hlavičke); inak si ho formulár načíta sám. */
  questions?: QuestionItem[]
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

// Dotazník organizátora (strava, ubytovanie…): odpovede za každé miesto podľa
// typu a poradia, odpovede za objednávku zvlášť. Chyby prídu zo servera.
const loadedQuestions = ref<QuestionItem[]>([])
const questions = computed(() => props.questions ?? loadedQuestions.value)
const seatAnswerStore = reactive<Record<number, AnswerMap[]>>({})
const orderQs = computed(() => orderQuestions(questions.value))
const orderAnswers = ref<AnswerMap>({})
const serverErrors = ref<Record<string, string[]>>({})

onMounted(async () => {
  if (props.questions) return
  try {
    loadedQuestions.value = await publicQuestionnaire(props.eventId)
  } catch {
    // Bez otázok sa dá registrovať ďalej; povinné aj tak stráži server.
  }
})

// Otázky môžu prísť až po vykreslení (od stránky) — prázdne odpovede doplníme.
watch(orderQs, qs => { orderAnswers.value = { ...emptyAnswers(qs), ...orderAnswers.value } }, { immediate: true })

/** Cena typu nie je celá — dotazník k nemu ponúka platené možnosti. */
function hasExtras(type: TicketTypeItem): boolean {
  return hasOptionalExtras([...seatQuestions(type), ...orderQs.value])
}

/** Má dotazník k typu poplatky či príplatky? Vtedy cena lístka nie je celá cena. */
function hasPricedQuestionnaire(type: TicketTypeItem): boolean {
  return hasExtras(type) || unitPrice(type) !== (type.priceAmount ?? 0)
}

/** Cena za osobu: lístok + povinné poplatky z dotazníka (napr. účastnícky). */
function unitPrice(type: TicketTypeItem): number {
  return (type.priceAmount ?? 0) + requiredFee(seatQuestions(type)) + requiredFee(orderQs.value)
}

interface BreakdownLine { key: string; label: string; count: number; amount: number }

/** Rozpis objednávky: lístky a každá platená možnosť z dotazníka s počtom. */
function breakdown(type: TicketTypeItem): BreakdownLine[] {
  const lines: BreakdownLine[] = [{
    key: 'type',
    label: type.name,
    count: qty(type),
    amount: (type.priceAmount ?? 0) * qty(type),
  }]
  const extras = new Map<string, BreakdownLine>()

  const add = (questions: QuestionItem[], answers: AnswerMap | undefined) => {
    for (const question of questions.filter(hasOptions)) {
      const value = answers?.[question.id!]
      const ids = Array.isArray(value) ? value : value === null || value === undefined || value === '' ? [] : [Number(value)]
      for (const id of ids) {
        const option = question.options.find(o => o.id === id)
        if (!option?.priceAmount) continue
        const key = `${question.id}-${id}`
        // Poplatok s jedinou možnosťou sa volá rovnako ako otázka — názov netreba opakovať.
        const label = option.label === question.label ? question.label : `${question.label}: ${option.label}`
        const line = extras.get(key) ?? { key, label, count: 0, amount: 0 }
        line.count++
        line.amount += option.priceAmount
        extras.set(key, line)
      }
    }
  }

  for (const i of seatIndexes(type)) add(seatQuestions(type), seatAnswers(type, i))
  add(orderQs.value, orderAnswers.value)

  return [...lines, ...extras.values()]
}

function seatQuestions(type: TicketTypeItem): QuestionItem[] {
  return questions.value.filter(q => appliesToType(q, type))
}

function seatAnswers(type: TicketTypeItem, index: number): AnswerMap {
  const list = seatAnswerStore[type.id!] ?? (seatAnswerStore[type.id!] = [])
  while (list.length <= index) list.push(emptyAnswers(seatQuestions(type)))
  return list[index]
}

function setSeatAnswers(type: TicketTypeItem, index: number, value: AnswerMap) {
  seatAnswers(type, index)
  seatAnswerStore[type.id!][index] = value
}

/** Poradie typu v odosielaných `items` — podľa neho sú kľúče chýb zo servera. */
function itemIndex(type: TicketTypeItem): number {
  return orderableTypes.value.filter(t => qty(t) > 0).findIndex(t => t.id === type.id)
}

function seatErrors(type: TicketTypeItem, index: number): Record<number, string> {
  return answerErrors(serverErrors.value, `items.${itemIndex(type)}.attendees.${index}.answers`)
}

const orderErrors = computed(() => answerErrors(serverErrors.value, 'answers'))

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

/**
 * Najmenší počet, ktorý sa dá objednať. Repozitár objednávku pod `min_per_order`
 * zhodí (tickets.errors.min_per_order), takže stepper nesmie takú hodnotu vôbec
 * ponúknuť — inak by „Rezervovať" pri type s minimom 2 vždy skončilo chybou.
 * Ak zvyšná kapacita na minimum nestačí, typ sa objednať nedá vôbec.
 */
function minFor(type: TicketTypeItem): number {
  return Math.max(1, type.minPerOrder ?? 1)
}

/** Dá sa tento typ ešte objednať? Kapacita musí stačiť aspoň na minimum. */
function orderable(type: TicketTypeItem): boolean {
  return maxFor(type) >= minFor(type)
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

/** Všetky objednané vstupenky typu — vrátane tej pre objednávateľa. */
function seatIndexes(type: TicketTypeItem): number[] {
  return Array.from({ length: qty(type) }, (_, i) => i)
}

/** Prvá vstupenka prvého vybraného typu — tá je objednávateľova. */
function isHolderSeat(type: TicketTypeItem, index: number): boolean {
  return index === 0 && type.id === firstSelectedId.value
}

/** Meno a e-mail objednávateľa: z účtu, alebo z polí „iné údaje" nižšie. */
const holderName = computed(() => (oneClick.value ? auth.displayName : form.holder_name))
const holderEmail = computed(() => (oneClick.value ? auth.email : form.holder_email))

/**
 * „Rezervovať"/„Kúpiť" — aktivuje typ s predvoleným 1 miestom.
 *
 * Každá karta je samostatná objednávka s vlastným odoslaním, takže naraz môže
 * byť aktívny len jeden typ — výber iného ten predchádzajúci zruší.
 */
function activate(type: TicketTypeItem) {
  if (!orderable(type)) return
  for (const id of Object.keys(quantities)) delete quantities[Number(id)]
  for (const id of Object.keys(attendees)) delete attendees[Number(id)]
  for (const id of Object.keys(seatAnswerStore)) delete seatAnswerStore[Number(id)]
  quantities[type.id!] = minFor(type)
  error.value = null
  serverErrors.value = {}
}

function inc(type: TicketTypeItem) {
  if (qty(type) < maxFor(type)) quantities[type.id!] = qty(type) + 1
}

/**
 * Pod minimum sa neuberá po jednom — objednávka by ostala v počte, ktorý server
 * odmietne. Krok pod minimum preto výber typu rovno zruší.
 */
function dec(type: TicketTypeItem) {
  const current = qty(type)
  if (current <= 0) return

  const next = current - 1
  if (next < minFor(type)) {
    quantities[type.id!] = 0
    delete attendees[type.id!]
    delete seatAnswerStore[type.id!]
    return
  }

  quantities[type.id!] = next
}

/**
 * Zruší konkrétnu vstupenku — na rozdiel od „−" nezmaže poslednú, ale tú
 * vybranú, a údaje ďalších účastníkov sa posunú o miesto vyššie.
 */
function removeSeat(type: TicketTypeItem, index: number) {
  const id = type.id!
  const list = attendees[id]
  if (list && index < list.length) list.splice(index, 1)
  const answers = seatAnswerStore[id]
  if (answers && index < answers.length) answers.splice(index, 1)

  const left = Math.max(0, qty(type) - 1)
  quantities[id] = left
  if (left === 0) {
    delete attendees[id]
    delete seatAnswerStore[id]
  }
}

const totalSeats = computed(() => Object.values(quantities).reduce((a, b) => a + (b || 0), 0))

/** Cena za objednané miesta jedného typu vrátane príplatkov z dotazníka. */
function priceFor(type: TicketTypeItem): number {
  const seats = seatIndexes(type).reduce((sum, i) => sum + answersPrice(seatQuestions(type), seatAnswers(type, i)), 0)
  return (type.priceAmount ?? 0) * qty(type) + seats + answersPrice(orderQs.value, orderAnswers.value)
}

function submitLabel(type: TicketTypeItem): string {
  return priceFor(type) ? t('tickets.request.submitPaid') : t('tickets.request.submitFree')
}

async function submit() {
  if (totalSeats.value === 0) return
  validation.markValidated()
  loading.value = true
  error.value = null
  serverErrors.value = {}
  try {
    const items = orderableTypes.value
      .filter(t => qty(t) > 0)
      .map(t => {
        const start = t.id === firstSelectedId.value ? 1 : 0
        return {
          ticket_type_id: t.id!,
          quantity: qty(t),
          attendees: Array.from({ length: qty(t) }, (_, i) => ({
            ...(i < start
              ? { name: null, email: null }
              : { name: attendee(t, i).name || null, email: attendee(t, i).email || null }),
            answers: seatAnswers(t, i),
          })),
        }
      })

    const answers = orderAnswers.value
    const payload = oneClick.value
      ? { items, answers }
      : {
          holder_name: form.holder_name,
          holder_email: form.holder_email,
          holder_phone: form.holder_phone || undefined,
          items,
          answers,
        }
    sentEmail.value = oneClick.value ? '' : form.holder_email
    success.value = await requestTicket(props.eventId, payload)
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }
    serverErrors.value = err.response?.data?.errors ?? {}
    // `answers` bez id otázky = obsadená kapacita možnosti — tá sa ukáže celá.
    error.value = serverErrors.value['answers']?.[0]
      ?? (Object.keys(serverErrors.value).some(k => k.includes('answers'))
        ? t('questionnaire.fixErrors')
        : err.response?.data?.message ?? t('tickets.request.failed'))
  } finally {
    loading.value = false
  }
}
</script>
