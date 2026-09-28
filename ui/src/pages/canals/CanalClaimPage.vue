<template>
  <!-- Odkazy z e-mailov procesu prevzatia (CanalClaimNotice / CanalOwnershipChanged).
       `confirm` — kontaktná schránka posudzuje žiadosť, `contest` — napáda
       dokončené prevzatie. Autorizáciou je token, prihlásenie netreba. -->
  <div class="mx-auto max-w-lg px-4 py-10">
    <p v-if="loading" class="text-slate-600">{{ t('canalClaim.loading') }}</p>
    <p v-else-if="!claim" class="rounded-lg bg-amber-50 p-4 text-sm text-amber-900">{{ t('canalClaim.loadFailed') }}</p>

    <div v-else class="rounded-2xl border border-slate-200 bg-white p-6">
      <h1 class="text-lg font-semibold text-slate-900">
        {{ mode === 'confirm' ? t('canalClaim.confirmTitle') : t('canalClaim.contestTitle') }}
      </h1>
      <p class="mt-2 text-sm text-slate-700">
        {{ t(mode === 'confirm' ? 'canalClaim.confirmLead' : 'canalClaim.contestLead', {
          name: claim.requesterName ?? '—', email: claim.requesterEmail ?? '—', canal: claim.canalName ?? '—',
        }) }}
      </p>

      <blockquote v-if="claim.message" class="mt-3 rounded-lg bg-slate-50 p-3 text-sm text-slate-700">
        <span class="block text-xs font-medium text-slate-500">{{ t('canalClaim.requesterMessage') }}</span>
        {{ claim.message }}
      </blockquote>

      <p v-if="done" class="mt-5 rounded-lg bg-green-50 p-4 text-sm text-green-800">{{ done }}</p>

      <template v-else-if="mode === 'confirm'">
        <p v-if="claim.status !== 'pending'" class="mt-5 rounded-lg bg-amber-50 p-4 text-sm text-amber-900">{{ t('canalClaim.notPending') }}</p>
        <template v-else>
          <p class="mt-4 text-xs text-slate-500">{{ t('canalClaim.confirmNote') }}</p>
          <p v-if="error" class="mt-2 text-sm text-red-600">{{ error }}</p>
          <button type="button" :disabled="busy"
            class="mt-3 w-full rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60"
            @click="run">
            {{ busy ? t('canalClaim.confirming') : t('canalClaim.confirm') }}
          </button>
        </template>
      </template>

      <template v-else>
        <p v-if="!claim.contestable" class="mt-5 rounded-lg bg-amber-50 p-4 text-sm text-amber-900">{{ t('canalClaim.notContestable') }}</p>
        <form v-else class="mt-4 grid gap-3" @submit.prevent="run">
          <label class="grid gap-1 text-xs font-medium text-slate-600">
            {{ t('canalClaim.contestNoteLabel') }}
            <textarea v-model="note" rows="3" maxlength="2000"
              class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-normal focus:border-blue-500 focus:outline-none" />
          </label>
          <p v-if="error" class="text-sm text-red-600">{{ error }}</p>
          <button type="submit" :disabled="busy"
            class="w-full rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 disabled:opacity-60">
            {{ busy ? t('canalClaim.contesting') : t('canalClaim.contest') }}
          </button>
        </form>
      </template>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import {
  showCanalClaim,
  confirmCanalClaim,
  showCanalClaimContest,
  contestCanalClaim,
  type CanalClaimDetail,
} from '@/api/canalClaims'
import { t } from '@/i18n'

const props = defineProps<{ mode: 'confirm' | 'contest' }>()

const route = useRoute()
const token = String(route.params.token ?? '')

const claim = ref<CanalClaimDetail | null>(null)
const loading = ref(true)
const busy = ref(false)
const error = ref<string | null>(null)
const done = ref<string | null>(null)
const note = ref('')

async function run() {
  busy.value = true
  error.value = null
  try {
    if (props.mode === 'confirm') {
      await confirmCanalClaim(token)
      done.value = t('canalClaim.confirmed', { canal: claim.value?.canalName ?? '' })
    } else {
      await contestCanalClaim(token, note.value)
      done.value = t('canalClaim.contested')
    }
  } catch (e: unknown) {
    const res = (e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }).response
    error.value = Object.values(res?.data?.errors ?? {})[0]?.[0] ?? res?.data?.message ?? t('canalClaim.failed')
  } finally {
    busy.value = false
  }
}

onMounted(async () => {
  try {
    claim.value = props.mode === 'confirm' ? await showCanalClaim(token) : await showCanalClaimContest(token)
  } catch {
    claim.value = null
  } finally {
    loading.value = false
  }
})
</script>
