<template>
  <ul class="space-y-3">
    <li
      v-for="question in questions"
      :key="question.id"
      class="rounded-xl border p-4 transition-colors"
      :class="rowClass(question)"
    >
      <div class="flex items-start gap-3">
        <!-- Hlasovanie je vľavo a je to jediné tlačidlo v riadku — na telefóne
             sa naň dá trafiť palcom bez mierenia. -->
        <button
          v-if="allowUpvotes"
          type="button"
          class="flex w-12 shrink-0 flex-col items-center rounded-lg border px-1 py-1.5 text-xs font-semibold transition-colors"
          :class="votedIds.includes(question.id)
            ? 'border-blue-500 bg-blue-50 text-blue-700'
            : 'border-slate-200 bg-white text-slate-600 hover:border-blue-300 hover:text-blue-700'"
          :aria-label="votedIds.includes(question.id) ? t('questions.list.upvoted') : t('questions.list.upvote')"
          :aria-pressed="votedIds.includes(question.id)"
          :disabled="busyId === question.id"
          @click="$emit('toggle-vote', question)"
        >
          <span aria-hidden="true">▲</span>
          <span>{{ question.upvotesCount }}</span>
        </button>

        <div class="min-w-0 flex-1">
          <div v-if="badges(question).length" class="mb-1 flex flex-wrap gap-1.5">
            <span
              v-for="badge in badges(question)"
              :key="badge.text"
              class="rounded-full px-2 py-0.5 text-[11px] font-semibold"
              :class="badge.class"
            >{{ badge.text }}</span>
          </div>

          <!-- Otázka je čistý text a vykresľuje sa interpoláciou, nikdy cez
               v-html — píše ju ktokoľvek bez účtu. -->
          <p class="whitespace-pre-line break-words text-slate-900">{{ question.body }}</p>

          <p class="mt-1 text-xs text-slate-500">
            {{ question.authorName || t('questions.list.anonymous') }}
          </p>

          <div v-if="question.answerBody" class="mt-3 rounded-lg bg-emerald-50 p-3">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-emerald-700">
              {{ t('questions.list.answerTitle') }}
            </p>
            <p class="mt-1 whitespace-pre-line break-words text-sm text-emerald-900">{{ question.answerBody }}</p>
          </div>
        </div>
      </div>
    </li>
  </ul>
</template>

<script setup lang="ts">
import { useI18n } from '@/i18n'
import type { QuestionItem } from '@/api/questions'

const props = withDefaults(defineProps<{
  questions: QuestionItem[]
  allowUpvotes?: boolean
  /** Id otázok, za ktoré tento prehliadač hlasoval (localStorage, nie server). */
  votedIds?: number[]
  /** Id otázok, ktoré z tohto prehliadača odišli — vrátane čakajúcich na schválenie. */
  myIds?: number[]
  /** Otázka, na ktorej práve beží požiadavka. */
  busyId?: number | null
}>(), {
  allowUpvotes: true,
  votedIds: () => [],
  myIds: () => [],
  busyId: null,
})

defineEmits<{ (e: 'toggle-vote', question: QuestionItem): void }>()

const { t } = useI18n()

function rowClass(question: QuestionItem): string {
  if (question.highlighted) return 'border-amber-300 bg-amber-50'
  if (question.answeredAt) return 'border-slate-200 bg-slate-50'
  return 'border-slate-200 bg-white'
}

function badges(question: QuestionItem): { text: string; class: string }[] {
  const list: { text: string; class: string }[] = []

  if (question.highlighted) {
    list.push({ text: t('questions.list.answering'), class: 'bg-amber-200 text-amber-900' })
  }

  if (question.answeredAt && !question.highlighted) {
    list.push({ text: t('questions.list.answered'), class: 'bg-emerald-100 text-emerald-800' })
  }

  if (question.status === 'pending') {
    list.push({ text: t('questions.list.pending'), class: 'bg-slate-200 text-slate-700' })
  }

  if (props.myIds.includes(question.id)) {
    list.push({ text: t('questions.list.mine'), class: 'bg-blue-100 text-blue-800' })
  }

  return list
}
</script>
