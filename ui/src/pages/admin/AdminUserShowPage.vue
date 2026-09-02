<template>
  <div class="grid gap-4">
    <RouterLink to="/admin/users" class="inline-flex w-fit items-center gap-1.5 text-sm font-medium text-slate-500 hover:text-slate-700">
      {{ t('admin.user.back') }}
    </RouterLink>

    <p v-if="loading" class="text-slate-600">{{ t('admin.user.loading') }}</p>
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
        <!-- Detail je na čítanie; meniť sa dá vo formulári. -->
        <RouterLink :to="`/admin/users/${userId}/edit`" class="btn btn-primary shrink-0">
          {{ t('admin.user.edit') }}
        </RouterLink>
      </div>

      <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <!-- Left column -->
        <div class="grid gap-4">
          <!-- Overview -->
          <section class="panel-card">
            <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">{{ t('admin.user.overview') }}</h2>
            <dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
              <div>
                <dt class="text-xs text-slate-400">{{ t('admin.user.email') }}</dt>
                <dd class="text-sm text-slate-800">{{ user.email || '—' }}</dd>
              </div>
              <div>
                <dt class="text-xs text-slate-400">{{ t('admin.user.verified') }}</dt>
                <dd class="text-sm" :class="user.email_verified ? 'text-emerald-600' : 'text-amber-600'"
                  :title="fullDate(user.email_verified_at)">
                  {{ user.email_verified ? t('admin.user.yes') : t('admin.user.no') }}
                </dd>
              </div>
              <div>
                <dt class="text-xs text-slate-400">{{ t('admin.user.status') }}</dt>
                <dd class="text-sm text-slate-800">{{ user.status_label || user.status || '—' }}</dd>
              </div>
              <div>
                <dt class="text-xs text-slate-400">{{ t('admin.user.registeredVia') }}</dt>
                <dd class="flex items-center gap-1.5 text-sm text-slate-800">
                  <span>{{ providerMeta(user.registered_via as string).icon }}</span>
                  {{ providerMeta(user.registered_via as string).label }}
                </dd>
              </div>
              <div>
                <dt class="text-xs text-slate-400">{{ t('admin.user.createdAt') }}</dt>
                <dd class="text-sm text-slate-800" :title="fullDate(user.created_at)">
                  {{ user.created_at ? fmtDate(user.created_at as string) : '—' }}
                </dd>
              </div>
              <div>
                <dt class="text-xs text-slate-400">{{ t('admin.user.lastLogin') }}</dt>
                <dd class="text-sm text-slate-800" :title="fullDate(user.last_login_at)">{{ relTime(user.last_login_at) }}</dd>
              </div>
              <div>
                <dt class="text-xs text-slate-400">{{ t('admin.user.lastActivity') }}</dt>
                <dd class="text-sm text-slate-800" :title="fullDate(user.last_activity)">{{ relTime(user.last_activity ?? user.last_login_at) }}</dd>
              </div>
              <div>
                <dt class="text-xs text-slate-400">{{ t('admin.user.updatedAt') }}</dt>
                <dd class="text-sm text-slate-800" :title="fullDate(user.updated_at)">
                  {{ user.updated_at ? fmtDate(user.updated_at as string) : '—' }}
                </dd>
              </div>
              <!-- Doklad o súhlase s podmienkami: účty založené pred jeho
                   zavedením ho nemajú, preto sa tu môže objaviť pomlčka. -->
              <div>
                <dt class="text-xs text-slate-400">{{ t('admin.user.termsAccepted') }}</dt>
                <dd class="text-sm text-slate-800" :title="fullDate(user.terms_accepted_at)">
                  <template v-if="user.terms_accepted_at">
                    {{ fmtDate(user.terms_accepted_at as string) }}
                    <span class="text-slate-400">({{ t('admin.user.termsVersion') }} {{ user.terms_version || '—' }})</span>
                  </template>
                  <template v-else>—</template>
                </dd>
              </div>
              <div>
                <dt class="text-xs text-slate-400">{{ t('admin.user.uuid') }}</dt>
                <dd class="break-words text-sm text-slate-800">{{ user.uuid || '—' }}</dd>
              </div>
            </dl>
          </section>

          <!-- Canals -->
          <section class="panel-card">
            <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">
              {{ t('admin.user.canals') }} <span class="text-slate-400">({{ canals.length }})</span>
            </h2>
            <ul v-if="canals.length" class="grid gap-2">
              <li v-for="c in canals" :key="c.id">
                <RouterLink :to="`/admin/canals/${c.id}`"
                  class="flex items-center justify-between gap-3 rounded-lg border border-slate-200 px-3 py-2 text-sm transition-colors hover:bg-slate-50">
                  <span class="min-w-0 truncate font-medium text-slate-800">
                    {{ c.name }}
                    <span v-if="Number(user.canal_id) === c.id" class="ml-1 text-xs font-normal text-slate-400">
                      ({{ t('admin.user.personalCanal') }})
                    </span>
                  </span>
                  <span class="shrink-0 rounded-full px-2 py-0.5 text-[0.7rem] font-medium uppercase tracking-wide"
                    :class="canalStatusClass(c.status)">{{ statusLabel('canals', c.status) }}</span>
                </RouterLink>
              </li>
            </ul>
            <p v-else class="text-sm text-slate-400">{{ t('admin.user.canalsEmpty') }}</p>
          </section>
        </div>

        <!-- Right column: prehľad prístupu. Meniť sa dá vo formulári „Upraviť". -->
        <div class="grid content-start gap-4">
          <section class="panel-card">
            <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">{{ t('admin.user.roles') }}</h2>
            <div v-if="roleNames.length" class="flex flex-wrap gap-1">
              <span v-for="role in roleNames" :key="role"
                class="rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset" :class="roleClass(role)">
                {{ roleLabel(role, roles) }}
              </span>
            </div>
            <p v-else class="text-sm text-slate-400">—</p>
          </section>

          <section class="panel-card">
            <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">{{ t('admin.user.blocking') }}</h2>

            <div v-if="user.is_blocked" class="rounded-lg bg-red-50 p-3 text-sm text-red-700 ring-1 ring-inset ring-red-200">
              <p class="font-medium">{{ t('admin.user.blocked') }}</p>
              <p v-if="user.blocked_reason" class="mt-1 text-red-600">{{ user.blocked_reason }}</p>
              <p class="mt-1 text-xs text-red-500">
                {{ user.blocked_until
                  ? t('admin.user.blockedUntil', { date: fullDate(user.blocked_until) })
                  : t('admin.user.blockedForever') }}
              </p>
              <p v-if="user.blocked_at" class="mt-1 text-xs text-red-400">
                {{ t('admin.user.blockedAt') }}: {{ fullDate(user.blocked_at) }}
              </p>
            </div>
            <p v-else class="text-sm text-slate-500">{{ t('users.statuses.active') }}</p>
          </section>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { showUser, getRoles } from '@/api/access-control'
import type { AccessRole } from '@/types'
import { t } from '@/i18n'
import { fmtDate } from '@/utils/dateFormat'
import {
  displayName, initials, avatarColor, roleLabel, roleClass,
  statusOf, providerMeta, relTime, fullDate,
} from '@/utils/userDisplay'
import { statusLabel } from '@/utils/statusLabel'

const SCOPE = 'admin' as const

const route = useRoute()

const userId = computed(() => Number(route.params['id']))
const user = ref<Record<string, unknown> | null>(null)
const roles = ref<AccessRole[]>([])
const loading = ref(true)
const error = ref<string | null>(null)

const roleNames = computed(() => (user.value?.roles as string[]) ?? [])
const canals = computed(() => (user.value?.canals as { id: number; name: string; slug: string; status: string }[]) ?? [])

onMounted(async () => {
  try {
    ;[user.value, roles.value] = await Promise.all([showUser(userId.value, SCOPE), getRoles(SCOPE)])
  } catch {
    error.value = t('admin.user.loadFailed')
  } finally {
    loading.value = false
  }
})

function canalStatusClass(status: string): string {
  switch (status) {
    case 'published': return 'bg-emerald-50 text-emerald-700'
    case 'archived':  return 'bg-slate-100 text-slate-500'
    case 'blocked':   return 'bg-red-50 text-red-700'
    default:          return 'bg-amber-50 text-amber-700'
  }
}
</script>
