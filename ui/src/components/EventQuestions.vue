<template>
  <section v-if="visible" class="rounded-2xl border border-slate-200 bg-white p-6">
    <div class="mb-1 flex items-center gap-2">
      <svg class="h-4 w-4 text-violet-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
      <h2 class="text-base font-semibold text-slate-800">{{ t('public.questions.title') }}</h2>
    </div>
    <p class="mb-4 text-sm text-slate-500">{{ view?.intro || leadText }}</p>

    <!-- Zodpovedané otázky sú tu to hlavné: návštevník prišiel pre odpoveď.
         Sú aj jediná časť, ktorú má zmysel indexovať (FAQPage v JSON-LD). -->
    <ul v-if="questions.length" class="mb-5 divide-y divide-slate-100">
      <li v-for="q in questions" :key="q.id" class="py-3 first:pt-0 last:pb-0">
        <div class="flex items-start gap-2">
          <span class="mt-0.5 shrink-0 text-slate-300" aria-hidden="true">?</span>
          <div class="min-w-0 flex-1">
            <p class="text-sm font-medium text-slate-900">{{ q.body }}</p>
            <p v-if="q.authorName" class="mt-0.5 text-xs text-slate-400">{{ q.authorName }}</p>

            <div v-if="q.answerBody" class="mt-2 rounded-lg bg-slate-50 px-3 py-2">
              <p class="mb-0.5 text-xs font-semibold uppercase tracking-wide text-slate-400">
                {{ t('public.questions.answer') }}
              </p>
              <p class="whitespace-pre-line text-sm text-slate-700">{{ q.answerBody }}</p>
            </div>
            <p v-else class="mt-1 text-xs text-slate-400">{{ t('public.questions.awaitingAnswer') }}</p>
          </div>
        </div>
      </li>
    </ul>

    <!-- Odoslané -->
    <div v-if="sent" class="rounded-lg bg-green-50 p-4 text-sm text-green-800">
      <p class="font-semibold">{{ pending ? t('public.questions.pendingTitle') : t('public.questions.sentTitle') }}</p>
      <p>{{ pending ? t('public.questions.pendingLead') : t('public.questions.sentLead') }}</p>
      <p v-if="notified">{{ t('public.questions.sentLeadNotify') }}</p>
      <button type="button" class="mt-2 text-sm font-medium text-green-700 hover:underline" @click="askAgain">
        {{ t('public.questions.askAnother') }}
      </button>
    </div>

    <!-- Formulár. Otvára sa až kliknutím — prázdne pole pod zoznamom odpovedí
         púta pozornosť, ktorú tu odpovede potrebujú viac. -->
    <template v-else-if="view?.open">
      <button
        v-if="!formOpen"
        type="button"
        class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
        @click="formOpen = true"
      >{{ t('public.questions.ask') }}</button>

      <form v-else class="space-y-3" @submit.prevent="submit">
        <FormField
          v-model="body"
          type="textarea"
          :label="t('public.questions.yourQuestion')"
          required
          trim
          rows="3"
          maxlength="500"
          :placeholder="t('public.questions.placeholder')"
        />

        <!-- Prihláseného sa na meno nepýtame — vieme ho z účtu a doplní ho
             server (rovnako ako pri objednávke lístka). -->
        <FormField
          v-if="view.askForName && !signedIn"
          v-model="authorName"
          :label="t('public.questions.yourName')"
          trim
          maxlength="80"
          :hint="t('public.questions.nameHint')"
        />
        <p v-else-if="view.askForName" class="text-xs text-slate-500">
          {{ t('public.questions.signedAsAccount', { name: auth.displayName }) }}
        </p>

        <!-- Odpoveď e-mailom. Zámerne nezaškrtnuté: adresu pýtame len od toho,
             kto o odpoveď naozaj stojí. -->
        <FormField
          v-model="notify"
          type="checkbox"
          :label="t('public.questions.notifyMe')"
        />

        <FormField
          v-if="notify && !signedIn"
          v-model="authorEmail"
          type="email"
          :label="t('public.questions.yourEmail')"
          required
          trim
          maxlength="190"
          :placeholder="t('public.questions.emailPlaceholder')"
          :hint="t('public.questions.emailPrivacy')"
        />
        <p v-else-if="notify" class="text-xs text-slate-500">
          {{ t('public.questions.notifyAccount') }}
        </p>

        <!-- Pasca na roboty: mimo obrazovky, bez tabulátora, skrytá pre čítačky. -->
        <div class="absolute left-[-9999px]" aria-hidden="true">
          <label>
            Website
            <input v-model="website" type="text" tabindex="-1" autocomplete="off" />
          </label>
        </div>

        <p v-if="view.moderation" class="text-xs text-slate-500">{{ t('public.questions.moderationNote') }}</p>
        <p v-if="error" class="text-sm text-red-600">{{ error }}</p>

        <div class="flex gap-2">
          <button
            type="submit"
            :disabled="submitting"
            class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60"
          >{{ submitting ? t('public.questions.submitting') : t('public.questions.submit') }}</button>
          <button
            type="button"
            class="rounded-lg px-4 py-2 text-sm font-medium text-slate-500 hover:bg-slate-50"
            @click="formOpen = false"
          >{{ t('public.questions.cancel') }}</button>
        </div>
      </form>
    </template>

    <!-- Zavretá nástenka so zodpovedanými otázkami ostáva ako archív. -->
    <p v-else class="text-sm text-slate-400">{{ t('public.questions.closed') }}</p>
  </section>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import {
  showEventQuestions,
  askEventQuestion,
  type EventQuestionsView,
  type QuestionItem,
} from '@/api/questions'
import { t, currentLocale } from '@/i18n'
import { provideFormValidation } from '@/composables/useFormValidation'
import { useAuthStore } from '@/stores/auth'
import FormField from '@/components/FormField.vue'

const props = defineProps<{ eventId: number }>()

const validation = provideFormValidation()
const auth = useAuthStore()

/**
 * Prihlásený nevypĺňa meno ani adresu — obe vieme z účtu a doplní ich server.
 * Klient by to ani nezvládol: e-mail účtu sa do SPA vôbec neposiela.
 */
const signedIn = computed(() => auth.isAuthenticated)

const view = ref<EventQuestionsView | null>(null)
const questions = ref<QuestionItem[]>([])
const formOpen = ref(false)
const submitting = ref(false)
const sent = ref(false)
const pending = ref(false)
const notified = ref(false)
const error = ref<string | null>(null)
const body = ref('')
const authorName = ref('')
const authorEmail = ref('')
const notify = ref(false)
const website = ref('')

/**
 * Prázdna sekcia je horšia než žiadna. Nástenka sa zakladá lenivo, takže väčšina
 * podujatí ju nemá vôbec — a tá, ktorá ju má, môže byť zavretá a bez otázok.
 * Ukazujeme ju len vtedy, keď sa dá niečo opýtať alebo je čo čítať.
 */
const visible = computed(() => {
  const v = view.value
  if (!v?.available || !v.showQuestions) return false
  return v.open || questions.value.length > 0
})

/** Pred podujatím smeruje otázka organizátorovi, počas neho prednášajúcemu. */
const leadText = computed(() =>
  view.value?.phase === 'live' ? t('public.questions.leadLive') : t('public.questions.leadBefore'),
)

onMounted(load)

async function load() {
  try {
    const result = await showEventQuestions(props.eventId)
    view.value = result
    questions.value = result.questions
  } catch {
    // Otázky sú doplnok stránky — pri chybe sa sekcia jednoducho nevykreslí.
    view.value = null
  }
}

function askAgain() {
  sent.value = false
  pending.value = false
  notified.value = false
  formOpen.value = true
}

async function submit() {
  validation.markValidated()
  submitting.value = true
  error.value = null

  try {
    const result = await askEventQuestion(props.eventId, {
      body: body.value,
      author_name: authorName.value || null,
      // Adresu posiela len hosť — prihlásenému ju server vezme z účtu.
      notify: notify.value,
      author_email: authorEmail.value || null,
      locale: currentLocale(),
      ticket: view.value?.ticket ?? '',
      website: website.value,
    })

    // Bez moderovania je otázka rovno vonku — nech ju človek vidí v zozname
    // hneď a nemusí stránku obnovovať.
    if (result.question) {
      questions.value = [...questions.value, result.question]
    }

    pending.value = result.pending
    notified.value = result.notify
    sent.value = true
    formOpen.value = false
    body.value = ''
    authorName.value = ''
    authorEmail.value = ''
    notify.value = false
    validation.reset()
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    error.value = err.response?.data?.message ?? t('public.questions.failed')
    // Známka je po odmietnutí spálená — ďalší pokus si vypýta čerstvú.
    await load()
  } finally {
    submitting.value = false
  }
}
</script>
