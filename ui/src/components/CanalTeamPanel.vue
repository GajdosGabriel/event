<template>
  <div class="show-card">
    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
      <h2 class="text-base font-semibold text-slate-800">Tím kanála</h2>
      <span v-if="team" class="text-xs text-slate-500">
        {{ team.members.length }} {{ pluralMembers(team.members.length) }}
      </span>
    </div>

    <p v-if="loading" class="text-sm text-slate-500">Načítavam…</p>
    <p v-else-if="loadError" class="text-sm text-red-600">Tím sa nepodarilo načítať.</p>

    <template v-else-if="team">
      <!-- Členovia -->
      <ul class="grid gap-1.5">
        <li v-for="m in team.members" :key="m.id"
          class="flex flex-wrap items-center gap-2 rounded-lg border border-slate-100 bg-slate-50 px-3 py-2">
          <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-semibold text-white"
            :class="avatarColor(m.name)">{{ initials(m.name) }}</span>

          <span class="min-w-0 flex-1">
            <span class="block truncate text-sm font-medium text-slate-900">
              {{ m.name }}
              <span v-if="m.isSelf" class="text-xs font-normal text-slate-500">(vy)</span>
            </span>
            <span v-if="m.email" class="block truncate text-xs text-slate-500">{{ m.email }}</span>
          </span>

          <!-- Vlastnú rolu si meniť nemožno — kanál by ostal bez správcu. -->
          <select v-if="team.canManage && !m.isSelf" :value="m.role" :disabled="busy"
            class="shrink-0 rounded-lg border border-slate-300 px-2 py-1 text-xs focus:border-blue-500 focus:outline-none"
            @change="changeRole(m, ($event.target as HTMLSelectElement).value as CanalRole)">
            <option v-for="r in team.roles" :key="r.value" :value="r.value">{{ r.label }}</option>
          </select>
          <span v-else class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium"
            :class="roleClass(m.role)">{{ m.roleLabel }}</span>

          <button v-if="team.canManage && !m.isSelf" type="button" :disabled="busy"
            class="shrink-0 rounded-lg px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50 disabled:opacity-50"
            @click="remove(m)">
            Odobrať
          </button>
        </li>
      </ul>

      <!-- Nevybavené pozvánky -->
      <div v-if="team.invitations.length" class="mt-4">
        <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Čaká na prijatie</h3>
        <ul class="grid gap-1.5">
          <li v-for="i in team.invitations" :key="i.id"
            class="flex flex-wrap items-center gap-2 rounded-lg border border-dashed border-amber-200 bg-amber-50 px-3 py-2">
            <span class="min-w-0 flex-1">
              <span class="block truncate text-sm font-medium text-slate-900">{{ i.email }}</span>
              <span class="block text-xs text-slate-500">
                {{ i.roleLabel }}
                <template v-if="i.expiresAt"> · platí do {{ formatDate(i.expiresAt) }}</template>
              </span>
            </span>
            <button type="button" :disabled="busy"
              class="shrink-0 rounded-lg px-2 py-1 text-xs font-medium text-blue-700 hover:bg-blue-100 disabled:opacity-50"
              @click="resend(i)">
              Poslať znova
            </button>
            <button type="button" :disabled="busy"
              class="shrink-0 rounded-lg px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-100 disabled:opacity-50"
              @click="cancelInvite(i)">
              Zrušiť
            </button>
          </li>
        </ul>
      </div>

      <!-- Pozvanie -->
      <form v-if="team.canManage" class="mt-4 border-t border-slate-100 pt-4" @submit.prevent="invite">
        <label class="mb-1 block text-xs font-medium text-slate-600">Pozvať do tímu</label>
        <div class="flex flex-wrap gap-2">
          <input v-model.trim="inviteEmail" type="email" required placeholder="meno@divadlo.sk"
            class="min-w-[12rem] flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
          <select v-model="inviteRole"
            class="rounded-lg border border-slate-300 px-2 py-2 text-sm focus:border-blue-500 focus:outline-none">
            <option v-for="r in team.roles" :key="r.value" :value="r.value">{{ r.label }}</option>
          </select>
          <button type="submit" :disabled="busy"
            class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60">
            {{ busy ? 'Odosielam…' : 'Poslať pozvánku' }}
          </button>
        </div>
        <p v-if="error" class="mt-2 text-sm text-red-600">{{ error }}</p>
        <p class="mt-2 text-xs text-slate-500">
          Pozvánka príde e-mailom. Prijať ju musí účet s tou istou adresou.
        </p>
      </form>
    </template>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
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
import { useToast } from '@/composables/useToast'

const props = defineProps<{ canalId: number }>()

const toast = useToast()

const team = ref<CanalTeam | null>(null)
const loading = ref(false)
const loadError = ref(false)
const busy = ref(false)
const error = ref<string | null>(null)
const inviteEmail = ref('')
const inviteRole = ref<CanalRole>('editor')

function roleClass(role: CanalRole): string {
  switch (role) {
    case 'owner': return 'bg-amber-50 text-amber-700'
    case 'editor': return 'bg-sky-50 text-sky-700'
    default: return 'bg-slate-100 text-slate-600'
  }
}

function pluralMembers(n: number): string {
  if (n === 1) return 'člen'
  if (n >= 2 && n <= 4) return 'členovia'
  return 'členov'
}

function formatDate(value: string): string {
  return new Date(value).toLocaleDateString('sk-SK', { day: 'numeric', month: 'numeric', year: 'numeric' })
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
    error.value = messageFrom(e, 'Akciu sa nepodarilo dokončiť.')
    toast.error(error.value)
    return false
  } finally {
    busy.value = false
  }
}

async function invite() {
  const ok = await run(
    () => inviteCanalMember(props.canalId, inviteEmail.value, inviteRole.value),
    'Pozvánka odoslaná.',
  )
  if (ok) inviteEmail.value = ''
}

async function changeRole(member: CanalTeamMember, role: CanalRole) {
  await run(() => updateCanalMemberRole(props.canalId, member.id, role), 'Rola zmenená.')
  // Pri neúspechu vrátime <select> na stav zo servera.
  if (error.value) team.value = await fetchCanalTeam(props.canalId)
}

async function remove(member: CanalTeamMember) {
  if (!confirm(`Naozaj odobrať ${member.name} z tímu?`)) return
  await run(() => removeCanalMember(props.canalId, member.id), 'Člen odobratý.')
}

async function resend(invitation: CanalTeamInvitation) {
  await run(() => resendCanalInvitation(props.canalId, invitation.id), 'Pozvánka odoslaná znova.')
}

async function cancelInvite(invitation: CanalTeamInvitation) {
  if (!confirm(`Zrušiť pozvánku pre ${invitation.email}?`)) return
  await run(() => cancelCanalInvitation(props.canalId, invitation.id), 'Pozvánka zrušená.')
}

onMounted(async () => {
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
