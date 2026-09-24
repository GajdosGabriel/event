<template>
  <div class="grid gap-4" :aria-busy="loading">
    <p v-if="loading">{{ t('common.loading') }}</p>
    <p v-if="error" role="alert">{{ t('common.actionFailed') }} <button type="button" @click="load">{{ t('roadmap.retry') }}</button></p>
    <template v-if="data">
      <section v-if="data.checklist.some(step => !step.done)" class="rounded-xl border border-slate-200 bg-white p-4">
        <h2 class="mb-3 text-lg">{{ t('roadmap.onboarding') }}</h2>
        <ol class="flex flex-wrap gap-4"><li v-for="step in data.checklist" :key="step.kind">
          <span v-if="step.done">✓ {{ t(`roadmap.${step.kind}`) }}</span>
          <RouterLink v-else-if="step.can_create" :to="`/dashboard/${step.kind}s/create`">{{ t(`roadmap.${step.kind}`) }}</RouterLink>
          <span v-else>{{ t(`roadmap.${step.kind}`) }}</span>
        </li></ol>
      </section>
      <div class="grid gap-4 md:grid-cols-2">
        <section class="rounded-xl border border-slate-200 bg-white p-4">
          <h2 class="mb-3 text-lg">{{ t('roadmap.upcoming') }}</h2>
          <ul class="grid gap-3"><li v-for="event in data.upcoming" :key="event.id">
            <RouterLink :to="`/dashboard/events/${event.id}`">{{ event.name }}</RouterLink>
            <p class="text-sm text-slate-500">{{ fmtDate(event.start_at) }} · {{ t('roadmap.registrations', { count: event.registrations }) }}</p>
          </li></ul>
          <p v-if="!data.upcoming.length">{{ t('roadmap.none') }}</p>
          <RouterLink to="/dashboard/events?phase=next7d&status=published&sort=upcoming" class="mt-3 inline-block">{{ t('roadmap.all') }}</RouterLink>
        </section>
        <section class="rounded-xl border border-slate-200 bg-white p-4">
          <h2 class="mb-3 text-lg">{{ t('roadmap.drafts') }}</h2>
          <ul class="grid gap-3"><li v-for="event in drafts" :key="event.id">
            <RouterLink :to="`/dashboard/events/${event.id}${event.can_edit ? '/edit' : ''}`">{{ event.name }}</RouterLink>
            <template v-if="rules">
              <p class="text-sm">{{ t('roadmap.readiness', { count: event.percent }) }}</p>
              <progress :value="event.percent" max="100" :aria-label="t('roadmap.readiness', { count: event.percent })" class="w-full" />
            </template>
          </li></ul>
          <p v-if="!drafts.length">{{ t('roadmap.none') }}</p>
          <RouterLink to="/dashboard/events?status=draft" class="mt-3 inline-block">{{ t('roadmap.all') }}</RouterLink>
        </section>
      </div>
      <RouterLink to="/dashboard/messages?unread=1" class="rounded-xl border border-slate-200 bg-white p-4">{{ t('roadmap.unread') }}: {{ data.unread }}</RouterLink>
    </template>
  </div>
</template>
<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import http from '@/api/index'
import { fetchReadinessRules, type ReadinessRule } from '@/api/ai'
import { evaluateReadiness } from '@/utils/publishReadiness'
import { fmtDate } from '@/utils/dateFormat'
import { useI18n } from '@/i18n'
interface NextActions {
  upcoming: { id: number; name: string; start_at: string; registrations: number }[]
  drafts: { id: number; name: string; can_edit: boolean; values: Record<string, unknown> }[]
  unread: number
  checklist: { kind: 'canal' | 'venue' | 'event'; done: boolean; can_create: boolean }[]
}
const { t } = useI18n()
const data = ref<NextActions | null>(null)
const rules = ref<ReadinessRule[] | null>(null)
const loading = ref(false)
const error = ref(false)
let version = 0
const drafts = computed(() => (data.value?.drafts ?? []).map(event => ({ ...event, ...evaluateReadiness(rules.value ?? [], event.values) })))
async function load() {
  const id = ++version
  loading.value = true
  error.value = false
  const [result, readiness] = await Promise.allSettled([http.get('/dashboard/next-actions'), fetchReadinessRules('dashboard')])
  if (id !== version) return
  if (result.status === 'fulfilled') data.value = result.value.data.data
  else error.value = true
  if (readiness.status === 'fulfilled') rules.value = readiness.value.event
  loading.value = false
}
onMounted(load)
onUnmounted(() => { ++version })
</script>
