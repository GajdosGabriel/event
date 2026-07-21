<template>
  <div>
    <button type="button"
      class="flex w-full items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700"
      @click="open = true">
      <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.86 9.86 0 01-4-.8L3 20l.8-4A7.94 7.94 0 013 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
      </svg>
      {{ label }}
    </button>

    <!-- Modal -->
    <Teleport to="body">
      <Transition enter-active-class="transition duration-150" enter-from-class="opacity-0" enter-to-class="opacity-100"
        leave-active-class="transition duration-100" leave-from-class="opacity-100" leave-to-class="opacity-0">
        <div v-if="open" class="fixed inset-0 z-9999 flex items-center justify-center bg-black/50 p-4"
          @click.self="close">
          <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl">
            <div class="mb-4 flex items-start justify-between gap-2">
              <div>
                <h2 class="text-lg font-semibold text-slate-900">{{ label }}</h2>
                <p v-if="targetName" class="mt-0.5 text-sm text-slate-500">{{ targetName }}</p>
              </div>
              <button type="button" class="rounded-full p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600" @click="close">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
              </button>
            </div>

            <!-- Neprihlásený → výzva na registráciu (správy posielajú len overené účty) -->
            <div v-if="!auth.isAuthenticated" class="space-y-4">
              <div class="rounded-lg bg-blue-50 p-4 text-sm text-blue-900">
                <p class="mb-1 font-semibold">Najprv sa prihláste</p>
                <p>Správy môžu posielať len registrovaní používatelia s overeným e-mailom — chránime tým organizátorov pred spamom.</p>
              </div>
              <div class="flex gap-2">
                <RouterLink :to="{ name: 'login', query: { redirect: route.fullPath } }"
                  class="flex-1 rounded-lg bg-blue-600 px-4 py-2 text-center text-sm font-semibold text-white no-underline hover:bg-blue-700">
                  Prihlásiť sa
                </RouterLink>
                <RouterLink :to="{ name: 'register' }"
                  class="flex-1 rounded-lg border border-blue-600 px-4 py-2 text-center text-sm font-semibold text-blue-600 no-underline hover:bg-blue-50">
                  Registrovať sa
                </RouterLink>
              </div>
            </div>

            <!-- Odoslané -->
            <div v-else-if="sent" class="rounded-lg bg-green-50 p-4 text-sm text-green-800">
              <p class="mb-1 font-semibold">Správa bola odoslaná!</p>
              <p>Odpoveď dorazí na e-mail vášho účtu.</p>
              <button type="button" class="mt-3 text-sm font-medium text-green-700 hover:underline" @click="close">Zavrieť</button>
            </div>

            <!-- Prihlásený → posiela z účtu -->
            <form v-else class="space-y-4" @submit.prevent="submit">
              <div>
                <label class="mb-1 block text-xs font-medium text-slate-600">Vaša správa</label>
                <textarea v-model.trim="body" required rows="4" maxlength="5000"
                  placeholder="Napíšte správu…"
                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
              </div>

              <div class="rounded-lg bg-blue-50 px-3 py-2 text-xs text-blue-800">
                Píšete ako <strong>{{ auth.displayName || 'prihlásený používateľ' }}</strong>. Odpoveď dorazí na e-mail vášho účtu.
              </div>

              <p v-if="error" class="text-sm text-red-600">{{ error }}</p>

              <button type="submit" :disabled="loading"
                class="w-full rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60">
                {{ loading ? 'Odosielam…' : 'Odoslať správu' }}
              </button>
            </form>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useRoute } from 'vue-router'
import { sendMessage, type MessageTargetType } from '@/api/messages'
import { useWindowKeydown } from '@/composables/useWindowKeydown'
import { useAuthStore } from '@/stores/auth'

const props = withDefaults(defineProps<{
  /** Typ cieľa — 'event' | 'venue' | 'canal' (musí byť vo whitelist na backende). */
  targetType: MessageTargetType
  targetId: number
  /** Názov cieľa zobrazený v hlavičke modálu (nepovinné). */
  targetName?: string
  /** Text tlačidla — dá sa prepísať (napr. „Kontaktovať organizátora"). */
  label?: string
}>(), {
  targetName: '',
  label: 'Poslať správu',
})

const auth = useAuthStore()
const route = useRoute()

const open = ref(false)
const loading = ref(false)
const sent = ref(false)
const error = ref<string | null>(null)
const body = ref('')

function close() {
  open.value = false
  // Po zatvorení resetujeme, aby ďalšie otvorenie začínalo načisto.
  if (sent.value) {
    sent.value = false
    body.value = ''
  }
  error.value = null
}

// Zatvorenie Escapom. Musí ísť cez window listener — vo Vue neexistuje
// modifikátor `.window`, takže pôvodné @keydown.esc.window nič nerobilo.
useWindowKeydown((event) => {
  if (event.key === 'Escape' && open.value) {
    close()
  }
})

async function submit() {
  loading.value = true
  error.value = null
  try {
    await sendMessage({
      target_type: props.targetType,
      target_id: props.targetId,
      body: body.value,
    })
    sent.value = true
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    error.value = err.response?.data?.message ?? 'Správu sa nepodarilo odoslať.'
  } finally {
    loading.value = false
  }
}
</script>
