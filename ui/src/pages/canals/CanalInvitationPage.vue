<template>
  <div class="mx-auto my-10 w-full max-w-lg px-4">
    <p v-if="loading" class="text-slate-600">{{ t('invitation.loading') }}</p>

    <div v-else-if="notFound" class="show-card text-center">
      <h1 class="text-xl font-bold text-slate-900">{{ t('invitation.notFoundTitle') }}</h1>
      <p class="mt-2 text-sm text-slate-600">{{ t('invitation.notFoundLead') }}</p>
      <RouterLink to="/" class="mt-4 inline-block text-sm text-blue-700">{{ t('invitation.home') }}</RouterLink>
    </div>

    <div v-else-if="invitation" class="show-card">
      <h1 class="text-xl font-bold text-slate-900">{{ t('invitation.title') }}</h1>
      <p class="mt-2 text-slate-700">
        <template v-if="invitation.invitedBy"><strong>{{ invitation.invitedBy }}</strong> {{ t('invitation.invitesYou') }}</template>
        <template v-else>{{ t('invitation.invited') }}</template>
        {{ t('invitation.toTeam') }} <strong>{{ invitation.canalName }}</strong>.
      </p>

      <dl class="mt-4 grid gap-2 rounded-lg bg-slate-50 p-4 text-sm">
        <div class="flex justify-between gap-2">
          <dt class="text-slate-500">{{ t('invitation.role') }}</dt>
          <dd class="font-medium text-slate-900">{{ invitation.roleLabel }}</dd>
        </div>
        <div class="flex justify-between gap-2">
          <dt class="text-slate-500">{{ t('invitation.email') }}</dt>
          <dd class="font-medium text-slate-900">{{ invitation.email }}</dd>
        </div>
        <div v-if="invitation.expiresAt" class="flex justify-between gap-2">
          <dt class="text-slate-500">{{ t('invitation.expires') }}</dt>
          <dd class="font-medium text-slate-900">{{ formatDate(invitation.expiresAt) }}</dd>
        </div>
      </dl>

      <p class="mt-4 text-sm text-slate-600">{{ roleNote }}</p>

      <!-- Už prijaté -->
      <div v-if="accepted" class="mt-5 rounded-lg bg-green-50 p-4 text-sm text-green-800">
        <p class="font-semibold">{{ t('invitation.acceptedTitle') }}</p>
        <p class="mt-1">
          {{ t('invitation.acceptedLeadBefore') }} <strong>{{ invitation.canalName }}</strong>
          {{ t('invitation.acceptedLeadAfter') }}
        </p>
        <RouterLink to="/dashboard" class="mt-3 inline-block font-medium text-green-700">{{ t('invitation.toDashboard') }}</RouterLink>
      </div>

      <!-- Neplatná pozvánka -->
      <div v-else-if="invitation.status !== 'pending'" class="mt-5 rounded-lg bg-amber-50 p-4 text-sm text-amber-900">
        <p v-if="invitation.status === 'expired'">{{ t('invitation.expired') }}</p>
        <p v-else-if="invitation.status === 'revoked'">{{ t('invitation.revoked') }}</p>
        <p v-else>{{ t('invitation.alreadyAccepted') }}</p>
      </div>

      <!-- Neprihlásený -->
      <div v-else-if="!auth.isAuthenticated" class="mt-5 space-y-3">
        <div class="rounded-lg bg-blue-50 p-4 text-sm text-blue-900">
          {{ t('invitation.signInLeadBefore') }} <strong>{{ invitation.email }}</strong>.
          {{ t('invitation.signInLeadAfter') }}
        </div>
        <div class="flex gap-2">
          <RouterLink :to="{ name: 'login', query: { redirect: route.fullPath } }"
            class="flex-1 rounded-lg bg-blue-600 px-4 py-2 text-center text-sm font-semibold text-white no-underline hover:bg-blue-700">
            {{ t('invitation.login') }}
          </RouterLink>
          <RouterLink :to="{ name: 'register', query: { email: invitation.email } }"
            class="flex-1 rounded-lg border border-blue-600 px-4 py-2 text-center text-sm font-semibold text-blue-600 no-underline hover:bg-blue-50">
            {{ t('invitation.register') }}
          </RouterLink>
        </div>
      </div>

      <!-- Prihlásený inou adresou -->
      <div v-else-if="!invitation.emailMatches" class="mt-5 rounded-lg bg-amber-50 p-4 text-sm text-amber-900">
        <p class="font-semibold">{{ t('invitation.otherAccountTitle') }}</p>
        <p class="mt-1">
          {{ t('invitation.otherAccountLeadBefore') }} <strong>{{ invitation.email }}</strong>.
          {{ t('invitation.otherAccountLeadAfter') }}
        </p>
      </div>

      <!-- Prihlásený správnou adresou -->
      <div v-else class="mt-5">
        <p v-if="error" class="mb-2 text-sm text-red-600">{{ error }}</p>
        <button type="button" :disabled="busy"
          class="w-full rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60"
          @click="accept">
          {{ busy ? t('invitation.accepting') : t('invitation.accept') }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { showInvitation, acceptInvitation, type CanalInvitationDetail } from '@/api/canalTeam'
import { currentLocale, t } from '@/i18n'
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

const ROLES = ['owner', 'editor', 'checkin'] as const

const roleNote = computed(() => {
  const role = invitation.value?.role ?? ''
  return ROLES.includes(role as typeof ROLES[number])
    ? t(`invitation.roleNotes.${role as typeof ROLES[number]}`)
    : ''
})

function formatDate(value: string): string {
  return new Date(value).toLocaleDateString(currentLocale(), { day: 'numeric', month: 'numeric', year: 'numeric' })
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
      ?? t('invitation.acceptFailed')
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
    document.title = t('invitation.title')
  } catch {
    notFound.value = true
  } finally {
    loading.value = false
  }
})
</script>
