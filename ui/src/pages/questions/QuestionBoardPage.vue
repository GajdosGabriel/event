<template>
  <div class="mx-auto w-full max-w-md px-4 py-8">
    <div v-if="loading" class="flex items-center justify-center gap-2 py-16 text-slate-500">
      <span class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-slate-300 border-t-blue-600" />
      {{ t('questions.board.loading') }}
    </div>

    <div v-else-if="notFound" class="rounded-2xl border border-red-200 bg-red-50 p-6 text-center">
      <p class="mb-2 text-lg font-semibold text-red-700">{{ t('questions.board.invalidTitle') }}</p>
      <p class="mb-4 text-sm text-red-600">{{ t('questions.board.invalidLead') }}</p>
      <RouterLink to="/" class="text-sm text-blue-600 hover:underline">{{ t('questions.board.home') }}</RouterLink>
    </div>

    <template v-else-if="board">
      <!-- Hlavička: kde som a čoho sa to týka. Človek sem prišiel naskenovaním
           kódu z plátna, takže musí hneď vidieť, že trafil správnu akciu. -->
      <header class="mb-5">
        <p class="text-xs font-semibold uppercase tracking-wider text-blue-600">
          {{ t('questions.board.eyebrow') }}
        </p>
        <h1 class="mt-1 text-2xl font-bold text-slate-900">{{ board.title }}</h1>
        <p v-if="board.eventName" class="mt-1 text-sm text-slate-500">{{ board.eventName }}</p>
        <p v-if="whenLabel" class="mt-1 text-sm text-slate-500">{{ whenLabel }}</p>
      </header>

      <div v-if="!board.open" class="rounded-2xl border border-slate-200 bg-slate-50 p-6 text-center">
        <p class="font-semibold text-slate-700">{{ t('questions.board.closedTitle') }}</p>
        <p class="mt-1 text-sm text-slate-500">{{ t('questions.board.closedLead') }}</p>
      </div>

      <!-- Po odoslaní: poďakovanie a cesta späť k formuláru. -->
      <div v-else-if="sent" class="rounded-2xl border border-emerald-200 bg-emerald-50 p-6 text-center">
        <p class="text-2xl" aria-hidden="true">✅</p>
        <p class="mt-1 font-semibold text-emerald-800">{{ t('questions.board.sentTitle') }}</p>
        <p class="mt-1 text-sm text-emerald-700">
          {{ lastWasPending ? t('questions.board.sentModerated') : t('questions.board.sentLead') }}
        </p>
        <button type="button" class="btn btn-secondary mt-4" @click="askAgain">
          {{ t('questions.board.askAgain') }}
        </button>
      </div>

      <!-- Formulár. Nad ohybom je len textarea a jedno veľké tlačidlo — celý
           zmysel je opýtať sa do troch sekúnd od naskenovania. -->
      <form v-else class="rounded-2xl border border-slate-200 bg-white p-5" novalidate @submit.prevent="submit">
        <p class="mb-3 text-sm text-slate-600">{{ board.intro || t('questions.board.lead') }}</p>

        <FormField
          v-model="form.body"
          type="textarea"
          rows="4"
          maxlength="500"
          required
          :error="error"
          :placeholder="t('questions.board.placeholder')"
          :validated="validated"
        />

        <p class="mt-1 text-right text-xs text-slate-400">
          {{ t('questions.board.remaining', { n: 500 - form.body.length }) }}
        </p>

        <div v-if="board.askForName" class="mt-2">
          <button
            v-if="!showName"
            type="button"
            class="text-sm text-blue-600 hover:underline"
            @click="showName = true"
          >
            {{ t('questions.board.nameToggle') }}
          </button>
          <FormField
            v-else
            v-model="form.authorName"
            maxlength="80"
            :placeholder="t('questions.board.namePlaceholder')"
          />
        </div>

        <!--
          Pasca na automaty. Človek toto pole nevidí ani doň netabuje; vyplní ho
          len robot, ktorý formulár prečítal zo zdroja. Kombinuje sa s podpísanou
          známkou z API, ktorá zároveň stráži, že medzi otvorením a odoslaním
          uplynul aspoň okamih.
        -->
        <div class="absolute left-[-9999px] h-0 w-0 overflow-hidden" aria-hidden="true">
          <label>
            Website
            <input v-model="form.website" type="text" tabindex="-1" autocomplete="off" />
          </label>
        </div>

        <button type="submit" class="btn btn-primary btn-lg mt-4 w-full" :disabled="sending">
          {{ sending ? t('questions.board.submitting') : t('questions.board.submit') }}
        </button>
      </form>

      <!-- Doterajšie otázky. Zbalené: kto prišiel z plátna, má sa najprv
           opýtať, nie čítať. Rozbalenie je vedomé kliknutie a nastavenie
           nástenky ho môže vypnúť úplne. -->
      <section v-if="board.showQuestions" class="mt-6">
        <button
          type="button"
          class="flex w-full items-center justify-between rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700"
          @click="toggleQuestions"
        >
          <span>{{ questionsOpen ? t('questions.board.hideQuestions') : t('questions.board.showQuestions') }}</span>
          <span class="text-xs font-normal text-slate-500">{{ plural('questions.board.counts', board.questionsCount) }}</span>
        </button>

        <div v-if="questionsOpen" class="mt-3">
          <p v-if="!visibleQuestions.length" class="rounded-xl border border-dashed border-slate-300 p-6 text-center text-sm text-slate-500">
            {{ t('questions.board.empty') }}
          </p>

          <QuestionList
            v-else
            :questions="visibleQuestions"
            :allow-upvotes="board.allowUpvotes"
            :voted-ids="votedIds"
            :my-ids="myIds"
            :busy-id="votingId"
            @toggle-vote="toggleVote"
          />
        </div>
      </section>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import FormField from '@/components/FormField.vue'
import QuestionList from '@/components/questions/QuestionList.vue'
import { useAnonId } from '@/composables/useAnonId'
import { fmtDayTimeRange } from '@/utils/dateFormat'
import { useI18n } from '@/i18n'
import {
  askQuestion,
  showQuestionBoard,
  streamQuestions,
  unvoteQuestion,
  voteQuestion,
  type QuestionBoardView,
  type QuestionItem,
} from '@/api/questions'

const route = useRoute()
const token = String(route.params.token ?? '')
const { t, plural } = useI18n()
const anon = useAnonId()

const board = ref<QuestionBoardView | null>(null)
const loading = ref(true)
const notFound = ref(false)

const form = reactive({ body: '', authorName: '', website: '' })
const showName = ref(false)
const validated = ref(false)
const sending = ref(false)
const sent = ref(false)
const lastWasPending = ref(false)
const error = ref<string | null>(null)

const questionsOpen = ref(false)
const votingId = ref<number | null>(null)
const votedIds = ref<number[]>([])
const myIds = ref<number[]>([])

/**
 * Vlastné otázky pri zapnutom moderovaní vo verejnom zozname nie sú — server
 * ich nepošle. Bez tohto by človek po obnovení stránky nemal ako zistiť, že
 * otázku vôbec poslal, a napísal by ju znova.
 */
const pendingMine = ref<QuestionItem[]>([])

const visibleQuestions = computed(() => {
  const server = board.value?.questions ?? []
  const serverIds = new Set(server.map((q) => q.id))

  return [...pendingMine.value.filter((q) => !serverIds.has(q.id)), ...server]
})

const whenLabel = computed(() => fmtDayTimeRange(board.value?.startsAt ?? null, board.value?.endsAt ?? null))

let pollTimer: number | undefined

async function load() {
  try {
    board.value = await showQuestionBoard(token)
  } catch {
    notFound.value = true
  } finally {
    loading.value = false
  }

  if (board.value) {
    myIds.value = anon.myIds(token)
    votedIds.value = (board.value.questions ?? []).filter((q) => anon.hasVoted(token, q.id)).map((q) => q.id)
  }
}

/**
 * Polling namiesto trvalého spojenia: hosting nemá démona, ktorý by websocket
 * obsluhoval. Osem sekúnd je kompromis medzi „stena žije" a záťažou, keď sa
 * v sále pýta dvesto telefónov naraz. Pri skrytej záložke sa nepýtame vôbec.
 */
async function poll() {
  if (!board.value || document.visibilityState !== 'visible') return

  try {
    const result = await streamQuestions(token, board.value.v)

    if (result.changed && board.value) {
      board.value.questions = result.questions ?? []
      board.value.questionsCount = result.questionsCount ?? 0
      board.value.v = result.v ?? null
    }
  } catch {
    // Výpadok siete v sále je bežný — ďalší tik to dorovná.
  }
}

function toggleQuestions() {
  questionsOpen.value = !questionsOpen.value

  if (questionsOpen.value) void poll()
}

async function submit() {
  validated.value = true
  error.value = null

  if (form.body.trim().length < 3 || !board.value) return

  sending.value = true

  try {
    const result = await askQuestion(token, {
      body: form.body.trim(),
      author_name: form.authorName.trim() || null,
      ticket: board.value.ticket,
      website: form.website,
    })

    anon.rememberMine(token, result.id)
    myIds.value = anon.myIds(token)

    if (result.question) {
      pendingMine.value = [result.question, ...pendingMine.value]
    } else {
      // Moderovaná otázka sa vo verejnom zozname neobjaví, ale odosielateľ ju
      // vidieť má — poskladáme ju z toho, čo práve napísal.
      pendingMine.value = [{
        id: result.id,
        body: form.body.trim(),
        authorName: form.authorName.trim() || null,
        upvotesCount: 0,
        answerBody: null,
        answeredAt: null,
        highlighted: false,
        createdAt: new Date().toISOString(),
        status: 'pending',
        statusLabel: null,
      }, ...pendingMine.value]
    }

    lastWasPending.value = result.pending
    sent.value = true
    questionsOpen.value = board.value.showQuestions
    form.body = ''
    form.authorName = ''
    validated.value = false
    await poll()
  } catch (e: unknown) {
    const response = (e as { response?: { data?: { errors?: Record<string, string[]>; message?: string } } })?.response?.data
    error.value = response?.errors?.body?.[0] ?? response?.message ?? t('questions.board.failed')
  } finally {
    sending.value = false
  }
}

function askAgain() {
  sent.value = false
  error.value = null
}

async function toggleVote(question: QuestionItem) {
  if (votingId.value !== null || !board.value?.allowUpvotes) return

  votingId.value = question.id
  const had = votedIds.value.includes(question.id)

  try {
    const count = had
      ? await unvoteQuestion(token, question.id, anon.anonId())
      : await voteQuestion(token, question.id, anon.anonId())

    question.upvotesCount = count

    if (had) {
      anon.forgetVote(token, question.id)
      votedIds.value = votedIds.value.filter((id) => id !== question.id)
    } else {
      anon.rememberVote(token, question.id)
      votedIds.value = [...votedIds.value, question.id]
    }
  } catch {
    // Hlas je drobnosť — chybu nemá zmysel hádzať cez celú obrazovku.
  } finally {
    votingId.value = null
  }
}

onMounted(async () => {
  // Stránka je verejná, ale nepatrí do vyhľadávača: je to jednorazový vstup
  // pre ľudí v sále a jej indexovanie by len rozsypalo obsah katalógu.
  const meta = document.createElement('meta')
  meta.name = 'robots'
  meta.content = 'noindex'
  document.head.appendChild(meta)

  await load()

  pollTimer = window.setInterval(() => void poll(), 8000)
})

onBeforeUnmount(() => {
  if (pollTimer) window.clearInterval(pollTimer)
  document.head.querySelector('meta[name="robots"][content="noindex"]')?.remove()
})
</script>
