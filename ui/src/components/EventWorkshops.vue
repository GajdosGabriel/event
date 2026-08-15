<template>
  <ul class="grid gap-2">
    <li v-for="w in sorted" :key="w.id"
      class="rounded-xl border bg-white px-4 py-3"
      :class="w.viewerJoined ? 'border-violet-300 bg-violet-50/40'
        : w.viewerWaitlisted ? 'border-amber-300 bg-amber-50/40' : 'border-slate-200'">
      <div class="flex flex-wrap items-start justify-between gap-x-3 gap-y-1">
        <p class="font-semibold text-slate-900">
          {{ w.name }}
          <span v-if="w.viewerJoined"
            class="ml-1 rounded-full bg-violet-100 px-2 py-0.5 text-xs font-medium text-violet-700">{{ t('public.workshops.joined') }}</span>
          <span v-else-if="w.viewerWaitlisted"
            class="ml-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">
            {{ t('public.workshops.waitlisted') }}<template v-if="w.viewerWaitlistPosition"> · {{ t('public.workshops.waitlistPosition', { n: w.viewerWaitlistPosition }) }}</template>
          </span>
          <span v-if="showInactive && !w.isActive"
            class="ml-1 rounded-full bg-slate-200 px-2 py-0.5 text-xs font-medium text-slate-600">{{ t('public.workshops.inactive') }}</span>
        </p>

        <!-- Cena + akcia (tlačidlo pod cenou, aby sme šetrili priestor) -->
        <div class="flex shrink-0 flex-col items-end gap-1.5">
          <p class="text-sm font-semibold" :class="w.priceAmount ? 'text-slate-800' : 'text-green-700'">
            {{ w.priceAmount ? formatPrice(w.priceAmount, w.priceCurrency) : t('common.free') }}
          </p>

          <template v-if="joinable && confirmingId !== w.id">
            <p v-if="locked" class="max-w-[13rem] text-right text-xs font-medium text-slate-500">
              {{ lockedMessage(w) }}
            </p>

            <!-- Má miesto -->
            <button v-else-if="w.viewerJoined" type="button"
              class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"
              @click="confirmingId = w.id ?? null">
              {{ t('public.workshops.leave') }}
            </button>

            <!-- Je náhradník -->
            <template v-else-if="w.viewerWaitlisted">
              <button type="button"
                class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                @click="confirmingId = w.id ?? null">
                {{ t('public.workshops.leaveWaitlist') }}
              </button>
              <span class="max-w-[13rem] text-right text-xs text-slate-500">{{ t('public.workshops.waitlistHint') }}</span>
            </template>

            <!-- Voľné miesto alebo čakačka -->
            <template v-else>
              <button type="button" :disabled="!canAct(w) || busyId === w.id"
                class="rounded-lg px-3 py-1.5 text-xs font-semibold text-white disabled:opacity-50"
                :class="isFull(w) ? 'bg-amber-600 hover:bg-amber-700' : 'bg-violet-600 hover:bg-violet-700'"
                @click="emit('join', w)">
                {{ busyId === w.id ? t('public.workshops.sending') : isFull(w) ? t('public.workshops.joinWaitlist') : t('public.workshops.join') }}
              </button>
              <span v-if="!authenticated" class="text-right text-xs text-slate-500">{{ t('public.workshops.loginFirst') }}</span>
              <span v-else-if="!viewerRegistered && !standalone && !w.openToPublic" class="max-w-[13rem] text-right text-xs text-slate-500">{{ t('public.workshops.registerFirst') }}</span>
              <span v-else-if="isFull(w)" class="max-w-[13rem] text-right text-xs text-slate-500">{{ t('public.workshops.fullHint') }}</span>
            </template>
          </template>
        </div>
      </div>

      <p v-if="timeLabel(w)" class="mt-0.5 text-sm font-medium text-violet-700">{{ timeLabel(w) }}</p>
      <p v-if="w.description" class="mt-1 text-sm leading-snug text-slate-600">{{ w.description }}</p>

      <p class="mt-1 text-xs text-slate-500">
        <template v-if="w.capacity !== null">
          {{ t('public.workshops.capacity', { n: w.capacity }) }}<span v-if="showInactive"> · {{ t('public.workshops.joinedCount', { n: w.soldCount ?? 0 }) }}</span>
          <span v-if="w.remainingCapacity !== null && w.remainingCapacity !== undefined">
            · {{ w.remainingCapacity > 0 ? t('public.workshops.remaining', { n: w.remainingCapacity }) : t('public.workshops.full') }}
          </span>
        </template>
        <template v-else>
          {{ t('public.workshops.unlimited') }}<span v-if="showInactive"> · {{ t('public.workshops.joinedCount', { n: w.soldCount ?? 0 }) }}</span>
        </template>
        <span v-if="w.waitlistCount"> · {{ w.waitlistCount }} {{ plural('public.workshops.counts.waiting', w.waitlistCount) }}</span>
      </p>

      <!-- Potvrdenie odhlásenia (inline, plná šírka pod kartou) -->
      <div v-if="joinable && confirmingId === w.id"
        class="mt-2 flex flex-wrap items-center gap-2 rounded-lg bg-amber-50 px-3 py-2">
        <span class="text-sm text-amber-900">
          {{ w.viewerWaitlisted
            ? t('public.workshops.confirmLeaveWaitlist', { name: w.name })
            : t('public.workshops.confirmLeave', { name: w.name }) }}
        </span>
        <button type="button" :disabled="busyId === w.id"
          class="rounded-lg bg-red-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-red-700 disabled:opacity-60"
          @click="confirmLeave(w)">
          {{ busyId === w.id ? t('public.workshops.leaving') : w.viewerWaitlisted ? t('public.workshops.confirmYesWaitlist') : t('public.workshops.confirmYes') }}
        </button>
        <button type="button" class="text-xs font-medium text-slate-600 hover:text-slate-900"
          @click="confirmingId = null">{{ t('common.cancel') }}</button>
      </div>
    </li>
  </ul>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { fmtDayTimeRange } from '@/utils/dateFormat'
import { formatPrice } from '@/utils/money'
import { useI18n } from '@/i18n'
import type { TicketTypeItem } from '@/types'

const { t, plural } = useI18n()

const props = defineProps<{
  workshops: TicketTypeItem[]
  /** V dashboarde/admine ukáž aj neaktívne workshopy a počet prihlásených. */
  showInactive?: boolean
  /** Verejná stránka: zobraz tlačidlá Prihlásiť/Odhlásiť. */
  joinable?: boolean
  authenticated?: boolean
  /** Má návštevník vstupenku na podujatie? */
  viewerRegistered?: boolean
  /** Podujatie nemá hlavný typ vstupenky — workshop je samostatná registrácia. */
  standalone?: boolean
  /** Podujatie začalo a organizátor zamkol zmeny. */
  locked?: boolean
  /** Id workshopu, na ktorom práve beží požiadavka. */
  busyId?: number | null
}>()

const emit = defineEmits<{
  join: [workshop: TicketTypeItem]
  leave: [workshop: TicketTypeItem]
}>()

const confirmingId = ref<number | null>(null)

// Podľa termínu; workshopy bez termínu na koniec.
const sorted = computed(() =>
  [...props.workshops].sort((a, b) => {
    if (!a.startsAt) return 1
    if (!b.startsAt) return -1
    return new Date(a.startsAt).getTime() - new Date(b.startsAt).getTime()
  }),
)

function isFull(w: TicketTypeItem): boolean {
  return w.remainingCapacity !== null && w.remainingCapacity !== undefined && w.remainingCapacity <= 0
}

/** Plný workshop neblokuje akciu — zaradí sa do čakačky. */
function canAct(w: TicketTypeItem): boolean {
  // Otvorený workshop nevyžaduje registráciu na podujatie.
  const hasAccess = Boolean(props.viewerRegistered) || Boolean(props.standalone) || Boolean(w.openToPublic)
  return Boolean(props.authenticated) && hasAccess && !props.locked
}

function lockedMessage(w: TicketTypeItem): string {
  if (w.viewerJoined) return t('public.workshops.lockedJoined')
  if (w.viewerWaitlisted) return t('public.workshops.lockedWaitlisted')
  return t('public.workshops.lockedJoin')
}

function confirmLeave(w: TicketTypeItem) {
  emit('leave', w)
  confirmingId.value = null
}

function timeLabel(w: TicketTypeItem): string {
  return fmtDayTimeRange(w.startsAt, w.endsAt)
}
</script>
