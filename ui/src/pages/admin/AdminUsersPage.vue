<template>
  <div class="grid gap-4">
    <div>
      <h1 class="text-2xl font-semibold text-slate-900">Používatelia</h1>
      <p class="mt-0.5 text-sm text-slate-500">
        <template v-if="!loading && !error">
          {{ filteredUsers.length }}{{ filteredUsers.length !== users.length ? ` z ${users.length}` : '' }}
          {{ pluralUsers(filteredUsers.length) }}
        </template>
        <template v-else>&nbsp;</template>
      </p>
    </div>

    <ResourceFilterBar
      v-if="!loading && !error"
      v-model:search="search"
      v-model:status="statusFilter"
      v-model:sort="sort"
      :status-options="statusOptions"
      :sort-options="sortOptions"
    >
      <template #filters>
        <select v-model="roleFilter" class="form-input w-auto" title="Rola">
          <option value="">Všetky role</option>
          <option v-for="role in roles" :key="role.name" :value="role.name">
            {{ role.label ?? roleLabel(role.name) }}
          </option>
        </select>
      </template>
    </ResourceFilterBar>

    <p v-if="loading" class="text-slate-600">Načítavam…</p>
    <p v-else-if="error" class="text-red-600">{{ error }}</p>

    <div v-else class="panel-card overflow-hidden !p-0">
      <div class="overflow-x-auto">
        <table class="w-full border-collapse text-sm">
          <thead>
            <tr class="border-b border-slate-200 bg-slate-50 text-left text-[0.7rem] uppercase tracking-wide text-slate-500">
              <th class="px-4 py-3 font-semibold">Používateľ</th>
              <th class="px-4 py-3 font-semibold">Role</th>
              <th class="px-4 py-3 font-semibold">Stav</th>
              <th class="px-4 py-3 text-center font-semibold">Kanály</th>
              <th class="px-4 py-3 font-semibold">Registrácia</th>
              <th class="px-4 py-3 font-semibold">Aktivita</th>
              <th class="px-4 py-3 text-right font-semibold">Akcie</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="user in filteredUsers" :key="user.id as number"
              class="border-b border-slate-100 transition-colors last:border-0 hover:bg-slate-50/70"
              :class="{ 'bg-red-50/40': user.deleted_at }">
              <!-- User identity -->
              <td class="px-4 py-3">
                <div class="flex items-center gap-3">
                  <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full text-xs font-semibold text-white"
                    :class="user.deleted_at ? 'bg-slate-400' : avatarColor(displayName(user))">
                    {{ initials(displayName(user)) }}
                  </span>
                  <div class="min-w-0">
                    <div class="truncate font-medium text-slate-900">{{ displayName(user) }}</div>
                    <div class="truncate text-xs text-slate-500">{{ user.email || '—' }}</div>
                  </div>
                </div>
              </td>

              <!-- Roles -->
              <td class="px-4 py-3">
                <div v-if="(user.roles as string[])?.length" class="flex flex-wrap gap-1">
                  <span v-for="role in (user.roles as string[])" :key="role"
                    class="rounded-full px-2 py-0.5 text-[0.7rem] font-medium ring-1 ring-inset"
                    :class="roleClass(role)">
                    {{ roleLabel(role) }}
                  </span>
                </div>
                <span v-else class="text-xs text-slate-400">bez roly</span>
              </td>

              <!-- Status -->
              <td class="px-4 py-3">
                <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[0.72rem] font-medium ring-1 ring-inset"
                  :class="statusOf(user).cls">
                  <span class="h-1.5 w-1.5 rounded-full" :class="statusOf(user).dot"></span>
                  {{ statusOf(user).label }}
                </span>
              </td>

              <!-- Canals -->
              <td class="px-4 py-3 text-center">
                <span v-if="Number(user.canals_count) > 0"
                  class="inline-block min-w-[1.5rem] rounded-md bg-slate-100 px-1.5 py-0.5 text-xs font-semibold text-slate-700">
                  {{ user.canals_count }}
                </span>
                <span v-else class="text-slate-300">—</span>
              </td>

              <!-- Registration -->
              <td class="px-4 py-3">
                <div class="flex items-center gap-1.5 text-xs">
                  <span class="text-base leading-none">{{ providerMeta(user.registered_via as string).icon }}</span>
                  <span class="text-slate-600">{{ providerMeta(user.registered_via as string).label }}</span>
                </div>
                <div v-if="user.created_at" class="mt-0.5 text-xs text-slate-400">
                  {{ fmtDate(user.created_at as string) }}
                </div>
              </td>

              <!-- Activity -->
              <td class="px-4 py-3">
                <div class="text-xs text-slate-600" :title="fullDate(user.last_activity ?? user.last_login_at)">
                  {{ relTime(user.last_activity ?? user.last_login_at) }}
                </div>
              </td>

              <!-- Actions -->
              <td class="px-4 py-3">
                <div class="flex justify-end">
                  <RowActions>
                    <template v-if="user.deleted_at">
                      <button class="row-menu-item" @click="restore(user.id as number)">Obnoviť</button>
                    </template>
                    <template v-else>
                      <button class="row-menu-item" @click="openRoleEditor(user)">Upraviť role</button>
                      <template v-if="!isSelf(user)">
                        <button v-if="user.is_blocked" class="row-menu-item" @click="unblock(user)">Odblokovať</button>
                        <button v-else class="row-menu-item" @click="openBlockEditor(user)">Blokovať</button>
                        <button class="row-menu-item row-menu-item-danger" @click="remove(user)">Zmazať</button>
                      </template>
                    </template>
                  </RowActions>
                </div>
              </td>
            </tr>

            <tr v-if="!filteredUsers.length">
              <td colspan="7" class="px-4 py-10 text-center text-sm text-slate-400">
                Žiadni používatelia nezodpovedajú hľadaniu.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div v-if="editingUser" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
      <div class="w-full max-w-sm rounded-2xl border border-slate-200 bg-white p-5 shadow-xl">
        <h2 class="mb-3 text-lg font-semibold">Role pre {{ editingUser.email }}</h2>
        <div class="grid gap-2">
          <label v-for="role in roles" :key="role.name" class="flex items-center gap-2 text-sm">
            <input type="checkbox" :value="role.name" v-model="selectedRoles" />
            {{ role.label ?? role.name }}
          </label>
        </div>
        <div class="mt-4 flex gap-2">
          <button class="btn btn-primary" :disabled="saving" @click="saveRoles">{{ saving ? 'Ukladám…' : 'Uložiť' }}</button>
          <button class="btn btn-secondary" @click="editingUser = null">Zrušiť</button>
        </div>
      </div>
    </div>

    <div v-if="blockingUser" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
      <div class="w-full max-w-sm rounded-2xl border border-slate-200 bg-white p-5 shadow-xl">
        <h2 class="text-lg font-semibold text-slate-900">Blokovať používateľa</h2>
        <p class="mt-1 text-sm text-slate-500">{{ blockingUser.email }}</p>

        <label class="mt-4 grid gap-1 text-sm">
          <span class="font-medium text-slate-700">Dôvod <span class="text-slate-400">(voliteľné)</span></span>
          <textarea v-model="blockReason" rows="2" class="form-textarea" placeholder="napr. porušenie pravidiel"></textarea>
        </label>

        <label class="mt-3 grid gap-1 text-sm">
          <span class="font-medium text-slate-700">Blokovať do <span class="text-slate-400">(voliteľné)</span></span>
          <input v-model="blockUntil" type="datetime-local" class="form-input" />
          <span class="text-xs text-slate-400">Prázdne = blok bez časového obmedzenia.</span>
        </label>

        <div class="mt-4 flex gap-2">
          <button class="btn btn-danger" :disabled="saving" @click="confirmBlock">
            {{ saving ? 'Ukladám…' : 'Blokovať' }}
          </button>
          <button class="btn btn-secondary" @click="blockingUser = null">Zrušiť</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { listUsers, getRoles, updateUserRoles, restoreUser, updateUser, deleteUser } from '@/api/access-control'
import type { AccessRole } from '@/types'
import { useToast } from '@/composables/useToast'
import { useAuthStore } from '@/stores/auth'
import { fmtDate } from '@/utils/dateFormat'
import ResourceFilterBar, { type FilterOption } from '@/components/ResourceFilterBar.vue'
import RowActions from '@/components/RowActions.vue'

const SCOPE = 'admin' as const

const toast = useToast()
const auth = useAuthStore()
const users = ref<Record<string, unknown>[]>([])
const roles = ref<AccessRole[]>([])
const loading = ref(false)
const error = ref<string | null>(null)
const editingUser = ref<Record<string, unknown> | null>(null)
const selectedRoles = ref<string[]>([])
const saving = ref(false)
const search = ref('')
const statusFilter = ref('')
const roleFilter = ref('')
const sort = ref('newest')
const blockingUser = ref<Record<string, unknown> | null>(null)
const blockReason = ref('')
const blockUntil = ref('')

function isSelf(user: Record<string, unknown>): boolean {
  return Number(user.id) === Number(auth.identity?.id)
}

const statusOptions: FilterOption[] = [
  { value: 'active', label: 'Aktívny' },
  { value: 'blocked', label: 'Blokovaný' },
  { value: 'unverified', label: 'Neoverený' },
  { value: 'deleted', label: 'Zmazaný' },
]

const sortOptions: FilterOption[] = [
  { value: 'newest', label: 'Najnovší' },
  { value: 'oldest', label: 'Najstarší' },
  { value: 'activity', label: 'Podľa aktivity' },
  { value: 'name', label: 'Meno A–Z' },
]

const filteredUsers = computed(() => {
  const q = search.value.trim().toLowerCase()

  const list = users.value.filter(u => {
    if (q) {
      const hay = [displayName(u), u.email as string, ...((u.roles as string[]) ?? [])]
        .join(' ').toLowerCase()
      if (!hay.includes(q)) return false
    }
    if (statusFilter.value && statusKey(u) !== statusFilter.value) return false
    if (roleFilter.value && !((u.roles as string[]) ?? []).includes(roleFilter.value)) return false
    return true
  })

  const dir = sort.value === 'oldest' ? 1 : -1
  return [...list].sort((a, b) => {
    if (sort.value === 'name') return displayName(a).localeCompare(displayName(b), 'sk')
    if (sort.value === 'activity') return ts(b.last_activity ?? b.last_login_at) - ts(a.last_activity ?? a.last_login_at)
    return dir * (ts(b.created_at) - ts(a.created_at))
  })
})

function ts(value: unknown): number {
  if (!value) return 0
  const t = new Date(value as string).getTime()
  return Number.isNaN(t) ? 0 : t
}

onMounted(async () => {
  loading.value = true
  try {
    ;[users.value, roles.value] = await Promise.all([listUsers(SCOPE), getRoles(SCOPE)])
  } catch { error.value = 'Nepodarilo sa načítať.' }
  finally { loading.value = false }
})

function displayName(user: Record<string, unknown>): string {
  return (user.display_name as string) || (user.email as string) || 'Neznámy'
}

function initials(name: string): string {
  const parts = name.replace(/[^\p{L}\p{N} ]/gu, '').trim().split(/\s+/).filter(Boolean)
  if (!parts.length) return '?'
  if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase()
  return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase()
}

const AVATAR_COLORS = [
  'bg-rose-500', 'bg-orange-500', 'bg-amber-500', 'bg-emerald-500',
  'bg-teal-500', 'bg-sky-500', 'bg-indigo-500', 'bg-violet-500', 'bg-fuchsia-500',
]
function avatarColor(seed: string): string {
  let h = 0
  for (let i = 0; i < seed.length; i++) h = (h * 31 + seed.charCodeAt(i)) >>> 0
  return AVATAR_COLORS[h % AVATAR_COLORS.length]
}

const ROLE_LABELS: Record<string, string> = {
  'super-admin': 'Super admin',
  'admin': 'Administrátor',
  'canal-owner': 'Vlastník kanálu',
  'editor': 'Editor',
  'moderator': 'Moderátor',
  'user': 'Používateľ',
}
function roleLabel(role: string): string {
  return roles.value.find(r => r.name === role)?.label ?? ROLE_LABELS[role] ?? role
}

function roleClass(role: string): string {
  switch (role) {
    case 'super-admin': return 'bg-purple-50 text-purple-700 ring-purple-200'
    case 'admin':       return 'bg-red-50 text-red-700 ring-red-200'
    case 'canal-owner': return 'bg-amber-50 text-amber-700 ring-amber-200'
    case 'editor':
    case 'moderator':   return 'bg-sky-50 text-sky-700 ring-sky-200'
    default:            return 'bg-slate-100 text-slate-600 ring-slate-200'
  }
}

function statusKey(user: Record<string, unknown>): 'deleted' | 'blocked' | 'unverified' | 'active' {
  if (user.deleted_at) return 'deleted'
  if (user.is_blocked) return 'blocked'
  if (user.email_verified === false) return 'unverified'
  return 'active'
}

const STATUS_META: Record<string, { label: string; cls: string; dot: string }> = {
  deleted:    { label: 'Zmazaný',   cls: 'bg-slate-100 text-slate-500 ring-slate-200', dot: 'bg-slate-400' },
  blocked:    { label: 'Blokovaný', cls: 'bg-red-50 text-red-700 ring-red-200',        dot: 'bg-red-500' },
  unverified: { label: 'Neoverený', cls: 'bg-amber-50 text-amber-700 ring-amber-200',  dot: 'bg-amber-500' },
  active:     { label: 'Aktívny',   cls: 'bg-emerald-50 text-emerald-700 ring-emerald-200', dot: 'bg-emerald-500' },
}

function statusOf(user: Record<string, unknown>): { label: string; cls: string; dot: string } {
  return STATUS_META[statusKey(user)]
}

function providerMeta(via?: string): { icon: string; label: string } {
  switch (via) {
    case 'google':   return { icon: '🟢', label: 'Google' }
    case 'facebook': return { icon: '🔵', label: 'Facebook' }
    case 'email':    return { icon: '✉️', label: 'Email' }
    default:         return { icon: '👤', label: via || 'Priama' }
  }
}

function relTime(value: unknown): string {
  if (!value) return 'nikdy'
  const then = new Date(value as string).getTime()
  if (Number.isNaN(then)) return '—'
  const diff = Date.now() - then
  const min = Math.round(diff / 60000)
  if (min < 1) return 'práve teraz'
  if (min < 60) return `pred ${min} min`
  const hrs = Math.round(min / 60)
  if (hrs < 24) return `pred ${hrs} h`
  const days = Math.round(hrs / 24)
  if (days < 30) return `pred ${days} d`
  return fmtDate(value as string)
}

function fullDate(value: unknown): string {
  if (!value) return ''
  return new Date(value as string).toLocaleString('sk-SK')
}

function pluralUsers(n: number): string {
  if (n === 1) return 'používateľ'
  if (n >= 2 && n <= 4) return 'používatelia'
  return 'používateľov'
}

function openRoleEditor(user: Record<string, unknown>) {
  editingUser.value = user
  selectedRoles.value = [...((user.roles as string[]) ?? [])]
}

async function saveRoles() {
  if (!editingUser.value) return
  saving.value = true
  try {
    await updateUserRoles(editingUser.value.id as number, selectedRoles.value, SCOPE)
    editingUser.value.roles = [...selectedRoles.value]
    toast.success('Role uložené.')
    editingUser.value = null
  } catch { toast.error('Uloženie zlyhalo.') }
  finally { saving.value = false }
}

async function restore(userId: number) {
  try {
    await restoreUser(userId, SCOPE)
    const user = users.value.find(u => u.id === userId)
    if (user) user.deleted_at = null
    toast.success('Používateľ obnovený.')
  } catch { toast.error('Obnova zlyhala.') }
}

function openBlockEditor(user: Record<string, unknown>) {
  blockingUser.value = user
  blockReason.value = ''
  blockUntil.value = ''
}

function applyUpdated(target: Record<string, unknown>, updated: Record<string, unknown>) {
  target.is_blocked = updated.is_blocked
  target.blocked_until = updated.blocked_until
}

async function confirmBlock() {
  if (!blockingUser.value) return
  saving.value = true
  try {
    const updated = await updateUser(blockingUser.value.id as number, {
      blocked: true,
      blocked_reason: blockReason.value.trim() || null,
      blocked_until: blockUntil.value || null,
    }, SCOPE)
    applyUpdated(blockingUser.value, updated)
    toast.success('Používateľ zablokovaný.')
    blockingUser.value = null
  } catch { toast.error('Blokovanie zlyhalo.') }
  finally { saving.value = false }
}

async function unblock(user: Record<string, unknown>) {
  try {
    const updated = await updateUser(user.id as number, { blocked: false }, SCOPE)
    applyUpdated(user, updated)
    toast.success('Používateľ odblokovaný.')
  } catch { toast.error('Odblokovanie zlyhalo.') }
}

async function remove(user: Record<string, unknown>) {
  if (!confirm(`Naozaj zmazať používateľa ${displayName(user)}?`)) return
  try {
    await deleteUser(user.id as number, SCOPE)
    user.deleted_at = new Date().toISOString()
    toast.success('Používateľ zmazaný.')
  } catch { toast.error('Zmazanie zlyhalo.') }
}
</script>
