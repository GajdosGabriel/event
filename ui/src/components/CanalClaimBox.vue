<template>
  <!-- „Spravujete tento kanál?" — len pri kanáli, ktorý nikto nespravuje
       (importovaný profil). Server rozhodol cez `claimable`. -->
  <div class="rounded-2xl border border-blue-100 bg-blue-50/60 p-5">
    <p class="text-sm font-semibold text-slate-900">{{ t('canalClaim.boxTitle') }}</p>
    <p class="mt-1 text-xs text-slate-600">{{ t('canalClaim.boxLead') }}</p>

    <p v-if="sent" class="mt-3 rounded-lg bg-green-50 p-3 text-sm text-green-800">{{ sent }}</p>

    <RouterLink v-else-if="!auth.isAuthenticated" :to="{ name: 'login', query: { redirect: route.fullPath } }"
      class="mt-3 inline-block rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white no-underline hover:bg-blue-700">
      {{ t('canalClaim.login') }}
    </RouterLink>

    <button v-else-if="!open" type="button"
      class="mt-3 rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700"
      @click="open = true">
      {{ t('canalClaim.start') }}
    </button>

    <form v-else class="mt-3 grid gap-3" @submit.prevent="submit">
      <fieldset class="grid gap-2">
        <label v-if="claimContact" class="flex cursor-pointer gap-2 rounded-lg border border-slate-200 bg-white p-3 text-sm">
          <input v-model="method" type="radio" value="contact_email" class="mt-0.5" />
          <span>
            <span class="block font-medium text-slate-900">{{ t('canalClaim.methodContact') }}</span>
            <span class="block text-xs text-slate-500">{{ t('canalClaim.methodContactHint') }}</span>
          </span>
        </label>
        <label class="flex cursor-pointer gap-2 rounded-lg border border-slate-200 bg-white p-3 text-sm">
          <input v-model="method" type="radio" value="admin_review" class="mt-0.5" />
          <span>
            <span class="block font-medium text-slate-900">{{ t('canalClaim.methodAdmin') }}</span>
            <span class="block text-xs text-slate-500">{{ t('canalClaim.methodAdminHint') }}</span>
          </span>
        </label>
      </fieldset>

      <label class="grid gap-1 text-xs font-medium text-slate-600">
        {{ method === 'admin_review' ? t('canalClaim.messageRequired') : t('canalClaim.messageLabel') }}
        <textarea v-model="message" rows="3" maxlength="2000" :required="method === 'admin_review'"
          class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-normal focus:border-blue-500 focus:outline-none" />
      </label>

      <p v-if="error" class="text-sm text-red-600">{{ error }}</p>

      <div class="flex gap-2">
        <button type="submit" :disabled="busy"
          class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60">
          {{ busy ? t('canalClaim.submitting') : t('canalClaim.submit') }}
        </button>
        <button type="button" class="rounded-lg px-3 py-2 text-sm text-slate-600 hover:bg-white" @click="open = false">
          {{ t('canalClaim.cancel') }}
        </button>
      </div>
    </form>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useRoute } from 'vue-router'
import { requestCanalClaim } from '@/api/canalClaims'
import { useAuthStore } from '@/stores/auth'
import { t } from '@/i18n'

const props = defineProps<{ canalId: number; claimContact: boolean }>()

const auth = useAuthStore()
const route = useRoute()

const open = ref(false)
const busy = ref(false)
const error = ref<string | null>(null)
const sent = ref<string | null>(null)
const message = ref('')
// Overenie cez kontakt je rýchlejšie — ponúkame ho prvé, keď existuje.
const method = ref<'contact_email' | 'admin_review'>(props.claimContact ? 'contact_email' : 'admin_review')

async function submit() {
  busy.value = true
  error.value = null
  try {
    await requestCanalClaim(props.canalId, method.value, message.value)
    sent.value = method.value === 'contact_email' ? t('canalClaim.sentContact') : t('canalClaim.sentAdmin')
  } catch (e: unknown) {
    const res = (e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }).response
    error.value = Object.values(res?.data?.errors ?? {})[0]?.[0] ?? res?.data?.message ?? t('canalClaim.failed')
  } finally {
    busy.value = false
  }
}
</script>
