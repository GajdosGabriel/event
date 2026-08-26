<template>
  <div id="team" ref="rootEl" class="show-card">
    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
      <h2 class="text-base font-semibold text-slate-800">{{ t('team.title') }}</h2>
      <span v-if="team" class="text-xs text-slate-500">
        {{ plural('team.counts.members', team.members.length) }}
      </span>
    </div>

    <p v-if="loading" class="text-sm text-slate-500">{{ t('team.loading') }}</p>
    <p v-else-if="loadError" class="text-sm text-red-600">{{ t('team.loadFailed') }}</p>

    <template v-else-if="team">
      <!-- Členovia -->
      <ul class="grid gap-1.5">
        <li v-for="m in team.members" :key="m.id"
          class="flex flex-wrap items-center gap-2 rounded-lg border border-slate-100 bg-slate-50 px-3 py-2">
          <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-semibold text-white"
            :class="avatarColor(m.name)">{{ initials(m.name) }}</span>

          <!-- E-mail tu nie je zámerne: kto pozvánku prijal, má v tíme meno a to
               ho identifikuje. Adresa je vidieť len dovtedy, kým je z člena ešte
               len pozvánka (zoznam nižšie) — tam meno zatiaľ neexistuje. -->
          <span class="min-w-0 flex-1 truncate text-sm font-medium text-slate-900">
            {{ m.name }}
            <span v-if="m.isSelf" class="text-xs font-normal text-slate-500">{{ t('team.self') }}</span>
          </span>

          <!-- Vlastnú rolu si meniť nemožno — kanál by ostal bez správcu. -->
          <select v-if="canManage && !m.isSelf" :value="m.role" :disabled="busy"
            class="shrink-0 rounded-lg border border-slate-300 px-2 py-1 text-xs focus:border-blue-500 focus:outline-none"
            @change="changeRole(m, ($event.target as HTMLSelectElement).value as CanalRole)">
            <option v-for="r in team.roles" :key="r.value" :value="r.value">{{ r.label }}</option>
          </select>
          <span v-else class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium"
            :class="roleClass(m.role)">{{ m.roleLabel }}</span>

          <button v-if="canManage && !m.isSelf" type="button" :disabled="busy"
            class="shrink-0 rounded-lg px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50 disabled:opacity-50"
            @click="remove(m)">
            {{ t('team.remove') }}
          </button>
        </li>
      </ul>

      <!-- Nevybavené pozvánky -->
      <div v-if="team.invitations.length" class="mt-4">
        <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">{{ t('team.pending') }}</h3>
        <ul class="grid gap-1.5">
          <li v-for="i in team.invitations" :key="i.id"
            class="flex flex-wrap items-center gap-2 rounded-lg border border-dashed border-amber-200 bg-amber-50 px-3 py-2">
            <span class="min-w-0 flex-1">
              <span class="block truncate text-sm font-medium text-slate-900">{{ i.email }}</span>
              <span class="block text-xs text-slate-500">
                {{ i.roleLabel }}
                <template v-if="i.expiresAt"> · {{ t('team.expires', { date: formatDate(i.expiresAt) }) }}</template>
              </span>
            </span>
            <template v-if="canManage">
              <button type="button" :disabled="busy"
                class="shrink-0 rounded-lg px-2 py-1 text-xs font-medium text-blue-700 hover:bg-blue-100 disabled:opacity-50"
                @click="resend(i)">
                {{ t('team.resend') }}
              </button>
              <button type="button" :disabled="busy"
                class="shrink-0 rounded-lg px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-100 disabled:opacity-50"
                @click="cancelInvite(i)">
                {{ t('team.cancel') }}
              </button>
            </template>
          </li>
        </ul>
      </div>

      <!-- Detail kanála tím len ukazuje; mení sa tam, kde sa mení všetko
           ostatné — v úprave kanála. -->
      <RouterLink v-if="readonly && team.canManage && manageTo" :to="manageTo"
        class="mt-4 inline-block text-sm font-medium text-blue-700 no-underline hover:underline">
        {{ t('team.manage') }} →
      </RouterLink>

      <!-- Pozvanie -->
      <form v-if="canManage" class="mt-4 border-t border-slate-100 pt-4" @submit.prevent="invite">
        <label class="mb-1 block text-xs font-medium text-slate-600">{{ t('team.inviteLabel') }}</label>
        <div class="flex flex-wrap gap-2">
          <FormField v-model="inviteEmail" type="email" required trim :placeholder="t('team.invitePlaceholder')"
            class="min-w-[12rem] flex-1" />
          <FormField v-model="inviteRole" type="select" class="w-auto">
            <option v-for="r in team.roles" :key="r.value" :value="r.value">{{ r.label }}</option>
          </FormField>
          <button type="submit" :disabled="busy"
            class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60">
            {{ busy ? t('team.inviting') : t('team.invite') }}
          </button>
        </div>
        <p v-if="error" class="mt-2 text-sm text-red-600">{{ error }}</p>
        <p class="mt-2 text-xs text-slate-500">
          {{ t('team.inviteHint') }}
        </p>

        <!-- Čo ktorá rola smie. Popisky rolí posiela server (`team.roles`),
             vysvetlivky sú preklady — sú vedľa výberu, lebo práve tu sa rola
             vyberá a nikde inde sa nedá dočítať, čo znamená. -->
        <dl class="mt-2 grid gap-1 text-xs text-slate-500">
          <div v-for="r in team.roles" :key="r.value">
            <dt class="inline font-medium text-slate-600">{{ r.label }}</dt>
            <dd class="inline"> — {{ roleHint(r.value) }}</dd>
          </div>
        </dl>
      </form>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import {
  fetchCanalTeam,
  inviteCanalMember,
  updateCanalMemberRole,
  removeCanalMember,
  resendCanalInvitation,
  cancelCanalInvitation,
  type CanalRole,
  type CanalTeam,
  type CanalTeamInvitation,
  type CanalTeamMember,
} from '@/api/canalTeam'
import { avatarColor, initials } from '@/utils/userDisplay'
import { currentLocale, t, plural, type MessageKey } from '@/i18n'
import { useToast } from '@/composables/useToast'
import { provideFormValidation } from '@/composables/useFormValidation'
import FormField from '@/components/FormField.vue'

/**
 * `readonly` = panel len ukazuje, kto je v tíme (detail kanála). Ovládanie
 * býva v úprave kanála, kam vedie odkaz `manageTo`.
 */
const props = defineProps<{ canalId: number; readonly?: boolean; manageTo?: string }>()

const route = useRoute()
const toast = useToast()
const rootEl = ref<HTMLElement | null>(null)
const validation = provideFormValidation()

const team = ref<CanalTeam | null>(null)
const loading = ref(false)
const loadError = ref(false)
const busy = ref(false)
const error = ref<string | null>(null)
const inviteEmail = ref('')
const inviteRole = ref<CanalRole>('editor')

/**
 * Právo od servera aj režim panela naraz — inak by sa to pýtalo v každom v-if.
 */
const canManage = computed(() => Boolean(team.value?.canManage) && !props.readonly)

/**
 * Vysvetlivka k role. Zoznam rolí prichádza zo servera, takže kľúč sa skladá
 * až za behu — rola bez prekladu ostane bez vysvetlenia, nie s kľúčom v texte.
 */
function roleHint(role: string): string {
  const key = `team.roleHints.${role}` as MessageKey
  const hint = t(key)

  return hint === key ? '' : hint
}

function roleClass(role: CanalRole): string {
  switch (role) {
    case 'owner': return 'bg-amber-50 text-amber-700'
    case 'editor': return 'bg-sky-50 text-sky-700'
    default: return 'bg-slate-100 text-slate-600'
  }
}

function formatDate(value: string): string {
  return new Date(value).toLocaleDateString(currentLocale(), { day: 'numeric', month: 'numeric', year: 'numeric' })
}

/** Validačnú hlášku z API (422) ukážeme presne tak, ako prišla. */
function messageFrom(e: unknown, fallback: string): string {
  const res = (e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }).response
  const firstError = Object.values(res?.data?.errors ?? {})[0]?.[0]
  return firstError ?? res?.data?.message ?? fallback
}

async function run(action: () => Promise<CanalTeam>, successMessage?: string) {
  busy.value = true
  error.value = null
  try {
    team.value = await action()
    if (successMessage) toast.success(successMessage)
    return true
  } catch (e: unknown) {
    error.value = messageFrom(e, t('team.actionFailed'))
    toast.error(error.value)
    return false
  } finally {
    busy.value = false
  }
}

async function invite() {
  validation.markValidated()

  const ok = await run(
    () => inviteCanalMember(props.canalId, inviteEmail.value, inviteRole.value),
    t('team.invited'),
  )

  // Po odoslaní je pole zámerne zase „nevalidované" — prázdne políčko pre
  // ďalšiu pozvánku nie je chyba.
  if (ok) {
    inviteEmail.value = ''
    validation.reset()
  }
}

async function changeRole(member: CanalTeamMember, role: CanalRole) {
  await run(() => updateCanalMemberRole(props.canalId, member.id, role), t('team.roleChanged'))
  // Pri neúspechu vrátime <select> na stav zo servera.
  if (error.value) team.value = await fetchCanalTeam(props.canalId)
}

async function remove(member: CanalTeamMember) {
  if (!confirm(t('team.removeConfirm', { name: member.name }))) return
  await run(() => removeCanalMember(props.canalId, member.id), t('team.removed'))
}

async function resend(invitation: CanalTeamInvitation) {
  await run(() => resendCanalInvitation(props.canalId, invitation.id), t('team.resent'))
}

async function cancelInvite(invitation: CanalTeamInvitation) {
  if (!confirm(t('team.cancelConfirm', { email: invitation.email }))) return
  await run(() => cancelCanalInvitation(props.canalId, invitation.id), t('team.cancelled'))
}

onMounted(async () => {
  // Odkaz „Kto môže skenovať“ zo skenera mieri sem cez #team. Doskrolovať si
  // musíme sami — scrollBehavior routera beží ešte pred načítaním kanála, keď
  // panel v DOM neexistuje a kotva nemá kam skočiť.
  if (route.hash === '#team') rootEl.value?.scrollIntoView({ behavior: 'smooth', block: 'start' })

  loading.value = true
  try {
    team.value = await fetchCanalTeam(props.canalId)
  } catch {
    loadError.value = true
  } finally {
    loading.value = false
  }
})
</script>
