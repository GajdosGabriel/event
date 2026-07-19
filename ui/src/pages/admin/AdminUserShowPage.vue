<template>
  <div class="grid gap-4">
    <RouterLink to="/admin/users" class="inline-flex w-fit items-center gap-1.5 text-sm font-medium text-slate-500 hover:text-slate-700">
      ← Používatelia
    </RouterLink>

    <p v-if="loading" class="text-slate-600">Načítavam…</p>
    <p v-else-if="error" class="text-red-600">{{ error }}</p>

    <template v-else-if="user">
      <!-- Header -->
      <div class="panel-card flex flex-wrap items-center gap-4">
        <span class="grid h-14 w-14 shrink-0 place-items-center rounded-full text-lg font-semibold text-white"
          :class="user.deleted_at ? 'bg-slate-400' : avatarColor(displayName(user))">
          {{ initials(displayName(user)) }}
        </span>
        <div class="min-w-0 flex-1">
          <div class="flex flex-wrap items-center gap-2">
            <h1 class="truncate text-2xl font-semibold text-slate-900">{{ displayName(user) }}</h1>
            <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset"
              :class="statusOf(user).cls">
              <span class="h-1.5 w-1.5 rounded-full" :class="statusOf(user).dot"></span>
              {{ statusOf(user).label }}
            </span>
          </div>
          <div class="mt-0.5 text-sm text-slate-500">{{ user.email || '—' }}</div>
          <div v-if="roleNames.length" class="mt-2 flex flex-wrap gap-1">
            <span v-for="role in roleNames" :key="role"
              class="rounded-full px-2 py-0.5 text-[0.7rem] font-medium ring-1 ring-inset" :class="roleClass(role)">
              {{ roleLabel(role, roles) }}
            </span>
          </div>
        </div>
      </div>

      <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <!-- Left column -->
        <div class="grid gap-4">
          <!-- Overview -->
          <section class="panel-card">
            <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">Prehľad</h2>
            <dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
              <div>
                <dt class="text-xs text-slate-400">Email</dt>
                <dd class="text-sm text-slate-800">{{ user.email || '—' }}</dd>
              </div>
              <div>
                <dt class="text-xs text-slate-400">Overený email</dt>
                <dd class="text-sm" :class="user.email_verified ? 'text-emerald-600' : 'text-amber-600'">
                  {{ user.email_verified ? 'Áno' : 'Nie' }}
                </dd>
              </div>
              <div>
                <dt class="text-xs text-slate-400">Registrácia</dt>
                <dd class="flex items-center gap-1.5 text-sm text-slate-800">
                  <span>{{ providerMeta(user.registered_via as string).icon }}</span>
                  {{ providerMeta(user.registered_via as string).label }}
                </dd>
              </div>
              <div>
                <dt class="text-xs text-slate-400">Vytvorený</dt>
                <dd class="text-sm text-slate-800" :title="fullDate(user.created_at)">
                  {{ user.created_at ? fmtDate(user.created_at as string) : '—' }}
                </dd>
              </div>
              <div>
                <dt class="text-xs text-slate-400">Posledné prihlásenie</dt>
                <dd class="text-sm text-slate-800" :title="fullDate(user.last_login_at)">{{ relTime(user.last_login_at) }}</dd>
              </div>
              <div>
                <dt class="text-xs text-slate-400">Posledná aktivita</dt>
                <dd class="text-sm text-slate-800" :title="fullDate(user.last_activity)">{{ relTime(user.last_activity ?? user.last_login_at) }}</dd>
              </div>
            </dl>
          </section>

          <!-- Canals -->
          <section class="panel-card">
            <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">
              Kanály <span class="text-slate-400">({{ canals.length }})</span>
            </h2>
            <ul v-if="canals.length" class="grid gap-2">
              <li v-for="c in canals" :key="c.id">
                <RouterLink :to="`/admin/canals/${c.id}`"
                  class="flex items-center justify-between gap-3 rounded-lg border border-slate-200 px-3 py-2 text-sm transition-colors hover:bg-slate-50">
                  <span class="min-w-0 truncate font-medium text-slate-800">{{ c.name }}</span>
                  <span class="shrink-0 rounded-full px-2 py-0.5 text-[0.7rem] font-medium uppercase tracking-wide"
                    :class="canalStatusClass(c.status)">{{ c.status }}</span>
                </RouterLink>
              </li>
            </ul>
            <p v-else class="text-sm text-slate-400">Používateľ nie je členom žiadneho kanálu.</p>
          </section>
        </div>

        <!-- Right column: management -->
        <div class="grid content-start gap-4">
          <!-- Roles -->
          <section class="panel-card">
            <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">Role</h2>
            <div class="grid gap-2">
              <label v-for="role in roles" :key="role.name" class="flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" :value="role.name" v-model="selectedRoles" class="accent-blue-600" />
                {{ role.label ?? roleLabel(role.name, roles) }}
              </label>
            </div>
            <button class="btn btn-primary mt-4 w-full" :disabled="savingRoles || !rolesChanged" @click="saveRoles">
              {{ savingRoles ? 'Ukladám…' : 'Uložiť role' }}
            </button>
          </section>

          <!-- Block -->
          <section class="panel-card">
            <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">Blokovanie</h2>

            <p v-if="isSelf" class="text-sm text-slate-400">Vlastný účet nemôžeš blokovať.</p>

            <template v-else-if="user.is_blocked">
              <div class="rounded-lg bg-red-50 p-3 text-sm text-red-700 ring-1 ring-inset ring-red-200">
                <p class="font-medium">Používateľ je blokovaný</p>
                <p v-if="user.blocked_reason" class="mt-1 text-red-600">{{ user.blocked_reason }}</p>
                <p class="mt-1 text-xs text-red-500">
                  {{ user.blocked_until ? `Do ${fullDate(user.blocked_until)}` : 'Bez časového obmedzenia.' }}
                </p>
              </div>
              <button class="btn btn-secondary mt-3 w-full" :disabled="savingBlock" @click="unblock">
                {{ savingBlock ? 'Ukladám…' : 'Odblokovať' }}
              </button>
            </template>

            <template v-else>
              <label class="grid gap-1 text-sm">
                <span class="font-medium text-slate-700">Dôvod <span class="text-slate-400">(voliteľné)</span></span>
                <textarea v-model="blockReason" rows="2" class="form-textarea" placeholder="napr. porušenie pravidiel"></textarea>
              </label>
              <label class="mt-3 grid gap-1 text-sm">
                <span class="font-medium text-slate-700">Blokovať do <span class="text-slate-400">(voliteľné)</span></span>
                <input v-model="blockUntil" type="datetime-local" class="form-input" />
                <span class="text-xs text-slate-400">Prázdne = blok bez časového obmedzenia.</span>
              </label>
              <button class="btn btn-danger mt-3 w-full" :disabled="savingBlock" @click="block">
                {{ savingBlock ? 'Ukladám…' : 'Blokovať' }}
              </button>
            </template>
          </section>

          <!-- Danger zone -->
          <section class="panel-card border-red-200">
            <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-red-500">Nebezpečná zóna</h2>
            <button v-if="user.deleted_at" class="btn btn-secondary w-full" :disabled="savingDelete" @click="restore">
              {{ savingDelete ? 'Ukladám…' : 'Obnoviť používateľa' }}
            </button>
            <template v-else>
              <p v-if="isSelf" class="text-sm text-slate-400">Vlastný účet nemôžeš zmazať.</p>
              <button v-else class="btn btn-danger w-full" :disabled="savingDelete" @click="remove">
                {{ savingDelete ? 'Mažem…' : 'Zmazať používateľa' }}
              </button>
            </template>
          </section>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { showUser, getRoles, updateUserRoles, updateUser, deleteUser, restoreUser } from '@/api/access-control'
import type { AccessRole } from '@/types'
import { useToast } from '@/composables/useToast'
import { useAuthStore } from '@/stores/auth'
import { fmtDate } from '@/utils/dateFormat'
import {
  displayName, initials, avatarColor, roleLabel, roleClass,
  statusOf, providerMeta, relTime, fullDate,
} from '@/utils/userDisplay'

const SCOPE = 'admin' as const

const route = useRoute()
const router = useRouter()
const toast = useToast()
const auth = useAuthStore()

const userId = computed(() => Number(route.params.id))
const user = ref<Record<string, unknown> | null>(null)
const roles = ref<AccessRole[]>([])
const loading = ref(true)
const error = ref<string | null>(null)

const selectedRoles = ref<string[]>([])
const savingRoles = ref(false)
const savingBlock = ref(false)
const savingDelete = ref(false)
const blockReason = ref('')
const blockUntil = ref('')

const isSelf = computed(() => user.value && Number(user.value.id) === Number(auth.identity?.id))
const roleNames = computed(() => (user.value?.roles as string[]) ?? [])
const canals = computed(() => (user.value?.canals as { id: number; name: string; slug: string; status: string }[]) ?? [])
const rolesChanged = computed(() => {
  const a = [...selectedRoles.value].sort().join(',')
  const b = [...roleNames.value].sort().join(',')
  return a !== b
})

async function load() {
  loading.value = true
  error.value = null
  try {
    ;[user.value, roles.value] = await Promise.all([
      showUser(userId.value, SCOPE),
      roles.value.length ? Promise.resolve(roles.value) : getRoles(SCOPE),
    ])
    selectedRoles.value = [...roleNames.value]
  } catch {
    error.value = 'Nepodarilo sa načítať používateľa.'
  } finally {
    loading.value = false
  }
}

onMounted(load)

function canalStatusClass(status: string): string {
  switch (status) {
    case 'published': return 'bg-emerald-50 text-emerald-700'
    case 'archived':  return 'bg-slate-100 text-slate-500'
    case 'blocked':   return 'bg-red-50 text-red-700'
    default:          return 'bg-amber-50 text-amber-700'
  }
}

async function saveRoles() {
  savingRoles.value = true
  try {
    await updateUserRoles(userId.value, selectedRoles.value, SCOPE)
    if (user.value) user.value.roles = [...selectedRoles.value]
    toast.success('Role uložené.')
  } catch { toast.error('Uloženie rolí zlyhalo.') }
  finally { savingRoles.value = false }
}

async function block() {
  savingBlock.value = true
  try {
    const updated = await updateUser(userId.value, {
      blocked: true,
      blocked_reason: blockReason.value.trim() || null,
      blocked_until: blockUntil.value || null,
    }, SCOPE)
    applyUpdated(updated)
    blockReason.value = ''
    blockUntil.value = ''
    toast.success('Používateľ zablokovaný.')
  } catch { toast.error('Blokovanie zlyhalo.') }
  finally { savingBlock.value = false }
}

async function unblock() {
  savingBlock.value = true
  try {
    const updated = await updateUser(userId.value, { blocked: false }, SCOPE)
    applyUpdated(updated)
    toast.success('Používateľ odblokovaný.')
  } catch { toast.error('Odblokovanie zlyhalo.') }
  finally { savingBlock.value = false }
}

function applyUpdated(updated: Record<string, unknown>) {
  if (!user.value) return
  user.value.is_blocked = updated.is_blocked
  user.value.blocked_until = updated.blocked_until
  user.value.blocked_reason = updated.blocked_reason
}

async function remove() {
  if (!user.value) return
  if (!confirm(`Naozaj zmazať používateľa ${displayName(user.value)}?`)) return
  savingDelete.value = true
  try {
    await deleteUser(userId.value, SCOPE)
    toast.success('Používateľ zmazaný.')
    router.push('/admin/users')
  } catch { toast.error('Zmazanie zlyhalo.') }
  finally { savingDelete.value = false }
}

async function restore() {
  savingDelete.value = true
  try {
    await restoreUser(userId.value, SCOPE)
    if (user.value) user.value.deleted_at = null
    toast.success('Používateľ obnovený.')
  } catch { toast.error('Obnova zlyhala.') }
  finally { savingDelete.value = false }
}
</script>
