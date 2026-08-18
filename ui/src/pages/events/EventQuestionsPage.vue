<template>
  <div class="mx-auto my-5 w-full max-w-[1000px] px-4">
    <EventTicketsTabs :event-id="eventId" />

    <div class="mb-4">
      <h1 class="text-2xl font-semibold text-slate-900">{{ t('questions.dashboard.title') }}</h1>
      <p class="text-sm text-slate-500">{{ t('questions.dashboard.lead') }}</p>
    </div>

    <p v-if="loading" class="text-slate-500">{{ t('questions.board.loading') }}</p>
    <p v-else-if="loadError" class="text-red-600">{{ loadError }}</p>

    <template v-else>
      <!-- Prepínač nástienok. Podujatie má vždy jednu, každý workshop môže mať
           vlastnú — s vlastným kódom, vlastnou stenou aj vlastnými otázkami. -->
      <nav v-if="slots.length > 1" class="mb-4 flex flex-wrap gap-1 rounded-lg bg-slate-100 p-1">
        <button
          v-for="slot in slots"
          :key="`${slot.targetType}-${slot.targetId}`"
          type="button"
          class="rounded-md px-3 py-1.5 text-sm font-medium transition-colors"
          :class="isActive(slot) ? 'bg-white text-blue-700 shadow-sm' : 'text-slate-600 hover:text-slate-900'"
          @click="select(slot)"
        >
          {{ slot.targetType === 'event' ? t('questions.dashboard.slotEvent') : slot.title }}
          <span v-if="slot.board?.pendingCount" class="ml-1 rounded-full bg-amber-500 px-1.5 text-xs text-white">
            {{ slot.board.pendingCount }}
          </span>
        </button>
      </nav>

      <!-- Nástenka ešte neexistuje: zakladá sa až kliknutím, aby pri každom
           podujatí a workshope neležal v databáze nepoužitý token. -->
      <section v-if="!active?.board" class="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center">
        <p class="text-slate-600">{{ t('questions.dashboard.notEnabled') }}</p>
        <button type="button" class="btn btn-primary mt-4" :disabled="enabling" @click="enable">
          {{ enabling ? t('questions.dashboard.enabling') : t('questions.dashboard.enable') }}
        </button>
      </section>

      <template v-else>
        <section class="mb-6 rounded-2xl border border-slate-200 bg-white p-5">
          <h2 class="mb-3 text-lg font-semibold text-slate-800">{{ t('questions.dashboard.materials.title') }}</h2>
          <p class="mb-4 text-xs text-slate-500">{{ t('questions.dashboard.materials.lead') }}</p>

          <SlideStudio :board="active.board" @rotate="rotate" />
        </section>

        <section class="mb-6 rounded-2xl border border-slate-200 bg-white p-5">
          <h2 class="mb-3 text-lg font-semibold text-slate-800">{{ t('questions.dashboard.settings.title') }}</h2>

          <div class="grid gap-3 lg:grid-cols-2">
            <FormField
              v-model="settings.is_open"
              type="checkbox"
              :label="t('questions.dashboard.settings.isOpen')"
              :hint="t('questions.dashboard.settings.isOpenHint')"
            />
            <FormField
              v-model="settings.moderation"
              type="checkbox"
              :label="t('questions.dashboard.settings.moderation')"
              :hint="t('questions.dashboard.settings.moderationHint')"
            />
            <FormField
              v-model="settings.show_questions"
              type="checkbox"
              :label="t('questions.dashboard.settings.showQuestions')"
              :hint="t('questions.dashboard.settings.showQuestionsHint')"
            />
            <FormField
              v-model="settings.allow_upvotes"
              type="checkbox"
              :label="t('questions.dashboard.settings.allowUpvotes')"
              :hint="t('questions.dashboard.settings.allowUpvotesHint')"
            />
            <FormField
              v-model="settings.ask_for_name"
              type="checkbox"
              :label="t('questions.dashboard.settings.askForName')"
              :hint="t('questions.dashboard.settings.askForNameHint')"
            />
          </div>

          <FormField
            v-model="settings.intro"
            class="mt-3"
            maxlength="255"
            :label="t('questions.dashboard.settings.intro')"
            :placeholder="t('questions.dashboard.settings.introPlaceholder')"
          />

          <div class="mt-3 grid gap-3 lg:grid-cols-2">
            <FormField v-model="settings.opens_at" type="datetime" :label="t('questions.dashboard.settings.opensAt')" />
            <FormField
              v-model="settings.closes_at"
              type="datetime"
              :label="t('questions.dashboard.settings.closesAt')"
              :hint="t('questions.dashboard.settings.windowHint')"
            />
          </div>

          <button type="button" class="btn btn-primary mt-4" :disabled="saving" @click="save">
            {{ saving ? t('tickets.settings.saving') : t('tickets.settings.save') }}
          </button>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5">
          <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-lg font-semibold text-slate-800">{{ t('questions.dashboard.moderation.title') }}</h2>

            <div class="flex flex-wrap gap-1 rounded-lg bg-slate-100 p-1">
              <button
                v-for="filter in filters"
                :key="String(filter.value)"
                type="button"
                class="rounded-md px-3 py-1.5 text-sm font-medium transition-colors"
                :class="statusFilter === filter.value ? 'bg-white text-blue-700 shadow-sm' : 'text-slate-600 hover:text-slate-900'"
                @click="setFilter(filter.value)"
              >
                {{ filter.label }}
                <span v-if="filter.count" class="ml-1 text-xs text-slate-400">{{ filter.count }}</span>
              </button>
            </div>
          </div>

          <p v-if="questions.length === 0" class="rounded-xl border border-dashed border-slate-300 p-8 text-center text-slate-500">
            {{ t('questions.dashboard.moderation.empty') }}
          </p>

          <ul v-else class="divide-y divide-slate-100">
            <li v-for="question in questions" :key="question.id" class="py-3">
              <div class="flex flex-wrap items-center gap-2">
                <span v-if="question.statusLabel" class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">
                  {{ question.statusLabel }}
                </span>
                <span v-if="question.highlighted" class="rounded-full bg-amber-200 px-2 py-0.5 text-xs font-semibold text-amber-900">
                  {{ t('questions.dashboard.moderation.highlight') }}
                </span>
                <span v-if="question.answeredAt" class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800">
                  {{ t('questions.list.answered') }}
                </span>
                <span class="text-xs text-slate-400">▲ {{ question.upvotesCount }}</span>
              </div>

              <p class="mt-1 whitespace-pre-line break-words text-slate-900">{{ question.body }}</p>
              <p class="mt-0.5 text-xs text-slate-500">{{ question.authorName || t('questions.list.anonymous') }}</p>

              <div class="mt-2 flex flex-wrap gap-2">
                <button v-if="question.status !== 'published'" type="button" class="action-btn" :disabled="busyId === question.id" @click="moderate(question, { status: 'published' })">
                  {{ t('questions.dashboard.moderation.approve') }}
                </button>
                <button v-if="question.status !== 'hidden'" type="button" class="action-btn" :disabled="busyId === question.id" @click="moderate(question, { status: 'hidden' })">
                  {{ t('questions.dashboard.moderation.hide') }}
                </button>
                <button type="button" class="action-btn" :disabled="busyId === question.id" @click="moderate(question, { highlighted: !question.highlighted })">
                  {{ question.highlighted ? t('questions.dashboard.moderation.unhighlight') : t('questions.dashboard.moderation.highlight') }}
                </button>
                <button type="button" class="action-btn" :disabled="busyId === question.id" @click="moderate(question, { answered: !question.answeredAt })">
                  {{ question.answeredAt ? t('questions.dashboard.moderation.unmarkAnswered') : t('questions.dashboard.moderation.markAnswered') }}
                </button>
                <button type="button" class="action-btn" @click="toggleAnswer(question)">
                  {{ t('questions.dashboard.moderation.answer') }}
                </button>
                <button type="button" class="action-btn text-red-600" :disabled="busyId === question.id" @click="remove(question)">
                  {{ t('questions.dashboard.moderation.delete') }}
                </button>
              </div>

              <!-- Písomná odpoveď zostane na nástenke aj po akcii — z jednorazovej
                   diskusie sa tak stane malé FAQ podujatia. -->
              <div v-if="answeringId === question.id" class="mt-3">
                <FormField
                  v-model="answerDraft"
                  type="textarea"
                  rows="3"
                  maxlength="2000"
                  :placeholder="t('questions.dashboard.moderation.answerPlaceholder')"
                />
                <button type="button" class="btn btn-primary btn-sm mt-2" :disabled="busyId === question.id" @click="saveAnswer(question)">
                  {{ t('questions.dashboard.moderation.saveAnswer') }}
                </button>
              </div>

              <p v-else-if="question.answerBody" class="mt-2 rounded-lg bg-emerald-50 p-3 text-sm text-emerald-900">
                {{ question.answerBody }}
              </p>
            </li>
          </ul>
        </section>
      </template>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import EventTicketsTabs from '@/components/EventTicketsTabs.vue'
import FormField from '@/components/FormField.vue'
import SlideStudio from '@/components/questions/SlideStudio.vue'
import { useToast } from '@/composables/useToast'
import { useI18n } from '@/i18n'
import {
  createQuestionBoard,
  deleteQuestion,
  indexBoardQuestions,
  indexQuestionBoards,
  moderateQuestion,
  rotateQuestionBoardToken,
  updateQuestionBoard,
  type ModerateQuestionPayload,
  type QuestionBoardSlot,
  type QuestionCounts,
  type QuestionItem,
  type QuestionStatus,
} from '@/api/questions'

const route = useRoute()
const eventId = Number(route.params.id)
const { t } = useI18n()
const toast = useToast()

const slots = ref<QuestionBoardSlot[]>([])
const activeKey = ref<string>('')
const loading = ref(true)
const loadError = ref<string | null>(null)
const enabling = ref(false)
const saving = ref(false)

const questions = ref<QuestionItem[]>([])
const counts = ref<QuestionCounts>({ pending: 0, published: 0, hidden: 0 })
const statusFilter = ref<QuestionStatus | null>(null)
const busyId = ref<number | null>(null)
const answeringId = ref<number | null>(null)
const answerDraft = ref('')

const settings = reactive({
  is_open: true,
  moderation: false,
  show_questions: true,
  allow_upvotes: true,
  ask_for_name: true,
  intro: '' as string | null,
  opens_at: '' as string | null,
  closes_at: '' as string | null,
})

const key = (slot: QuestionBoardSlot) => `${slot.targetType}-${slot.targetId}`
const active = computed(() => slots.value.find((slot) => key(slot) === activeKey.value) ?? slots.value[0] ?? null)
const isActive = (slot: QuestionBoardSlot) => key(slot) === activeKey.value

const filters = computed(() => [
  { value: null as QuestionStatus | null, label: t('questions.dashboard.moderation.all'), count: 0 },
  { value: 'pending' as QuestionStatus, label: t('questions.dashboard.moderation.pending'), count: counts.value.pending },
  { value: 'published' as QuestionStatus, label: t('questions.dashboard.moderation.published'), count: counts.value.published },
  { value: 'hidden' as QuestionStatus, label: t('questions.dashboard.moderation.hidden'), count: counts.value.hidden },
])

async function load() {
  try {
    slots.value = await indexQuestionBoards(eventId)
    if (!activeKey.value && slots.value[0]) activeKey.value = key(slots.value[0])
  } catch {
    loadError.value = t('questions.dashboard.loadFailed')
  } finally {
    loading.value = false
  }
}

function select(slot: QuestionBoardSlot) {
  activeKey.value = key(slot)
}

/** Nastavenia formulára sa napĺňajú z práve vybranej nástenky. */
watch(active, (slot) => {
  const board = slot?.board
  if (!board) return

  settings.is_open = board.isOpen
  settings.moderation = board.moderation
  settings.show_questions = board.showQuestions
  settings.allow_upvotes = board.allowUpvotes
  settings.ask_for_name = board.askForName
  settings.intro = board.intro ?? ''
  settings.opens_at = board.opensAt
  settings.closes_at = board.closesAt

  void loadQuestions()
}, { immediate: true })

async function enable() {
  if (!active.value || enabling.value) return

  enabling.value = true

  try {
    const board = await createQuestionBoard(eventId, active.value.targetType, active.value.targetId)
    active.value.board = board
  } catch {
    toast.error(t('questions.dashboard.enableFailed'))
  } finally {
    enabling.value = false
  }
}

async function save() {
  const board = active.value?.board
  if (!board || saving.value) return

  saving.value = true

  try {
    active.value!.board = await updateQuestionBoard(board.id, {
      ...settings,
      intro: settings.intro || null,
      opens_at: settings.opens_at || null,
      closes_at: settings.closes_at || null,
    })
    toast.success(t('questions.dashboard.saved'))
  } catch {
    toast.error(t('questions.dashboard.saveFailed'))
  } finally {
    saving.value = false
  }
}

async function rotate() {
  const board = active.value?.board
  if (!board || !window.confirm(t('questions.dashboard.materials.rotateConfirm'))) return

  try {
    active.value!.board = await rotateQuestionBoardToken(board.id)
    toast.success(t('questions.dashboard.materials.rotated'))
  } catch {
    toast.error(t('questions.dashboard.saveFailed'))
  }
}

async function loadQuestions() {
  const board = active.value?.board
  if (!board) {
    questions.value = []
    return
  }

  try {
    const result = await indexBoardQuestions(board.id, statusFilter.value)
    questions.value = result.questions
    counts.value = result.counts
  } catch {
    questions.value = []
  }
}

function setFilter(status: QuestionStatus | null) {
  statusFilter.value = status
  void loadQuestions()
}

async function moderate(question: QuestionItem, payload: ModerateQuestionPayload) {
  busyId.value = question.id

  try {
    await moderateQuestion(question.id, payload)
    // Zoznam sa načíta celý — zvýraznenie sa inde zruší a poradie určuje server.
    await loadQuestions()
    await load()
  } catch {
    toast.error(t('questions.dashboard.moderation.actionFailed'))
  } finally {
    busyId.value = null
  }
}

function toggleAnswer(question: QuestionItem) {
  if (answeringId.value === question.id) {
    answeringId.value = null
    return
  }

  answeringId.value = question.id
  answerDraft.value = question.answerBody ?? ''
}

async function saveAnswer(question: QuestionItem) {
  await moderate(question, { answer_body: answerDraft.value.trim() || null })
  answeringId.value = null
}

async function remove(question: QuestionItem) {
  if (!window.confirm(t('questions.dashboard.moderation.deleteConfirm'))) return

  busyId.value = question.id

  try {
    await deleteQuestion(question.id)
    await loadQuestions()
    await load()
  } catch {
    toast.error(t('questions.dashboard.moderation.actionFailed'))
  } finally {
    busyId.value = null
  }
}

onMounted(load)
</script>
