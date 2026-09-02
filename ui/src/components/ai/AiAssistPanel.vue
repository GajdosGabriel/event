<template>
  <!--
    Panel má tri poschodia a každé sa objaví, až keď má čo povedať:

      1. poznámky z kontroly po zverejnení (len keď nejaké sú),
      2. ukazovateľ pripravenosti (kým záznam hotový nie je),
      3. samotný AI pomocník (až keď hotový je).

    Poradie nie je náhoda. Kým chýba obsah, je jediná zmysluplná rada „doplňte
    ho"; ponúkať v tej chvíli vylepšenie štýlu by bolo vylepšovanie ničoho.
  -->
  <div class="grid gap-3">
    <!-- ── 1. Poznámky ku zverejnenému textu ─────────────────────────── -->
    <div v-if="review && !reviewDismissed && review.issues.length" class="rounded-xl border border-amber-200 bg-amber-50 p-3">
      <div class="flex flex-wrap items-center justify-between gap-2">
        <p class="text-sm font-semibold text-amber-800">{{ t('ai.review.title') }}</p>
        <span v-if="review.score !== null" class="text-xs text-amber-700">
          {{ t('ai.review.score', { score: review.score }) }}
        </span>
      </div>

      <p v-if="reviewStale" class="mt-1 text-xs text-amber-700">{{ t('ai.review.stale') }}</p>

      <ul class="mt-2 grid gap-1.5">
        <li v-for="(issue, i) in review.issues" :key="i" class="text-sm text-amber-900">
          <span class="mr-1 rounded px-1.5 py-0.5 text-[11px] font-semibold"
            :class="issue.severity === 'warning' ? 'bg-amber-200 text-amber-900' : 'bg-amber-100 text-amber-700'">
            {{ t(`ai.review.severity.${issue.severity}`) }}
          </span>
          {{ issue.message }}
          <em v-if="issue.quote" class="text-amber-700">— „{{ issue.quote }}"</em>
        </li>
      </ul>

      <div class="mt-3 flex flex-wrap gap-2">
        <button v-if="review.modes.length" type="button" class="btn btn-sm bg-amber-600 text-white border-transparent hover:bg-amber-700"
          @click="fixFromReview">
          {{ t('ai.review.fix') }}
        </button>
        <button type="button" class="btn btn-sm btn-secondary" @click="reviewDismissed = true">
          {{ t('ai.review.dismiss') }}
        </button>
      </div>
    </div>

    <!-- ── 2. Ukazovateľ pripravenosti ───────────────────────────────── -->
    <div v-if="readiness.loaded.value && !readiness.ready.value && readiness.total.value > 0"
      class="rounded-xl border border-slate-200 bg-slate-50 p-3">
      <div class="flex flex-wrap items-center justify-between gap-2">
        <p class="text-sm font-semibold text-slate-700">{{ t('ai.readiness.title') }}</p>
        <span class="text-xs text-slate-500">
          {{ t('ai.readiness.progress', { done: readiness.satisfied.value, total: readiness.total.value }) }}
        </span>
      </div>

      <!-- Ukazovateľ je dekorácia nad zoznamom nižšie, preto je pre čítačku
           skrytý — číslo aj zoznam sú v texte. -->
      <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-200" aria-hidden="true">
        <div class="h-full rounded-full bg-emerald-500 transition-all" :style="{ width: readiness.percent.value + '%' }" />
      </div>

      <p class="mt-2 text-sm text-slate-600">
        {{ t('ai.readiness.missing') }}
        <span class="text-slate-800">{{ missingLabels }}</span>
      </p>
      <p class="mt-1 text-xs text-slate-500">{{ t('ai.readiness.hint') }}</p>
    </div>

    <!-- ── 3. AI pomocník ────────────────────────────────────────────── -->
    <div v-if="readiness.ready.value" class="rounded-xl border border-violet-200 bg-violet-50 p-3">
      <button type="button" class="flex cursor-pointer items-center gap-2 text-sm font-semibold text-violet-700"
        :aria-expanded="open" @click="open = !open">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7" />
          <path stroke-linecap="round" stroke-linejoin="round" d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z" />
        </svg>
        {{ open ? t('ai.hide') : t('ai.show') }}
      </button>

      <div v-if="open" class="mt-3 grid gap-3">
        <p class="text-sm text-violet-800">{{ t('ai.lead') }}</p>

        <div class="grid gap-1.5">
          <label v-for="mode in MODES" :key="mode" class="flex cursor-pointer items-start gap-2 text-sm text-violet-800">
            <input v-model="modes" type="checkbox" :value="mode" class="mt-0.5 accent-violet-600" />
            <span>
              {{ t(`ai.modes.${mode}`) }}
              <span class="block text-xs text-violet-600">{{ t(`ai.modeHints.${mode}`) }}</span>
            </span>
          </label>
        </div>

        <p class="text-xs text-violet-600">{{ t('ai.note') }}</p>

        <div class="flex flex-wrap items-center gap-3">
          <button type="button" class="btn btn-sm bg-violet-600 text-white border-transparent hover:bg-violet-700"
            :disabled="running || !modes.length" @click="run('improve')">
            {{ running && action === 'improve' ? t('ai.running') : t('ai.run') }}
          </button>

          <!-- „Napíš popis od nuly" má zmysel len tam, kde sa dá napísať vecná
               informácia o trvalom subjekte. Pri podujatí by to bol výmysel —
               server takú požiadavku aj tak odmietne. -->
          <button v-if="kind !== 'event'" type="button" class="btn btn-sm btn-secondary"
            :disabled="running" :title="t('ai.draftHint')" @click="run('draft')">
            {{ running && action === 'draft' ? t('ai.draftRunning') : t('ai.draft') }}
          </button>

          <span v-if="error" class="text-sm text-red-600">{{ error }}</span>
        </div>

        <!-- Návrh vedľa pôvodného textu, nie namiesto neho. Nahradí sa až
             tlačidlom — text, ktorý človek písal, mu nemá zmiznúť pod rukami. -->
        <div v-if="result" class="overflow-hidden rounded-lg border border-violet-200 bg-white">
          <div class="flex items-center justify-between gap-2 border-b border-violet-100 bg-violet-50 px-3 py-2">
            <p class="text-xs font-semibold text-violet-700">{{ result.changes_summary }}</p>
            <div class="flex gap-1">
              <button type="button" class="rounded px-2 py-1 text-xs"
                :class="preview === 'html' ? 'bg-violet-600 text-white' : 'text-violet-700 hover:bg-violet-100'"
                @click="preview = 'html'">{{ t('ai.preview') }}</button>
              <button type="button" class="rounded px-2 py-1 text-xs"
                :class="preview === 'raw' ? 'bg-violet-600 text-white' : 'text-violet-700 hover:bg-violet-100'"
                @click="preview = 'raw'">{{ t('ai.source') }}</button>
            </div>
          </div>

          <div class="max-h-80 overflow-auto p-3">
            <!-- HTML z návrhu prešlo cez HtmlBodyCleaner na serveri — rovnaká
                 cesta, akou sa čistí popis pri importe. -->
            <div v-if="preview === 'html'" class="prose prose-sm prose-slate max-w-none" v-html="result.improved_text" />
            <pre v-else class="whitespace-pre-wrap font-mono text-xs text-slate-700">{{ result.improved_text }}</pre>
          </div>

          <div class="flex gap-2 border-t border-violet-100 p-3">
            <button type="button" class="btn btn-sm bg-violet-600 text-white border-transparent hover:bg-violet-700"
              @click="apply">{{ t('ai.apply') }}</button>
            <button type="button" class="btn btn-sm btn-secondary" @click="result = null">{{ t('ai.discard') }}</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { t, type MessageKey } from '@/i18n'
import { useToast } from '@/composables/useToast'
import { usePublishReadiness } from '@/composables/usePublishReadiness'
import {
  aiAssist,
  fetchContentReview,
  type AiKind,
  type AiMode,
  type ContentReviewResult,
  type Scope,
} from '@/api/ai'

/**
 * Panel „Vyplniť pomocou AI".
 *
 * Jeden komponent pre podujatie, miesto aj kanál — v dashboarde aj v admine.
 * Čo sa medzi nimi líši, je `kind`; všetko ostatné (kedy sa panel ukáže, čo
 * ponúka, ako sa návrh potvrdzuje) je zámerne rovnaké, lebo je to tá istá
 * úloha a človek ju rieši v troch formulároch po sebe.
 *
 * Komponent nič neukladá. Vracia hotový text cez `v-model` a zápis do
 * databázy je vec formulára, ktorý ho obsahuje.
 */
const props = defineProps<{
  /** Popis záznamu — HTML z editora. Panel ho číta aj prepisuje. */
  modelValue: string
  kind: AiKind
  scope: Scope
  /** Ploché hodnoty formulára pre vyhodnotenie pripravenosti. */
  values: Record<string, unknown>
  /** Názov záznamu — podklad pre „napíš popis za mňa". */
  name?: string
  /** Doplňujúci kontext k názvu (typicky obec), aby model netipoval polohu. */
  context?: string
  /** Id uloženého záznamu. Bez neho sa posudok nemá čím vypýtať. */
  recordId?: number | null
}>()

const emit = defineEmits<{ 'update:modelValue': [string] }>()

const MODES: AiMode[] = ['grammar', 'style', 'expand']

const route = useRoute()
const toast = useToast()

const open = ref(false)
const running = ref(false)
const action = ref<'improve' | 'draft'>('improve')
const error = ref<string | null>(null)
const result = ref<{ improved_text: string; changes_summary: string } | null>(null)
const preview = ref<'html' | 'raw'>('html')
const modes = ref<AiMode[]>(['grammar', 'style'])

const review = ref<ContentReviewResult | null>(null)
const reviewDismissed = ref(false)

const readiness = usePublishReadiness(props.scope, props.kind, () => props.values)

/**
 * Zoznam chýbajúceho v ľudskej reči, oddelený čiarkami.
 *
 * Kľúč prichádza z konfigurácie na serveri, takže je to `string` — preklad si
 * ho musí pretypovať. Keď v `ai.readiness.keys` chýba, `t()` vráti samotný
 * kľúč a v UI je to hneď vidieť (viď i18n/index.ts).
 */
const missingLabels = computed(() =>
  readiness.missing.value.map(key => t(`ai.readiness.keys.${key}` as MessageKey)).join(', '),
)

/**
 * Text, ktorý stál vo formulári pri načítaní — teda ten, ku ktorému posudok
 * vznikol. Nastaví sa až pri prvej neprázdnej hodnote: formulár miesta aj
 * kanála sa plní v `onMounted`, takže panel sa mnohokrát zmontuje skôr, než
 * popis dorazí zo servera, a baseline z prázdneho reťazca by hlásil zmenu
 * hneď po načítaní stránky.
 */
const bodyAtLoad = ref<string | null>(null)

watch(() => props.modelValue, (val) => {
  if (bodyAtLoad.value === null && val !== '') bodyAtLoad.value = val
}, { immediate: true })

/**
 * Posudok patrí verzii textu, ktorú videl model. Keď ho človek medzitým
 * upravil, výhrady sa stlmia namiesto skrytia — konkrétny preklep v nich môže
 * stále platiť a zahodiť ich potichu by bola strata informácie.
 *
 * Porovnáva sa s textom pri načítaní, nie s odtlačkom zo servera: ten je
 * sha256 z normalizovaného tela a počítať ho v prehliadači kvôli jednej vete
 * upozornenia je zbytočná práca navyše.
 */
const reviewStale = computed(() =>
  bodyAtLoad.value !== null && props.modelValue !== bodyAtLoad.value,
)

onMounted(async () => {
  await loadReview()
  applyModesFromUrl()
})

/**
 * Posudok z e-mailu má byť vidieť aj vo formulári — inak žije len v schránke
 * a kto príde upravovať záznam z iného dôvodu, o výhradách nevie.
 */
async function loadReview() {
  if (!props.recordId) return

  try {
    review.value = await fetchContentReview(props.scope, props.kind, props.recordId)
  } catch {
    // Posudok je pomôcka; jeho výpadok nemá na formulári nič zmeniť.
  }
}

/**
 * Odkaz z e-mailu nesie `?ai=grammar,expand` — panel sa otvorí rozbalený a
 * s už zaškrtnutým tým, čoho sa poznámky týkali. Bez toho by človek pristál na
 * stránke plnej polí a hádal, čím začať.
 *
 * Hodnoty sa preosievajú cez MODES: parameter je z adresného riadka, teda
 * čokoľvek, a nemá čo skončiť v požiadavke na server.
 */
function applyModesFromUrl() {
  const raw = route.query.ai

  if (typeof raw !== 'string' || raw === '') return

  const wanted = raw.split(',').filter((m): m is AiMode => (MODES as string[]).includes(m))

  if (!wanted.length) return

  modes.value = wanted
  open.value = true
}

/** „Opraviť pomocou AI" z poznámok — zaškrtne presne to, čo kontrola našla. */
function fixFromReview() {
  if (!review.value?.modes.length) return

  modes.value = [...review.value.modes]
  open.value = true
  reviewDismissed.value = true
}

async function run(which: 'improve' | 'draft') {
  action.value = which
  running.value = true
  error.value = null
  result.value = null

  try {
    const res = await aiAssist(props.scope, which === 'draft'
      ? { kind: props.kind, action: 'draft', name: props.name ?? '', context: props.context }
      : { kind: props.kind, action: 'improve', text: props.modelValue, modes: modes.value })

    if (!res.success) throw new Error(res.error ?? t('ai.failed'))

    result.value = {
      improved_text: res.improved_text ?? '',
      changes_summary: res.changes_summary ?? '',
    }
    preview.value = 'html'
  } catch (e) {
    // Chyba z validácie servera príde ako 422 s `message`; sieťová ako Error.
    const response = (e as { response?: { data?: { message?: string } } }).response
    error.value = response?.data?.message ?? (e as Error)?.message ?? t('ai.failed')
  } finally {
    running.value = false
  }
}

function apply() {
  if (!result.value) return

  emit('update:modelValue', result.value.improved_text)
  result.value = null
  open.value = false
  // Poznámky sa týkali textu, ktorý práve prestal existovať.
  reviewDismissed.value = true
  toast.success(t('ai.applied'))
}
</script>
