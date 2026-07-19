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
            {{ role.label ?? roleLabel(role.name, roles) }}
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
                <RouterLink :to="`/admin/users/${user.id}`" class="flex items-center gap-3 group">
                  <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full text-xs font-semibold text-white"
                    :class="user.deleted_at ? 'bg-slate-400' : avatarColor(displayName(user))">
                    {{ initials(displayName(user)) }}
                  </span>
                  <div class="min-w-0">
                    <div class="truncate font-medium text-slate-900 group-hover:text-blue-600">{{ displayName(user) }}</div>
                    <div class="truncate text-xs text-slate-500">{{ user.email || '—' }}</div>
                  </div>
                </RouterLink>
              </td>

              <!-- Roles -->
              <td class="px-4 py-3">
                <div v-if="(user.roles as string[])?.length" class="flex flex-wrap gap-1">
                  <span v-for="role in (user.roles as string[])" :key="role"
                    class="rounded-full px-2 py-0.5 text-[0.7rem] font-medium ring-1 ring-inset"
                    :class="roleClass(role)">
                    {{ roleLabel(role, roles) }}
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
                    <RouterLink :to="`/admin/users/${user.id}`" class="row-menu-item block">Upraviť</RouterLink>
                    <template v-if="user.deleted_at">
                      <button class="row-menu-item" @click="restore(user.id as number)">Obnoviť</button>
                    </template>
                    <template v-else-if="!isSelf(user)">
                      <button class="row-menu-item row-menu-item-danger" @click="remove(user)">Zmazať</button>
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

  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { listUsers, getRoles, restoreUser, deleteUser } from '@/api/access-control'
import type { AccessRole } from '@/types'
import { useToast } from '@/composables/useToast'
import { useAuthStore } from '@/stores/auth'
import { fmtDate } from '@/utils/dateFormat'
import {
  displayName, initials, avatarColor, roleLabel, roleClass,
  statusKey, statusOf, providerMeta, relTime, fullDate, pluralUsers,
} from '@/utils/userDisplay'
import ResourceFilterBar, { type FilterOption } from '@/components/ResourceFilterBar.vue'
import RowActions from '@/components/RowActions.vue'

const SCOPE = 'admin' as const

const toast = useToast()
const auth = useAuthStore()
const users = ref<Record<string, unknown>[]>([])
const roles = ref<AccessRole[]>([])
const loading = ref(false)
const error = ref<string | null>(null)
const search = ref('')
const statusFilter = ref('')
const roleFilter = ref('')
const sort = ref('newest')

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

async function restore(userId: number) {
  try {
    await restoreUser(userId, SCOPE)
    const user = users.value.find(u => u.id === userId)
    if (user) user.deleted_at = null
    toast.success('Používateľ obnovený.')
  } catch { toast.error('Obnova zlyhala.') }
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
