<template>
  <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
    <!-- Kroky -->
    <ol class="mb-5 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs font-medium">
      <li v-for="(label, index) in stepLabels" :key="label" class="flex items-center gap-2">
        <span
          class="flex h-5 w-5 items-center justify-center rounded-full text-[11px]"
          :class="index < stepIndex
            ? 'bg-green-600 text-white'
            : index === stepIndex
              ? 'bg-slate-900 text-white'
              : 'bg-slate-100 text-slate-400'"
        >{{ index < stepIndex ? '✓' : index + 1 }}</span>
        <span :class="index === stepIndex ? 'text-slate-900' : 'text-slate-400'">{{ label }}</span>
        <span v-if="index < stepLabels.length - 1" class="text-slate-300">›</span>
      </li>
    </ol>

    <!-- 1. Nahratie -->
    <section v-if="step === 'upload'">
      <div
        class="rounded-xl border-2 border-dashed p-8 text-center transition-colors"
        :class="dragging ? 'border-slate-900 bg-slate-50' : 'border-slate-200'"
        @dragover.prevent="dragging = true"
        @dragleave.prevent="dragging = false"
        @drop.prevent="onDrop"
      >
        <p class="mb-4 text-base font-semibold text-slate-900">{{ t('poster.wizard.drop') }}</p>

        <button type="button" class="btn btn-primary" @click="fileInput?.click()">{{ t('poster.wizard.pick') }}</button>

        <!-- Zoznam formátov stojí pod tlačidlom: je to poznámka pod čiarou,
             nie krok — nad tlačidlom len odďaľoval výzvu k akcii. -->
        <p class="mt-3 text-sm text-slate-500">{{ t('poster.wizard.formats') }}</p>
        <input
          ref="fileInput"
          type="file"
          class="hidden"
          accept=".pdf,.docx,.txt,.md,.jpg,.jpeg,.png,.webp"
          @change="onFilePicked"
        />
      </div>

      <div class="mt-4">
        <button type="button" class="text-sm font-medium text-slate-600 underline hover:text-slate-900" @click="textMode = !textMode">
          {{ textMode ? t('poster.wizard.textToggleOn') : t('poster.wizard.textToggleOff') }}
        </button>

        <div v-if="textMode" class="mt-3 grid gap-2">
          <textarea
            v-model="pastedText"
            rows="6"
            class="form-input h-auto py-2"
            :placeholder="t('poster.wizard.textPlaceholder')"
          ></textarea>
          <div>
            <button type="button" class="btn btn-primary" :disabled="pastedText.trim().length < 30" @click="analyze(pastedText)">
              {{ t('poster.wizard.textSubmit') }}
            </button>
          </div>
        </div>
      </div>

      <p v-if="error" class="mt-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ error }}</p>
    </section>

    <!-- 2. Analýza -->
    <section v-else-if="step === 'analyzing'" class="py-10 text-center">
      <div class="mx-auto mb-4 h-8 w-8 animate-spin rounded-full border-2 border-slate-200 border-t-slate-900"></div>
      <p class="text-base font-semibold text-slate-900">{{ progressMessage }}</p>
      <p class="mt-1 text-sm text-slate-500">{{ t('poster.wizard.scanNote') }}</p>
    </section>

    <!-- 3. Kontrola -->
    <section v-else-if="step === 'review' && draft">
      <header class="mb-4">
        <h2 class="text-lg font-semibold text-slate-900">{{ t('poster.wizard.reviewTitle') }}</h2>
        <p class="text-sm text-slate-500">
          {{ t('poster.wizard.reviewLead', { found: draft.analysis.found_count, total: draft.analysis.total_count }) }}
        </p>
      </header>

      <p v-if="info" class="mb-4 rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-sm text-blue-800">{{ info }}</p>

      <p v-if="draft.analysis.notice" class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
        {{ draft.analysis.notice }}
      </p>

      <!-- Prehľad nálezov -->
      <ul class="mb-5 grid gap-1.5 sm:grid-cols-2">
        <li
          v-for="field in draft.analysis.fields"
          :key="field.key"
          class="flex items-start gap-2 rounded-lg border px-3 py-2 text-sm"
          :class="statusClass(field.status)"
        >
          <span class="mt-0.5 shrink-0 font-semibold">{{ statusIcon(field.status) }}</span>
          <span class="min-w-0">
            <span class="block text-xs font-semibold uppercase tracking-wide opacity-70">{{ field.label }}</span>
            <span v-if="field.value" class="block truncate">{{ shorten(field.value) }}</span>
            <span v-else class="block italic opacity-70">
              {{ field.required ? t('poster.wizard.missingRequired') : t('poster.wizard.missing') }}
            </span>
            <span v-if="field.note" class="mt-0.5 block text-xs opacity-70">{{ field.note }}</span>
          </span>
        </li>
      </ul>

      <p v-if="draft.analysis.matches.canal || draft.analysis.matches.venue" class="mb-5 rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-sm text-blue-900">
        {{ t('poster.wizard.matchIntro') }}
        <template v-if="draft.analysis.matches.canal"> {{ t('poster.wizard.matchCanal') }} <strong>{{ draft.analysis.matches.canal.name }}</strong></template>
        <template v-if="draft.analysis.matches.canal && draft.analysis.matches.venue">, </template>
        <template v-if="draft.analysis.matches.venue"> {{ t('poster.wizard.matchVenue') }} <strong>{{ draft.analysis.matches.venue.name }}</strong></template>.
      </p>

      <!-- Opravy -->
      <div class="grid gap-3 sm:grid-cols-2">
        <FormField v-model="form.title" :label="t('poster.wizard.title')" required maxlength="250" class="sm:col-span-2" />

        <FormField v-model="form.start_at" type="datetime" :label="t('poster.wizard.startAt')" required allow-past />

        <FormField v-model="form.end_at" type="datetime" :label="t('poster.wizard.endAt')" allow-past />

        <FormField v-model="form.venueName" :label="t('poster.wizard.venueName')" required maxlength="250" :placeholder="t('poster.wizard.venueNamePlaceholder')" />

        <!-- Mesto musí byť záznam z číselníka, nie voľný text: `village_id` je
             na `venues` povinné a z preklepu ako „Nové Zámky-mesto" by vzniklo
             miesto bez obce. AI dodá len reťazec, ktorý sa tu snažíme na
             číselník napasovať — keď sa netrafí, vyberie ho človek. -->
        <FormField v-model="form.municipalityId" :label="t('poster.wizard.municipality')" required>
          <template #default="{ value, invalid, update }">
            <SearchableSelect
              :model-value="value ?? null"
              :options="municipalities"
              :placeholder="t('poster.wizard.municipalityPlaceholder')"
              :invalid="invalid"
              @update:model-value="update"
            />
          </template>
          <template #footer>
            <span v-if="form.municipalityId === null && detectedCity" class="form-hint">
              {{ t('poster.wizard.cityHint', { city: detectedCity }) }}
            </span>
          </template>
        </FormField>

        <FormField v-model="form.venueStreet" :label="t('poster.wizard.street')" maxlength="250" />

        <FormField v-model="form.organizerName" :label="t('poster.wizard.organizer')" maxlength="250" />

        <FormField v-model="form.email" type="email" :label="t('poster.wizard.email')" maxlength="250" />

        <FormField v-model="form.phone" :label="t('poster.wizard.phone')" maxlength="50" />

        <FormField :label="t('poster.wizard.description')" class="sm:col-span-2">
          <HtmlEditor v-model="form.description" :placeholder="t('poster.wizard.descriptionPlaceholder')" />
        </FormField>
      </div>

      <p v-if="error" class="mt-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ error }}</p>

      <!-- Výzva sa objaví až po prvom kliknutí na „Pokračovať" — vypísať ju nad
           práve načítaným formulárom by vyzeralo, že človek už niečo pokazil. -->
      <p v-if="validation.validated.value && !formComplete" class="mt-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
        {{ t('poster.wizard.incomplete') }}
      </p>

      <div class="mt-5 flex flex-wrap items-center gap-3">
        <button type="button" class="btn btn-primary" @click="goToAccount">
          {{ t('poster.wizard.continue') }}
        </button>
        <button type="button" class="text-sm font-medium text-slate-500 hover:text-slate-800" @click="reset">
          {{ t('poster.wizard.another') }}
        </button>
      </div>
    </section>

    <!-- 4. Účet -->
    <section v-else-if="step === 'account' && draft">
      <header class="mb-4">
        <h2 class="text-lg font-semibold text-slate-900">{{ t('poster.wizard.accountTitle') }}</h2>
        <p class="text-sm text-slate-500">
          {{ t('poster.wizard.accountLead') }}
        </p>
      </header>

      <div v-if="isAuthenticated" class="rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800">
        {{ t('poster.wizard.loggedIn') }}
      </div>

      <div v-else class="grid gap-3">
        <FormField v-model="account.email" type="email" :label="t('poster.wizard.accountEmail')" required :validated="accountValidated" />

        <div class="flex gap-2 text-sm">
          <button
            type="button"
            class="rounded-lg px-3 py-1.5 font-medium"
            :class="account.mode === 'register' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600'"
            @click="account.mode = 'register'"
          >{{ t('poster.wizard.modeRegister') }}</button>
          <button
            type="button"
            class="rounded-lg px-3 py-1.5 font-medium"
            :class="account.mode === 'login' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600'"
            @click="account.mode = 'login'"
          >{{ t('poster.wizard.modeLogin') }}</button>
        </div>

        <FormField
          v-if="account.mode === 'register'"
          v-model="account.displayName"
          :label="t('poster.wizard.accountName')"
          required
          :validated="accountValidated"
        />

        <FormField
          v-model="account.password"
          type="password"
          :label="t('poster.wizard.password')"
          required
          :validated="accountValidated"
          autocomplete="new-password"
        />

        <FormField
          v-if="account.mode === 'register'"
          v-model="account.passwordConfirmation"
          type="password"
          :label="t('poster.wizard.passwordConfirm')"
          required
          :validated="accountValidated"
          autocomplete="new-password"
        />

        <!-- Prihlásenie súhlas nepotrebuje — ten už človek dal pri registrácii. -->
        <TermsConsentField
          v-if="account.mode === 'register'"
          v-model="account.termsAccepted"
          :validated="accountValidated"
        />
      </div>

      <p v-if="error" class="mt-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ error }}</p>
      <p v-if="info" class="mt-4 rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-sm text-blue-800">{{ info }}</p>

      <div class="mt-5 flex flex-wrap items-center gap-3">
        <button type="button" class="btn btn-primary" :disabled="busy" @click="finish">
          {{ busy ? t('poster.wizard.saving') : t('poster.wizard.save') }}
        </button>
        <button type="button" class="text-sm font-medium text-slate-500 hover:text-slate-800" @click="step = 'review'">
          {{ t('poster.wizard.backToReview') }}
        </button>
      </div>
    </section>

    <!-- 5. Hotovo -->
    <section v-else-if="step === 'verify'" class="py-6 text-center">
      <p class="mb-2 text-2xl">📧</p>
      <h2 class="mb-1 text-lg font-semibold text-slate-900">{{ t('poster.wizard.verifyTitle') }}</h2>
      <p class="mx-auto max-w-md text-sm text-slate-500">
        {{ t('poster.wizard.verifyLeadBefore') }} <strong>{{ account.email }}</strong>
        {{ t('poster.wizard.verifyLeadAfter') }}
      </p>
    </section>

    <section v-else-if="step === 'done'" class="py-6 text-center">
      <p class="mb-2 text-2xl">🎉</p>
      <h2 class="mb-1 text-lg font-semibold text-slate-900">{{ t('poster.wizard.doneTitle') }}</h2>
      <p class="mb-4 text-sm text-slate-500">{{ t('poster.wizard.doneLead') }}</p>
      <RouterLink v-if="createdEventId" :to="`/dashboard/events/${createdEventId}/edit`" class="btn btn-primary">
        {{ t('poster.wizard.open') }}
      </RouterLink>
    </section>
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import FormField from '@/components/FormField.vue'
import TermsConsentField from '@/components/TermsConsentField.vue'
import HtmlEditor from '@/components/HtmlEditor.vue'
import SearchableSelect from '@/components/SearchableSelect.vue'
import { listPublicMunicipalities } from '@/api/municipalities'
import type { LookupOption } from '@/types'
import { useAuthStore } from '@/stores/auth'
import { register as registerAccount } from '@/api/auth'
import { t } from '@/i18n'
import { useToast } from '@/composables/useToast'
import { provideFormValidation } from '@/composables/useFormValidation'
import {
  analyzePoster,
  claimPosterDraft,
  fetchPosterDraft,
  rememberPosterDraft,
  type PosterDraft,
  type PosterFieldStatus,
  type PosterOverrides,
} from '@/api/posters'

type Step = 'upload' | 'analyzing' | 'review' | 'account' | 'verify' | 'done'

const stepLabels = computed(() => [
  t('poster.wizard.steps.upload'),
  t('poster.wizard.steps.analyze'),
  t('poster.wizard.steps.review'),
  t('poster.wizard.steps.account'),
])

/**
 * Rozpracovaný plagát prežíva odchod zo stránky: medzi analýzou a uložením je
 * registrácia s overením e-mailu, čo znamená odchod do schránky a návrat späť.
 * Bez tohto by sa celá analýza (a jedno platené AI volanie) stratila.
 */
const STORAGE_KEY = 'poster_draft'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const toast = useToast()

const step = ref<Step>('upload')
const draft = ref<PosterDraft | null>(null)
const token = ref<string>('')
const error = ref<string | null>(null)
const info = ref<string | null>(null)
const busy = ref(false)
const dragging = ref(false)
const textMode = ref(false)
const pastedText = ref('')
const fileInput = ref<HTMLInputElement | null>(null)
const createdEventId = ref<number | null>(null)
const progressMessage = ref(t('poster.wizard.progress.readPoster'))
let progressTimer: ReturnType<typeof setInterval> | undefined

// Sprievodca validuje dvakrát a nezávisle: najprv údaje o podujatí (krok
// „Kontrola"), potom prihlasovacie údaje (krok „Účet"). Kým sa na daný krok
// neklikne „ďalej", jeho prázdne povinné polia nie sú červené.
const validation = provideFormValidation()
const accountValidated = ref(false)

const isAuthenticated = computed(() => auth.isAuthenticated)
const stepIndex = computed(() => ({ upload: 0, analyzing: 1, review: 2, account: 3, verify: 3, done: 3 }[step.value]))

const form = reactive({
  title: '',
  start_at: '',
  end_at: '',
  venueName: '',
  municipalityId: null as number | null,
  venueStreet: '',
  organizerName: '',
  email: '',
  phone: '',
  description: '',
})

const municipalities = ref<LookupOption[]>([])
/** Reťazec mesta tak, ako ho prečítala AI — na vysvetlenie, keď sa nenapároval. */
const detectedCity = ref('')

const account = reactive({
  email: '',
  displayName: '',
  password: '',
  passwordConfirmation: '',
  termsAccepted: false,
  mode: 'register' as 'register' | 'login',
})

const formComplete = computed(() =>
  form.title.trim() !== '' &&
  form.start_at.trim() !== '' &&
  form.venueName.trim() !== '' &&
  form.municipalityId !== null,
)

const selectedCityName = computed(
  () => municipalities.value.find(m => m.id === form.municipalityId)?.name ?? '',
)

function statusClass(status: PosterFieldStatus): string {
  if (status === 'found') return 'border-green-200 bg-green-50 text-green-900'
  if (status === 'guessed') return 'border-amber-200 bg-amber-50 text-amber-900'
  return 'border-slate-200 bg-slate-50 text-slate-600'
}

function statusIcon(status: PosterFieldStatus): string {
  if (status === 'found') return '✓'
  if (status === 'guessed') return '≈'
  return '–'
}

function shorten(value: string): string {
  return value.length > 120 ? `${value.slice(0, 120)}…` : value
}

function onDrop(event: DragEvent) {
  dragging.value = false
  const file = event.dataTransfer?.files?.[0]
  if (file) analyze(file)
}

function onFilePicked(event: Event) {
  const file = (event.target as HTMLInputElement).files?.[0]
  if (file) analyze(file)
}

async function analyze(input: File | string) {
  error.value = null
  step.value = 'analyzing'
  startProgress(typeof input === 'string' ? 'text' : input.type)

  try {
    const result = await analyzePoster(input)
    applyDraft(result)
    persist()
    step.value = 'review'
  } catch (e: unknown) {
    error.value = extractMessage(e, t('poster.wizard.analyzeFailed'))
    step.value = 'upload'
  } finally {
    stopProgress()
  }
}

/**
 * Analýza trvá desiatky sekúnd a jediný spinner pôsobí ako zaseknutá stránka.
 * Hlásky sú preto naviazané na to, čo sa naozaj deje v pipeline.
 */
function startProgress(kind: string) {
  const messages = kind.startsWith('image') || kind === 'application/pdf'
    ? [
        t('poster.wizard.progress.readPoster'),
        t('poster.wizard.progress.when'),
        t('poster.wizard.progress.who'),
        t('poster.wizard.progress.compose'),
      ]
    : [
        t('poster.wizard.progress.readText'),
        t('poster.wizard.progress.when'),
        t('poster.wizard.progress.compose'),
      ]

  let index = 0
  progressMessage.value = messages[0] as string
  progressTimer = setInterval(() => {
    index = Math.min(index + 1, messages.length - 1)
    progressMessage.value = messages[index] as string
  }, 6000)
}

function stopProgress() {
  clearInterval(progressTimer)
}

function applyDraft(next: PosterDraft) {
  draft.value = next
  if (next.token) token.value = next.token

  const s = next.suggestion ?? {}
  form.title = s.title ?? ''
  form.start_at = toInputDateTime(s.start_at ?? null)
  form.end_at = toInputDateTime(s.end_at ?? null)
  form.venueName = s.venue?.name ?? ''
  form.venueStreet = s.venue?.street_and_number ?? ''
  form.organizerName = s.organizer?.name ?? ''
  form.email = s.email ?? ''
  form.phone = s.phone ?? ''
  form.description = toEditorHtml(next.description ?? '')

  detectedCity.value = s.venue?.city ?? ''
  form.municipalityId = matchMunicipality(detectedCity.value)

  // E-mail na koncepte znamená, že sa človek vracia z odkazu — účet už buď má,
  // alebo si ho medzitým založil. Prepnúť ho späť na registráciu vie jedným klikom.
  if (next.email) {
    account.email = next.email
    account.mode = 'login'
  }
}

/**
 * Popis môže prísť ako HTML (od copywritera) alebo ako surový text z dokumentu.
 * Editor pracuje s HTML — surový text by v ňom stratil odstavce aj riadkovanie
 * (v HTML sú nové riadky len medzery), takže ho najprv prevedieme.
 */
function toEditorHtml(value: string): string {
  const text = value.trim()
  if (text === '') return ''
  if (/<(?:p|br|div|h[1-6]|ul|ol|li|strong|em)\b[^>]*>/i.test(text)) return text

  const escape = (s: string) =>
    s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')

  return text
    .split(/\n{2,}/)
    .map(block => `<p>${block.split('\n').map(escape).join('<br>')}</p>`)
    .join('')
}

/**
 * Mesto z AI je voľný text („Nové Zámky", niekedy v lokáli). Skúsime ho nájsť
 * v číselníku; keď sa netrafí, ostane výber na človeku — hádať tu nechceme,
 * zlá obec by miesto priradila do iného okresu.
 */
function matchMunicipality(city: string): number | null {
  const needle = normalizeCity(city)
  if (needle === '') return null

  const exact = municipalities.value.find(m => normalizeCity(m.name) === needle)
  if (exact) return exact.id

  const partial = municipalities.value.filter(
    m => normalizeCity(m.name).startsWith(needle) || needle.startsWith(normalizeCity(m.name)),
  )

  // Jednoznačná zhoda áno, viacero kandidátov nie — „Nová Ves" má v SR desiatky
  // obcí a vybrať náhodnú je horšie než nechať pole prázdne.
  return partial.length === 1 ? (partial[0] as LookupOption).id : null
}

function normalizeCity(value: string): string {
  return value
    // NFD rozloží „Ž" na „Z" + diakritické znamienko, ktoré tu zahodíme —
    // vďaka tomu sa „Žilina" z plagátu napáruje aj na „Zilina" bez mäkčeňa.
    // Rozsah je zapísaný escapmi, aby ho neznehodnotila zmena kódovania súboru.
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, ' ')
    .trim()
}

/** `2026-08-21 18:00:00` z API → `2026-08-21T18:00` pre `datetime-local`. */
function toInputDateTime(value: string | null): string {
  if (!value) return ''
  const normalized = value.replace(' ', 'T')
  return normalized.length >= 16 ? normalized.slice(0, 16) : ''
}

function overrides(): PosterOverrides {
  return {
    title: form.title.trim() || null,
    start_at: form.start_at ? form.start_at.replace('T', ' ') : null,
    end_at: form.end_at ? form.end_at.replace('T', ' ') : null,
    email: form.email.trim() || null,
    phone: form.phone.trim() || null,
    description: form.description.trim() || null,
    venue: {
      name: form.venueName.trim() || null,
      // Posielame názov obce z číselníka, nie `id`: backend miesto zakladá cez
      // `ImportedVenueManager`, ktorý obec hľadá podľa názvu. Názov z číselníka
      // sa trafí vždy presne, na rozdiel od reťazca z plagátu.
      city: selectedCityName.value || null,
      street_and_number: form.venueStreet.trim() || null,
    },
    organizer: { name: form.organizerName.trim() || null },
  }
}

function goToAccount() {
  error.value = null
  info.value = null

  // Tlačidlo už nie je zablokované — chýbajúce polia radšej ukážeme červené
  // aj s vysvetlením, než aby človek hľadal, prečo sa nedá kliknúť.
  validation.markValidated()
  if (!formComplete.value) return

  if (!account.email && form.email) account.email = form.email
  if (!account.displayName && form.organizerName) account.displayName = form.organizerName
  step.value = 'account'
}

async function finish() {
  accountValidated.value = true
  error.value = null
  info.value = null
  busy.value = true

  try {
    if (!isAuthenticated.value) {
      if (!account.email.trim() || !account.password) {
        error.value = t('poster.wizard.credentialsRequired')
        return
      }

      // Sprievodca nie je <form>, takže natívne `required` na zaškrtávacom
      // poli nič nezastaví — súhlas treba overiť tu, ešte pred registráciou.
      if (account.mode === 'register' && !account.termsAccepted) {
        error.value = t('auth.register.termsRequired')
        return
      }

      // E-mail ukladáme ešte pred registráciou. Aj keď sa overenie natiahne
      // alebo človek zavrie okno, odkaz späť na rozpracované podujatie mu už
      // dovtedy doletel do schránky.
      await rememberPosterDraft(draft.value!.id, token.value, account.email.trim())

      if (account.mode === 'register') {
        await registerAccount({
          display_name: account.displayName.trim() || account.email.trim(),
          email: account.email.trim(),
          password: account.password,
          password_confirmation: account.passwordConfirmation,
          terms_accepted: account.termsAccepted,
        })
        persist()
        step.value = 'verify'
        return
      }

      await auth.login(account.email.trim(), account.password)
    }

    await claim()
  } catch (e: unknown) {
    error.value = extractMessage(e, t('poster.wizard.saveFailed'))
  } finally {
    busy.value = false
  }
}

async function claim() {
  const result = await claimPosterDraft(draft.value!.id, token.value, overrides())
  createdEventId.value = result.eventId
  localStorage.removeItem(STORAGE_KEY)
  step.value = 'done'
  toast.success(t('poster.wizard.saved'))

  // Identita sa práve zmenila — claim mohol založiť kanál a nastaviť ho ako
  // aktívny. Bez načítania by dashboard po presmerovaní tvrdil, že žiadny nemá.
  await auth.fetchIdentity()
}

function persist() {
  if (!draft.value) return
  localStorage.setItem(STORAGE_KEY, JSON.stringify({ id: draft.value.id, token: token.value }))
}

function reset() {
  draft.value = null
  token.value = ''
  error.value = null
  info.value = null
  createdEventId.value = null
  pastedText.value = ''
  localStorage.removeItem(STORAGE_KEY)
  step.value = 'upload'
}

/**
 * Návrat k rozpracovanému plagátu: buď z odkazu v e-maile (`/nahrat-plagat/:id`
 * s tokenom v query), alebo z localStorage po registrácii v tom istom prehliadači.
 */
async function restore() {
  const routeId = typeof route.params['id'] === 'string' ? route.params['id'] : null
  const routeToken = typeof route.query['token'] === 'string' ? route.query['token'] : null

  let id = routeId
  let restoredToken = routeToken

  if (!id || !restoredToken) {
    const stored = localStorage.getItem(STORAGE_KEY)
    if (!stored) return
    try {
      const parsed = JSON.parse(stored) as { id: string; token: string }
      id = parsed.id
      restoredToken = parsed.token
    } catch {
      localStorage.removeItem(STORAGE_KEY)
      return
    }
  }

  if (!id || !restoredToken) return

  try {
    const restored = await fetchPosterDraft(id, restoredToken)
    token.value = restoredToken
    applyDraft(restored)
    persist()

    if (restored.claimed && restored.event_id) {
      createdEventId.value = restored.event_id
      step.value = 'done'
      return
    }

    step.value = 'review'
    info.value = isAuthenticated.value
      ? t('poster.wizard.welcomeBackAuthed')
      : t('poster.wizard.welcomeBackGuest')
  } catch {
    // Vypršaný alebo cudzí token nie je chyba, na ktorú treba upozorňovať —
    // človek jednoducho začne odznova.
    localStorage.removeItem(STORAGE_KEY)
    if (routeId) router.replace({ path: '/nahrat-plagat' })
  }
}

function extractMessage(e: unknown, fallback: string): string {
  const response = (e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } })?.response
  const firstError = response?.data?.errors ? Object.values(response.data.errors)[0]?.[0] : undefined
  return firstError ?? response?.data?.message ?? fallback
}

async function loadMunicipalities() {
  try {
    municipalities.value = await listPublicMunicipalities()
  } catch {
    // Bez číselníka sa mesto nedá vybrať, ale sprievodcu to zabiť nemá —
    // človek uvidí prázdny select a vie skúsiť znova neskôr.
  }
}

// Číselník musí byť načítaný skôr, než sa pokúsime napárovať mesto z konceptu,
// inak by `matchMunicipality()` hľadala v prázdnom poli a pole ostalo prázdne.
onMounted(async () => {
  await loadMunicipalities()
  await restore()
})

onBeforeUnmount(stopProgress)
</script>
