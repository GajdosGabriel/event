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
            <h1 class="min-w-0 max-w-full truncate text-2xl font-semibold text-slate-900" :title="displayName(user)">{{ displayName(user) }}</h1>
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
        <div class="flex shrink-0 flex-wrap gap-2">
          <!-- Maily, prihlásenia a ďalšie udalosti tohto človeka. -->
          <RouterLink :to="{ path: '/admin/dennik', query: { user_id: String(userId) } }" class="btn btn-secondary">
            {{ t('nav.systemLog') }}
          </RouterLink>
          <!-- Detail je na čítanie; meniť sa dá vo formulári. -->
          <RouterLink :to="`/admin/users/${userId}/edit`" class="btn btn-primary">
            {{ t('admin.user.edit') }}
          </RouterLink>
        </div>
      </div>

      <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <!-- Left column -->
        <div class="grid min-w-0 grid-cols-1 gap-4">
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
            <ul v-if="canals.length" class="grid grid-cols-1 gap-2">
              <li v-for="c in pagedCanals" :key="c.id">
                <RouterLink :to="`/admin/canals/${c.id}`"
                  class="flex items-center justify-between gap-3 rounded-lg border border-slate-200 px-3 py-2 text-sm transition-colors hover:bg-slate-50">
                  <span class="min-w-0 truncate font-medium text-slate-800" :title="c.name">
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
            <AppPaginator :current-page="canalPage" :last-page="canalLastPage" @change="setCanalPage" />
          </section>
        </div>

        <!-- Right column: prehľad prístupu. Meniť sa dá vo formulári „Upraviť". -->
        <div class="grid content-start gap-4">
          <!-- Kontakt: prihlasovací e-mail + telefón/web z kanálov, ktoré vlastní. -->
          <section class="panel-card">
            <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">{{ t('admin.user.contact') }}</h2>
            <dl class="grid gap-3 text-sm">
              <div>
                <dt class="text-xs text-slate-400">{{ t('admin.user.contactLogin') }}</dt>
                <dd class="flex flex-wrap items-center gap-1.5">
                  <a v-if="user.email" :href="`mailto:${user.email}`" class="break-all text-blue-700">{{ user.email }}</a>
                  <span v-else>—</span>
                  <span v-if="user.email" class="rounded-full px-2 py-0.5 text-xs font-medium"
                    :class="user.email_verified ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800'">
                    {{ user.email_verified ? t('canals.show.emailVerified') : t('canals.show.emailUnverified') }}
                  </span>
                </dd>
              </div>

              <div v-for="c in contacts" :key="c.canal_id" class="border-t border-slate-100 pt-3">
                <dt class="mb-1 flex flex-wrap items-center gap-1.5 text-xs text-slate-400">
                  <RouterLink :to="`/admin/canals/${c.canal_id}`" class="font-medium text-slate-700 no-underline hover:text-blue-700 hover:underline">
                    {{ c.canal_name }}
                  </RouterLink>
                  <span>· {{ c.personal ? t('admin.user.personalCanal') : t('admin.user.contactOwned') }}</span>
                </dt>
                <dd class="grid gap-1">
                  <span v-if="c.email" class="flex flex-wrap items-center gap-1.5">
                    ✉ <a :href="`mailto:${c.email}`" class="break-all text-blue-700">{{ c.email }}</a>
                    <span class="rounded-full px-2 py-0.5 text-xs font-medium"
                      :class="c.email_verified ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800'">
                      {{ c.email_verified ? t('canals.show.emailVerified') : t('canals.show.emailUnverified') }}
                    </span>
                  </span>
                  <span v-if="c.phone">☎ <a :href="`tel:${c.phone}`" class="text-blue-700">{{ c.phone }}</a></span>
                  <span v-if="c.website">🌐 <a :href="c.website" target="_blank" rel="noopener" class="break-all text-blue-700">{{ c.website }}</a></span>
                </dd>
              </div>
              <p v-if="!contacts.length" class="text-xs text-slate-400">{{ t('admin.user.contactEmpty') }}</p>
              <p v-if="contactsMore" class="text-xs text-slate-400">{{ t('admin.user.contactMore', { n: contactsMore }) }}</p>
            </dl>
          </section>

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
import AppPaginator from '@/components/AppPaginator.vue'
import { useClientPagination } from '@/composables/useClientPagination'

const SCOPE = 'admin' as const

const route = useRoute()

const userId = computed(() => Number(route.params['id']))
const user = ref<Record<string, unknown> | null>(null)
const roles = ref<AccessRole[]>([])
const loading = ref(true)
const error = ref<string | null>(null)

const roleNames = computed(() => (user.value?.roles as string[]) ?? [])

interface UserContact {
  canal_id: number
  canal_name: string
  personal: boolean
  email: string | null
  email_verified: boolean
  phone: string | null
  website: string | null
}
const contactData = computed(() => user.value?.contacts as { items: UserContact[]; more: number } | undefined)
const contacts = computed(() => contactData.value?.items ?? [])
const contactsMore = computed(() => contactData.value?.more ?? 0)
const canals = computed(() => (user.value?.canals as { id: number; name: string; slug: string; status: string }[]) ?? [])
const {
  page: canalPage, lastPage: canalLastPage, items: pagedCanals, setPage: setCanalPage,
} = useClientPagination(canals, 10)

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
