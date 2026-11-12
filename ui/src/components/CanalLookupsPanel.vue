<template>
  <div class="show-card">
    <h2 class="mb-1 text-base font-semibold text-slate-800">{{ t('canals.lookups.title') }}</h2>
    <p class="mb-3 text-xs text-slate-500">{{ t('canals.lookups.hint') }}</p>

    <p v-if="loading && !status" class="text-sm text-slate-500">{{ t('common.loading') }}</p>
    <p v-else-if="!status" class="text-sm text-slate-400">{{ t('canals.lookups.loadFailed') }}</p>

    <ol v-else class="grid gap-3">
      <li v-for="step in steps" :key="step.kind" class="detail-card">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <span class="text-sm font-medium text-slate-800">{{ step.title }}</span>
          <ActionButton
            :disabled="step.state.running || !!step.state.blocker || starting === step.kind"
            :title="step.state.blocker ? blockerLabel(step.state.blocker) : ''"
            @click="run(step.kind)"
          >
            {{ step.state.running ? t('canals.lookups.running') : t('canals.lookups.run') }}
          </ActionButton>
        </div>
        <ul class="mt-1 grid gap-0.5 text-xs text-slate-600">
          <li v-for="(line, i) in step.lines" :key="i">{{ line }}</li>
          <li v-if="step.state.blocker" class="text-slate-400">{{ blockerLabel(step.state.blocker) }}</li>
        </ul>
      </li>
    </ol>
  </div>
</template>

<script setup lang="ts">
/**
 * Doplnenie údajov kanála pre admina: profil (web, adresa, kontakty) →
 * poloha → e-mail, v poradí, v akom sa dopĺňajú automaticky.
 *
 * Ukazuje, čo sa naposledy hľadalo a prečo sa nehľadá, a krok vie spustiť
 * znova. Hľadanie beží vo fronte — panel si stav obnovuje, kým niečo beží,
 * a po dobehnutí dá vedieť rodičovi (detail kanála sa načíta nanovo).
 */
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import ActionButton from '@/components/ActionButton.vue'
import { getCanalLookups, runCanalLookup, type CanalLookupKind, type CanalLookups } from '@/api/canals'
import { useToast } from '@/composables/useToast'
import { fmtDate, fmtTime } from '@/utils/dateFormat'
import { useI18n, type MessageKey } from '@/i18n'

const props = defineProps<{ canalId: number }>()
const emit = defineEmits<{ changed: [] }>()
const { t: translate } = useI18n()
// Kľúče sa skladajú z kódov z API (zdroj polohy, dôvod preskočenia…).
const t = (key: string, params?: Record<string, string | number>) => translate(key as MessageKey, params)
const toast = useToast()

const status = ref<CanalLookups | null>(null)
const loading = ref(false)
const starting = ref<CanalLookupKind | null>(null)
let timer: ReturnType<typeof setTimeout> | null = null
let polls = 0

/** Hľadanie trvá desiatky sekúnd plus čakanie na beh fronty (do minúty). */
const POLL_MS = 5000
const MAX_POLLS = 60

const anyRunning = computed(() => !!status.value && Object.values(status.value).some(s => s.running))

function when(date: string | null): string {
  return date ? `${fmtDate(date)} ${fmtTime(date)}` : ''
}

function blockerLabel(code: string): string {
  return t(`canals.lookups.blockers.${code}`)
}

function fields(list: string[]): string {
  return list.map(f => t(`canals.lookups.fields.${f}`)).join(', ')
}

const steps = computed(() => {
  const s = status.value
  if (!s) return []

  const profile: string[] = []
  profile.push(s.profile.missing.length
    ? t('canals.lookups.profile.missing', { fields: fields(s.profile.missing) })
    : t('canals.lookups.profile.complete'))
  if (s.profile.completedAt) {
    profile.push(t('canals.lookups.lastRun', { date: when(s.profile.completedAt) }) + ' · ' + (s.profile.changedFields.length
      ? t('canals.lookups.profile.changed', { fields: fields(s.profile.changedFields) })
      : t('canals.lookups.profile.nothingFound')))
  } else if (s.profile.attempts > 0) {
    profile.push(t('canals.lookups.attempts', { n: s.profile.attempts }))
  } else {
    profile.push(t('canals.lookups.never'))
  }
  if (s.profile.inputsChanged) profile.push(t('canals.lookups.inputsChanged'))
  if (s.profile.lastError) profile.push(t('canals.lookups.error', { error: s.profile.lastError }))

  const coordinates: string[] = []
  coordinates.push(s.coordinates.missing
    ? t('canals.lookups.coordinates.placeholder')
    : t('canals.lookups.coordinates.source', { source: sourceLabel(s.coordinates.coordinatesSource) }))
  if (s.coordinates.lookedUpAt) {
    coordinates.push(t('canals.lookups.lastRun', { date: when(s.coordinates.lookedUpAt) }) + ' · ' + (s.coordinates.foundSource
      ? t('canals.lookups.coordinates.found', { source: sourceLabel(s.coordinates.foundSource) })
      : t('canals.lookups.coordinates.notFound')))
  } else if (s.coordinates.missing) {
    coordinates.push(t('canals.lookups.never'))
  }
  if (s.coordinates.missing && s.coordinates.inputsChanged) coordinates.push(t('canals.lookups.inputsChanged'))

  const email: string[] = []
  if (s.email.searchedAt) {
    email.push(t('canals.lookups.lastRun', { date: when(s.email.searchedAt) }) + ' · '
      + t(`canals.lookups.email.results.${s.email.result ?? 'none'}`)
      + (s.email.foundVia ? ` (${t(`canals.lookups.email.via.${s.email.foundVia}`)})` : ''))
  } else {
    email.push(t('canals.lookups.never'))
  }
  if (s.email.autoSkipReason) {
    email.push(t('canals.lookups.email.autoSkip', { reason: t(`canals.lookups.email.reasons.${s.email.autoSkipReason}`) }))
  } else if (s.email.retryAt && s.email.result !== 'found') {
    email.push(t('canals.lookups.email.nextRun', { date: when(s.email.retryAt) }))
  }
  if (s.email.lastError && !s.email.autoSkipReason) email.push(t('canals.lookups.error', { error: s.email.lastError }))

  return [
    { kind: 'profile' as const, title: t('canals.lookups.profile.title'), state: s.profile, lines: profile },
    { kind: 'coordinates' as const, title: t('canals.lookups.coordinates.title'), state: s.coordinates, lines: coordinates },
    { kind: 'email' as const, title: t('canals.lookups.email.title'), state: s.email, lines: email },
  ]
})

function sourceLabel(source: string | null): string {
  return source ? t(`canals.lookups.coordinates.sources.${source}`) : '—'
}

async function load() {
  loading.value = true
  try {
    const wasRunning = anyRunning.value
    status.value = await getCanalLookups(props.canalId)
    // Krok dobehol — údaje kanála (web, adresa, poloha, e-maily) sa mohli zmeniť.
    if (wasRunning && !anyRunning.value) emit('changed')
  } catch {
    status.value = null
  } finally {
    loading.value = false
  }
  schedule()
}

function schedule() {
  if (timer) clearTimeout(timer)
  timer = null
  if (anyRunning.value && polls < MAX_POLLS) {
    polls++
    timer = setTimeout(load, POLL_MS)
  }
}

async function run(kind: CanalLookupKind) {
  starting.value = kind
  try {
    status.value = await runCanalLookup(props.canalId, kind)
    toast.success(t('canals.lookups.queued'))
    polls = 0
    // Synchrónna fronta (lokálne) dobehne ešte pred odpoveďou — nie je čo sledovať.
    if (!anyRunning.value) emit('changed')
    schedule()
  } catch (e: unknown) {
    const message = (e as { response?: { data?: { message?: string } } })?.response?.data?.message
    toast.error(message ?? t('canals.lookups.runFailed'))
  } finally {
    starting.value = null
  }
}

onMounted(load)
onBeforeUnmount(() => { if (timer) clearTimeout(timer) })
</script>
