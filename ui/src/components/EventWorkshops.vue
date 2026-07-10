<template>
  <ul class="grid gap-2">
    <li v-for="w in sorted" :key="w.id"
      class="rounded-xl border bg-white px-4 py-3"
      :class="w.viewerJoined ? 'border-violet-300 bg-violet-50/40'
        : w.viewerWaitlisted ? 'border-amber-300 bg-amber-50/40' : 'border-slate-200'">
      <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
        <p class="font-semibold text-slate-900">
          {{ w.name }}
          <span v-if="w.viewerJoined"
            class="ml-1 rounded-full bg-violet-100 px-2 py-0.5 text-xs font-medium text-violet-700">Prihlásený</span>
          <span v-else-if="w.viewerWaitlisted"
            class="ml-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">
            Náhradník<template v-if="w.viewerWaitlistPosition"> · {{ w.viewerWaitlistPosition }}. v poradí</template>
          </span>
          <span v-if="showInactive && !w.isActive"
            class="ml-1 rounded-full bg-slate-200 px-2 py-0.5 text-xs font-medium text-slate-600">Neaktívny</span>
        </p>
        <p class="text-sm font-semibold" :class="w.priceAmount ? 'text-slate-800' : 'text-green-700'">
          {{ w.priceAmount ? formatPrice(w.priceAmount, w.priceCurrency) : 'Zdarma' }}
        </p>
      </div>

      <p v-if="timeLabel(w)" class="mt-0.5 text-sm font-medium text-violet-700">{{ timeLabel(w) }}</p>
      <p v-if="w.description" class="mt-1 text-sm leading-snug text-slate-600">{{ w.description }}</p>

      <p class="mt-1 text-xs text-slate-500">
        <template v-if="w.capacity !== null">
          Kapacita {{ w.capacity }}<span v-if="showInactive"> · prihlásených {{ w.soldCount ?? 0 }}</span>
          <span v-if="w.remainingCapacity !== null && w.remainingCapacity !== undefined">
            · {{ w.remainingCapacity > 0 ? `voľných ${w.remainingCapacity}` : 'obsadené' }}
          </span>
        </template>
        <template v-else>
          Bez obmedzenia kapacity<span v-if="showInactive"> · prihlásených {{ w.soldCount ?? 0 }}</span>
        </template>
        <span v-if="w.waitlistCount"> · {{ w.waitlistCount }} {{ waitingWord(w.waitlistCount) }}</span>
      </p>

      <!-- Prihlásenie / odhlásenie / čakačka -->
      <div v-if="joinable" class="mt-3">
        <!-- Potvrdenie odhlásenia (inline, bez popupu) -->
        <div v-if="confirmingId === w.id" class="flex flex-wrap items-center gap-2 rounded-lg bg-amber-50 px-3 py-2">
          <span class="text-sm text-amber-900">
            {{ w.viewerWaitlisted
              ? `Naozaj opustiť čakačku na „${w.name}"?`
              : `Naozaj sa odhlásiť z „${w.name}"? Miesto dostane prvý náhradník.` }}
          </span>
          <button type="button" :disabled="busyId === w.id"
            class="rounded-lg bg-red-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-red-700 disabled:opacity-60"
            @click="confirmLeave(w)">
            {{ busyId === w.id ? 'Odhlasujem…' : w.viewerWaitlisted ? 'Áno, opustiť' : 'Áno, odhlásiť' }}
          </button>
          <button type="button" class="text-xs font-medium text-slate-600 hover:text-slate-900"
            @click="confirmingId = null">Zrušiť</button>
        </div>

        <template v-else>
          <p v-if="locked" class="text-xs font-medium text-slate-500">
            {{ lockedMessage(w) }}
          </p>

          <!-- Má miesto -->
          <button v-else-if="w.viewerJoined" type="button"
            class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"
            @click="confirmingId = w.id ?? null">
            Odhlásiť sa
          </button>

          <!-- Je náhradník -->
          <div v-else-if="w.viewerWaitlisted" class="flex flex-wrap items-center gap-2">
            <button type="button"
              class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"
              @click="confirmingId = w.id ?? null">
              Opustiť čakačku
            </button>
            <span class="text-xs text-slate-500">Keď sa miesto uvoľní, pridelíme ti ho a pošleme e-mail.</span>
          </div>

          <!-- Voľné miesto alebo čakačka -->
          <div v-else class="flex flex-wrap items-center gap-2">
            <button type="button" :disabled="!canAct(w) || busyId === w.id"
              class="rounded-lg px-3 py-1.5 text-xs font-semibold text-white disabled:opacity-50"
              :class="isFull(w) ? 'bg-amber-600 hover:bg-amber-700' : 'bg-violet-600 hover:bg-violet-700'"
              @click="emit('join', w)">
              {{ busyId === w.id ? 'Odosielam…' : isFull(w) ? 'Zaradiť medzi náhradníkov' : 'Prihlásiť sa' }}
            </button>
            <span v-if="!authenticated" class="text-xs text-slate-500">Najprv sa prihlás do účtu.</span>
            <span v-else-if="!viewerRegistered && !standalone && !w.openToPublic" class="text-xs text-slate-500">Najprv sa registruj na podujatie.</span>
            <span v-else-if="isFull(w)" class="text-xs text-slate-500">Workshop je plný — pôjdeš do poradia.</span>
          </div>
        </template>
      </div>
    </li>
  </ul>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { fmtDayTimeRange } from '@/utils/dateFormat'
import type { TicketTypeItem } from '@/types'

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
  if (w.viewerJoined) return 'Podujatie už začalo — odhlásiť sa už nedá.'
  if (w.viewerWaitlisted) return 'Podujatie už začalo — čakačka sa už neposúva.'
  return 'Podujatie už začalo — prihlásiť sa už nedá.'
}

function waitingWord(count: number): string {
  if (count === 1) return 'náhradník'
  return count <= 4 ? 'náhradníci' : 'náhradníkov'
}

function confirmLeave(w: TicketTypeItem) {
  emit('leave', w)
  confirmingId.value = null
}

function timeLabel(w: TicketTypeItem): string {
  return fmtDayTimeRange(w.startsAt, w.endsAt)
}

function formatPrice(amount: number, currency: string | null) {
  return new Intl.NumberFormat('sk-SK', { style: 'currency', currency: currency ?? 'EUR' }).format(amount / 100)
}
</script>
