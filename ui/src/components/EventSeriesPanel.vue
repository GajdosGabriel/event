<template>
  <div v-if="occurrences.length || canAdd" class="show-card">
    <div class="mb-3 flex items-center justify-between gap-2">
      <h2 class="text-base font-semibold text-slate-800">
        {{ occurrences.length ? t('events.series.title', { n: occurrences.length }) : t('events.series.emptyTitle') }}
      </h2>
      <button v-if="canAdd" type="button" class="text-xs text-blue-600 hover:underline" :disabled="adding" @click="add">
        {{ adding ? t('events.series.adding') : t('events.series.add') }}
      </button>
    </div>

    <p v-if="!occurrences.length" class="text-sm text-slate-500">{{ t('events.series.emptyLead') }}</p>

    <ul v-else class="grid gap-1.5">
      <li
        v-for="occurrence in occurrences"
        :key="occurrence.id"
        class="flex items-center gap-3 rounded-lg border px-3 py-2"
        :class="occurrence.isCurrent ? 'border-blue-200 bg-blue-50' : 'border-slate-100 bg-slate-50'"
      >
        <span
          class="h-2 w-2 shrink-0 rounded-full"
          :class="occurrence.status === 'published' ? 'bg-green-500' : occurrence.status === 'archived' ? 'bg-slate-400' : 'bg-amber-400'"
        />
        <RouterLink
          :to="`${prefix}/events/${occurrence.id}`"
          class="min-w-0 flex-1 truncate text-sm font-medium text-slate-900 no-underline hover:text-blue-700"
        >
          {{ occurrence.dateRangeLabel ?? t('events.series.noDate') }}
        </RouterLink>
        <span v-if="occurrence.isCurrent" class="shrink-0 text-xs font-semibold text-blue-700">
          {{ t('events.series.current') }}
        </span>
      </li>
    </ul>

    <p v-if="occurrences.length" class="mt-3 text-xs text-slate-500">{{ t('events.series.sharedHint') }}</p>

    <button
      v-if="occurrences.length && canAdd"
      type="button"
      class="mt-2 text-xs text-slate-500 hover:text-red-700 hover:underline"
      :disabled="detaching"
      @click="detach"
    >
      {{ t('events.series.detach') }}
    </button>
  </div>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { eventOccurrences, addEventOccurrence, detachEventFromSeries, type SeriesOccurrenceRow } from '@/api/events'
import { t } from '@/i18n'
import { useToast } from '@/composables/useToast'

const props = defineProps<{
  eventId: number
  /** Prefix ciest podľa režimu — `/dashboard` alebo `/admin`. */
  prefix: string
  /** Bez práva na založenie podujatia sa termín pridať nedá. */
  canAdd: boolean
}>()

const emit = defineEmits<{ changed: [] }>()

const router = useRouter()
const toast = useToast()

const occurrences = ref<SeriesOccurrenceRow[]>([])
const adding = ref(false)
const detaching = ref(false)

async function load() {
  occurrences.value = await eventOccurrences(props.eventId)
}

watch(() => props.eventId, load, { immediate: true })

/**
 * Nový termín vzniká bez dátumu a ako koncept — organizátor ho rovno otvorí
 * v úprave a doplní, kedy sa koná. Predvyplniť dátum by znamenalo hádať, či je
 * to o týždeň, o mesiac, alebo každý druhý štvrtok.
 */
async function add() {
  adding.value = true
  try {
    const created = await addEventOccurrence(props.eventId)
    toast.success(t('events.series.added'))
    emit('changed')
    router.push(`${props.prefix}/events/${created.id}/edit`)
  } catch (e: unknown) {
    toast.error((e as { response?: { data?: { message?: string } } })?.response?.data?.message ?? t('events.series.addFailed'))
  } finally {
    adding.value = false
  }
}

async function detach() {
  if (!window.confirm(t('events.series.detachConfirm'))) return

  detaching.value = true
  try {
    await detachEventFromSeries(props.eventId)
    toast.success(t('events.series.detached'))
    await load()
    emit('changed')
  } catch {
    toast.error(t('events.series.detachFailed'))
  } finally {
    detaching.value = false
  }
}
</script>
