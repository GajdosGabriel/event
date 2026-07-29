<template>
  <div class="mx-auto my-10 w-full max-w-lg px-4">
    <p v-if="loading" class="text-slate-600">Načítavam pozvánku…</p>

    <div v-else-if="notFound" class="show-card text-center">
      <h1 class="text-xl font-bold text-slate-900">Pozvánka sa nenašla</h1>
      <p class="mt-2 text-sm text-slate-600">Odkaz je neplatný alebo už bol zrušený.</p>
      <RouterLink to="/" class="mt-4 inline-block text-sm text-blue-700">← Späť na úvod</RouterLink>
    </div>

    <div v-else-if="invitation" class="show-card">
      <h1 class="text-xl font-bold text-slate-900">Pozvánka do tímu</h1>
      <p class="mt-2 text-slate-700">
        <template v-if="invitation.invitedBy"><strong>{{ invitation.invitedBy }}</strong> vás pozýva</template>
        <template v-else>Boli ste pozvaný(á)</template>
        do tímu kanála <strong>{{ invitation.canalName }}</strong>.
      </p>

      <dl class="mt-4 grid gap-2 rounded-lg bg-slate-50 p-4 text-sm">
        <div class="flex justify-between gap-2">
          <dt class="text-slate-500">Rola</dt>
          <dd class="font-medium text-slate-900">{{ invitation.roleLabel }}</dd>
        </div>
        <div class="flex justify-between gap-2">
          <dt class="text-slate-500">Adresa pozvánky</dt>
          <dd class="font-medium text-slate-900">{{ invitation.email }}</dd>
        </div>
        <div v-if="invitation.expiresAt" class="flex justify-between gap-2">
          <dt class="text-slate-500">Platí do</dt>
          <dd class="font-medium text-slate-900">{{ formatDate(invitation.expiresAt) }}</dd>
        </div>
      </dl>

      <p class="mt-4 text-sm text-slate-600">{{ roleNote }}</p>

      <!-- Už prijaté -->
      <div v-if="accepted" class="mt-5 rounded-lg bg-green-50 p-4 text-sm text-green-800">
        <p class="font-semibold">Ste v tíme!</p>
        <p class="mt-1">Kanál <strong>{{ invitation.canalName }}</strong> nájdete v prepínači kanálov v dashboarde.</p>
        <RouterLink to="/dashboard" class="mt-3 inline-block font-medium text-green-700">Prejsť do dashboardu →</RouterLink>
      </div>

      <!-- Neplatná pozvánka -->
      <div v-else-if="invitation.status !== 'pending'" class="mt-5 rounded-lg bg-amber-50 p-4 text-sm text-amber-900">
        <p v-if="invitation.status === 'expired'">Platnosť pozvánky vypršala. Požiadajte o novú.</p>
        <p v-else-if="invitation.status === 'revoked'">Pozvánka bola zrušená.</p>
        <p v-else>Pozvánka už bola prijatá.</p>
      </div>

      <!-- Neprihlásený -->
      <div v-else-if="!auth.isAuthenticated" class="mt-5 space-y-3">
        <div class="rounded-lg bg-blue-50 p-4 text-sm text-blue-900">
          Pozvánku prijmete po prihlásení účtom s adresou <strong>{{ invitation.email }}</strong>.
          Ak účet ešte nemáte, zaregistrujte sa na túto adresu — po overení e-mailu
          sa sem vráťte odkazom z pozvánky.
        </div>
        <div class="flex gap-2">
          <RouterLink :to="{ name: 'login', query: { redirect: route.fullPath } }"
            class="flex-1 rounded-lg bg-blue-600 px-4 py-2 text-center text-sm font-semibold text-white no-underline hover:bg-blue-700">
            Prihlásiť sa
          </RouterLink>
          <RouterLink :to="{ name: 'register', query: { email: invitation.email } }"
            class="flex-1 rounded-lg border border-blue-600 px-4 py-2 text-center text-sm font-semibold text-blue-600 no-underline hover:bg-blue-50">
            Registrovať sa
          </RouterLink>
        </div>
      </div>

      <!-- Prihlásený inou adresou -->
      <div v-else-if="!invitation.emailMatches" class="mt-5 rounded-lg bg-amber-50 p-4 text-sm text-amber-900">
        <p class="font-semibold">Pozvánka patrí inému účtu</p>
        <p class="mt-1">
          Je určená pre adresu <strong>{{ invitation.email }}</strong>. Odhláste sa a prihláste účtom s touto adresou.
        </p>
      </div>

      <!-- Prihlásený správnou adresou -->
      <div v-else class="mt-5">
        <p v-if="error" class="mb-2 text-sm text-red-600">{{ error }}</p>
        <button type="button" :disabled="busy"
          class="w-full rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60"
          @click="accept">
          {{ busy ? 'Prijímam…' : 'Prijať pozvánku' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { showInvitation, acceptInvitation, type CanalInvitationDetail } from '@/api/canalTeam'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const auth = useAuthStore()

const invitation = ref<CanalInvitationDetail | null>(null)
const loading = ref(true)
const notFound = ref(false)
const busy = ref(false)
const accepted = ref(false)
const error = ref<string | null>(null)

const token = computed(() => String(route.params.token ?? ''))

const ROLE_NOTES: Record<string, string> = {
  owner: 'Ako vlastník budete môcť spravovať kanál, jeho podujatia aj tím.',
  editor: 'Ako editor budete môcť vytvárať a upravovať podujatia, miesta a lístky.',
  checkin: 'Ako obsluha vstupu budete môcť načítavať QR kódy a odbavovať príchody.',
}
const roleNote = computed(() => ROLE_NOTES[invitation.value?.role ?? ''] ?? '')

function formatDate(value: string): string {
  return new Date(value).toLocaleDateString('sk-SK', { day: 'numeric', month: 'numeric', year: 'numeric' })
}

async function accept() {
  busy.value = true
  error.value = null
  try {
    await acceptInvitation(token.value)
    accepted.value = true
    // Aktualizuje prepínač kanálov v hlavičke — pribudol nový.
    await auth.fetchIdentity()
  } catch (e: unknown) {
    const res = (e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }).response
    error.value = Object.values(res?.data?.errors ?? {})[0]?.[0]
      ?? res?.data?.message
      ?? 'Pozvánku sa nepodarilo prijať.'
  } finally {
    busy.value = false
  }
}

onMounted(async () => {
  // Identitu potrebujeme skôr, než sa rozhodne, čo pozvanému ponúknuť —
  // `email_matches` počíta backend z prihláseného účtu.
  if (auth.isAuthenticated && !auth.identity) {
    await auth.fetchIdentity()
  }

  try {
    invitation.value = await showInvitation(token.value)
    document.title = 'Pozvánka do tímu'
  } catch {
    notFound.value = true
  } finally {
    loading.value = false
  }
})
</script>
