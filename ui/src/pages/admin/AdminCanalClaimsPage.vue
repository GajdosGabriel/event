<template>
  <div class="grid gap-4">
    <h1 class="text-2xl font-semibold text-slate-900">{{ t('canalClaim.admin.title') }}</h1>
    <p class="max-w-3xl text-sm text-slate-600">{{ t('canalClaim.admin.intro') }}</p>

    <div class="flex flex-wrap gap-2">
      <button v-for="option in filters" :key="option" class="btn"
        :class="filter === option ? 'btn-primary' : 'btn-secondary'" @click="setFilter(option)">
        {{ t(`canalClaim.admin.filter.${option}`) }}
      </button>
    </div>

    <p v-if="loading" class="text-slate-600">{{ t('canalClaim.loading') }}</p>
    <p v-else-if="items.length === 0" class="panel-card text-sm text-slate-500">{{ t('canalClaim.admin.empty') }}</p>

    <div v-else class="grid gap-3">
      <article v-for="item in items" :key="item.id" class="panel-card grid gap-2 text-sm">
        <div class="flex flex-wrap items-center gap-2">
          <RouterLink v-if="item.canal" :to="`/organizatori/${item.canal.id}`"
            class="font-semibold text-slate-900 no-underline hover:underline">{{ item.canal.name }}</RouterLink>
          <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="statusClass(item.status)">
            {{ t(`canalClaim.admin.status.${item.status}`) }}
          </span>
          <span class="text-xs text-slate-500">{{ t(`canalClaim.admin.method.${item.method}`) }}</span>
          <span class="ml-auto text-xs text-slate-400">{{ formatDate(item.createdAt) }}</span>
        </div>

        <dl class="grid gap-1 text-slate-700 sm:grid-cols-2">
          <div><dt class="inline text-slate-500">{{ t('canalClaim.admin.requester') }}:</dt>
            <dd class="inline"> {{ item.user?.name }} &lt;{{ item.user?.email }}&gt;</dd></div>
          <div><dt class="inline text-slate-500">{{ t('canalClaim.admin.contact') }}:</dt>
            <dd class="inline"> {{ item.contactEmail ?? item.canal?.email ?? '—' }}</dd></div>
        </dl>

        <p v-if="item.message" class="rounded-lg bg-slate-50 p-2 text-slate-700">„{{ item.message }}“</p>
        <p v-if="item.contestNote" class="rounded-lg bg-red-50 p-2 text-red-800">
          <span class="font-medium">{{ t('canalClaim.admin.contestNote') }}:</span> „{{ item.contestNote }}“
        </p>
        <p v-if="item.decisionNote" class="text-xs text-slate-500">
          {{ t('canalClaim.admin.decision') }}: {{ item.decisionNote }} <template v-if="item.decidedBy">({{ item.decidedBy }})</template>
        </p>

        <div v-if="item.status === 'pending' || item.status === 'contested'" class="flex flex-wrap items-center gap-2 border-t border-slate-100 pt-2">
          <input v-model="notes[item.id]" type="text" maxlength="2000" :placeholder="t('canalClaim.admin.notePlaceholder')"
            class="form-input h-9 min-w-[12rem] flex-1" />
          <template v-if="item.status === 'pending'">
            <button class="btn btn-primary" :disabled="saving === item.id" @click="act(item, 'approve')">{{ t('canalClaim.admin.approve') }}</button>
            <button class="btn btn-secondary" :disabled="saving === item.id" @click="act(item, 'reject')">{{ t('canalClaim.admin.reject') }}</button>
          </template>
          <template v-else>
            <button class="btn btn-secondary" :disabled="saving === item.id" @click="act(item, 'keep')">{{ t('canalClaim.admin.keep') }}</button>
            <button class="btn btn-danger" :disabled="saving === item.id" @click="act(item, 'revert')">{{ t('canalClaim.admin.revert') }}</button>
          </template>
        </div>
      </article>
    </div>
  </div>
</template>

<script setup lang="ts">
import { reactive, ref, onMounted } from 'vue'
import {
  fetchAdminCanalClaims,
  approveCanalClaim,
  rejectCanalClaim,
  resolveCanalClaim,
  type AdminCanalClaim,
  type CanalClaimStatus,
} from '@/api/canalClaims'
import { useToast } from '@/composables/useToast'
import { currentLocale, t } from '@/i18n'

/** `open` = čakajúce + napadnuté (predvoľba servera). */
type Filter = 'open' | 'completed' | 'rejected' | 'reverted' | 'expired'
const filters: Filter[] = ['open', 'completed', 'rejected', 'reverted', 'expired']

const toast = useToast()
const filter = ref<Filter>('open')
const items = ref<AdminCanalClaim[]>([])
const loading = ref(false)
const saving = ref<number | null>(null)
const notes = reactive<Record<number, string>>({})

function statusClass(status: CanalClaimStatus): string {
  switch (status) {
    case 'pending': return 'bg-amber-100 text-amber-800'
    case 'contested': return 'bg-red-100 text-red-700'
    case 'completed': return 'bg-green-100 text-green-800'
    default: return 'bg-slate-100 text-slate-600'
  }
}

function formatDate(value: string | null): string {
  return value ? new Date(value).toLocaleString(currentLocale(), { dateStyle: 'short', timeStyle: 'short' }) : ''
}

async function load() {
  loading.value = true
  try {
    items.value = await fetchAdminCanalClaims(filter.value === 'open' ? undefined : filter.value)
  } finally {
    loading.value = false
  }
}

function setFilter(value: Filter) {
  filter.value = value
  load()
}

async function act(item: AdminCanalClaim, action: 'approve' | 'reject' | 'keep' | 'revert') {
  if (action === 'revert' && !confirm(t('canalClaim.admin.revertConfirm', { canal: item.canal?.name ?? '' }))) return
  saving.value = item.id
  const note = notes[item.id] ?? ''
  try {
    if (action === 'approve') await approveCanalClaim(item.id, note)
    else if (action === 'reject') await rejectCanalClaim(item.id, note)
    else await resolveCanalClaim(item.id, action === 'revert', note)
    toast.success(t('canalClaim.admin.done'))
    await load()
  } catch (e: unknown) {
    const res = (e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }).response
    toast.error(Object.values(res?.data?.errors ?? {})[0]?.[0] ?? res?.data?.message ?? t('canalClaim.failed'))
  } finally {
    saving.value = null
  }
}

onMounted(load)
</script>
