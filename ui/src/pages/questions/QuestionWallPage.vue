<template>
  <div class="fixed inset-0 z-50 overflow-hidden bg-slate-950 text-white">
    <div v-if="loading" class="flex h-full items-center justify-center text-slate-400">
      {{ t('questions.board.loading') }}
    </div>

    <div v-else-if="notFound" class="flex h-full flex-col items-center justify-center gap-3 px-6 text-center">
      <p class="text-2xl font-semibold">{{ t('questions.board.invalidTitle') }}</p>
      <p class="text-slate-400">{{ t('questions.board.invalidLead') }}</p>
    </div>

    <div v-else-if="board" class="flex h-full flex-col p-6 lg:p-10">
      <header class="mb-6 flex items-start justify-between gap-6">
        <div class="min-w-0">
          <p class="text-sm font-semibold uppercase tracking-[0.2em] text-blue-400">
            {{ t('questions.wall.title') }}
          </p>
          <h1 class="mt-1 truncate text-3xl font-bold lg:text-5xl">{{ board.title }}</h1>
          <p v-if="board.eventName" class="mt-1 truncate text-lg text-slate-400">{{ board.eventName }}</p>
        </div>

        <!-- QR ostáva na stene natrvalo. Kto prišiel neskôr alebo si snímku
             nestihol naskenovať, nemusí čakať na to, kým ju niekto premietne
             znova. -->
        <div class="shrink-0 rounded-2xl bg-white p-3 text-center">
          <!-- Zámerne veľký: na plátne sa sníma z celej sály, nie z ruky. -->
          <img :src="qrUrl" alt="" class="h-28 w-28 lg:h-44 lg:w-44" />
          <p class="mt-1 text-[11px] font-semibold text-slate-700 lg:text-xs">{{ t('questions.wall.join') }}</p>
          <p class="text-[11px] text-slate-500 lg:text-xs">{{ shortUrl }}</p>
        </div>
      </header>

      <p v-if="!questions.length" class="flex flex-1 items-center justify-center text-xl text-slate-500">
        {{ t('questions.wall.empty') }}
      </p>

      <!-- Stĺpce, nie jeden dlhý zoznam: na 16:9 plátne je výška vzácna
           a v jednom stĺpci by sa zmestili tri otázky. -->
      <div v-else class="min-h-0 flex-1 overflow-y-auto">
        <div class="columns-1 gap-5 lg:columns-2 2xl:columns-3">
          <article
            v-for="question in questions"
            :key="question.id"
            class="mb-5 break-inside-avoid rounded-2xl p-5"
            :class="question.highlighted
              ? 'bg-amber-400 text-slate-950 shadow-2xl'
              : question.answeredAt
                ? 'bg-slate-900 text-slate-500'
                : 'bg-slate-800 text-white'"
          >
            <p v-if="question.highlighted" class="mb-2 text-xs font-bold uppercase tracking-widest">
              {{ t('questions.wall.highlight') }}
            </p>
            <p v-else-if="question.answeredAt" class="mb-2 text-xs font-bold uppercase tracking-widest">
              {{ t('questions.list.answered') }}
            </p>

            <p
              class="whitespace-pre-line break-words font-semibold"
              :class="question.highlighted ? 'text-2xl lg:text-4xl' : 'text-lg lg:text-2xl'"
            >{{ question.body }}</p>

            <div class="mt-3 flex items-center gap-3 text-sm opacity-70">
              <span v-if="board.allowUpvotes">▲ {{ question.upvotesCount }}</span>
              <span class="truncate">{{ question.authorName || t('questions.list.anonymous') }}</span>
            </div>

            <!-- Moderátorské tlačidlá vidí len ten, kto má na podujatie právo;
                 server ich aj tak overuje policy, toto je len skrytie. -->
            <div v-if="canModerate" class="mt-4 flex flex-wrap gap-2">
              <button type="button" class="wall-action" :disabled="busyId === question.id" @click="highlight(question)">
                {{ question.highlighted ? t('questions.wall.unhighlight') : t('questions.wall.highlight') }}
              </button>
              <button type="button" class="wall-action" :disabled="busyId === question.id" @click="markAnswered(question)">
                {{ t('questions.wall.markAnswered') }}
              </button>
              <button type="button" class="wall-action" :disabled="busyId === question.id" @click="hide(question)">
                {{ t('questions.wall.hide') }}
              </button>
            </div>
          </article>
        </div>
      </div>

      <footer class="mt-4 flex items-center justify-between text-xs text-slate-500">
        <span>{{ plural('questions.board.counts', board.questionsCount) }}</span>
        <button type="button" class="hover:text-slate-300" @click="toggleFullscreen">
          {{ isFullscreen ? t('questions.wall.exitFullscreen') : t('questions.wall.fullscreen') }}
        </button>
      </footer>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { boardQrUrl, moderateQuestion, showQuestionBoard, streamQuestions, type QuestionBoardView, type QuestionItem } from '@/api/questions'
import { useAuthStore } from '@/stores/auth'
import { publicQuestionBoardPath } from '@/utils/publicUrl'
import { useI18n } from '@/i18n'

const route = useRoute()
const token = String(route.params.token ?? '')
const { t, plural } = useI18n()
const auth = useAuthStore()

const board = ref<QuestionBoardView | null>(null)
const loading = ref(true)
const notFound = ref(false)
const busyId = ref<number | null>(null)
const isFullscreen = ref(false)

const questions = computed(() => board.value?.questions ?? [])

/**
 * Samotný QR, nie zmenšená snímka — v rohu má sto pixelov a musí sa dať
 * naskenovať z desiatich metrov.
 */
const qrUrl = computed(() => boardQrUrl(token))
const shortUrl = computed(() => `${location.host}${publicQuestionBoardPath(board.value?.code ?? '')}`)

/**
 * Moderátorské tlačidlá sa ukážu prihlásenému. Či na ne naozaj má právo,
 * rozhodne server pri prvom kliknutí — stena je verejná na token a nevie,
 * do ktorého kanála podujatie patrí.
 */
const canModerate = computed(() => auth.isAuthenticated)

let pollTimer: number | undefined

async function load() {
  try {
    board.value = await showQuestionBoard(token)
  } catch {
    notFound.value = true
  } finally {
    loading.value = false
  }
}

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
    // Sieť v sále vypadáva; ďalší tik to dorovná.
  }
}

async function moderate(question: QuestionItem, payload: Parameters<typeof moderateQuestion>[1]) {
  busyId.value = question.id

  try {
    await moderateQuestion(question.id, payload)
    // Zásah sa neaplikuje lokálne — poradie určuje server (zvýraznená ide hore
    // a zvýraznenie sa inde zruší), takže sa zoznam natiahne celý.
    board.value!.v = null
    await poll()
  } catch {
    // Nemám právo alebo vypadla sieť — stena beží ďalej.
  } finally {
    busyId.value = null
  }
}

const highlight = (q: QuestionItem) => moderate(q, { highlighted: !q.highlighted })
const markAnswered = (q: QuestionItem) => moderate(q, { answered: !q.answeredAt, highlighted: false })
const hide = (q: QuestionItem) => moderate(q, { status: 'hidden' })

async function toggleFullscreen() {
  if (document.fullscreenElement) {
    await document.exitFullscreen()
    return
  }

  await document.documentElement.requestFullscreen().catch(() => undefined)
}

function syncFullscreen() {
  isFullscreen.value = Boolean(document.fullscreenElement)
}

onMounted(async () => {
  await load()

  // Stena beží päť sekúnd, nie osem ako telefón: na plátne je oneskorenie
  // medzi „poslal som" a „vidím to tam" tá jediná vec, ktorú si sála všimne.
  pollTimer = window.setInterval(() => void poll(), 5000)
  document.addEventListener('fullscreenchange', syncFullscreen)
})

onBeforeUnmount(() => {
  if (pollTimer) window.clearInterval(pollTimer)
  document.removeEventListener('fullscreenchange', syncFullscreen)
})
</script>

<style scoped>
@reference "tailwindcss";

.wall-action {
  @apply rounded-lg bg-white/15 px-3 py-1.5 text-xs font-semibold text-white transition-colors hover:bg-white/25 disabled:opacity-50;
}
</style>
