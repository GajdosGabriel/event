<template>
  <div class="mx-auto w-full max-w-md px-4 py-8">
    <div v-if="loading" class="flex items-center justify-center gap-2 py-16 text-slate-500">
      <span class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-slate-300 border-t-blue-600" />
      {{ t('rsvp.loading') }}
    </div>

    <div v-else-if="notFound" class="rounded-2xl border border-red-200 bg-red-50 p-6 text-center">
      <p class="mb-2 text-lg font-semibold text-red-700">{{ t('rsvp.invalidTitle') }}</p>
      <p class="mb-4 text-sm text-red-600">{{ t('rsvp.invalidLead') }}</p>
      <RouterLink to="/" class="text-sm text-blue-600 hover:underline">{{ t('rsvp.home') }}</RouterLink>
    </div>

    <div v-else-if="info" class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
      <!-- Hlavička -->
      <div class="p-6" :class="headerClass">
        <p class="text-xs font-semibold uppercase tracking-wider opacity-80">
          {{ isWaitlistOffer ? t('rsvp.waitlistKicker') : t('rsvp.kicker') }}
        </p>
        <h1 class="mt-1 text-2xl font-bold">{{ info.event?.name }}</h1>
        <p v-if="info.event?.dateRangeLabel" class="mt-1 text-sm opacity-90">{{ info.event.dateRangeLabel }}</p>
      </div>

      <div class="space-y-5 p-6">
        <!-- Zhrnutie -->
        <div>
          <p v-if="isWaitlistOffer" class="text-sm text-slate-600">
            {{ t('rsvp.waitlistLead') }}
          </p>
          <p v-else class="text-sm text-slate-600">
            <strong>{{ info.holderName }}</strong> {{ info.isPaid ? t('rsvp.orderedFor') : t('rsvp.reservedFor') }}
            {{ t('rsvp.onThisEvent') }}
          </p>
          <ul class="mt-3 space-y-1">
            <li v-for="(seat, i) in info.seats" :key="i" class="flex items-center gap-2 text-sm text-slate-800">
              <span class="text-slate-400">🎫</span>
              <span class="font-medium">{{ seat.label }}</span>
              <span v-if="seat.type" class="text-xs text-slate-500">· {{ seat.type }}</span>
            </li>
          </ul>
        </div>

        <!-- Stav: čaká na potvrdenie -->
        <template v-if="info.status === 'pending'">
          <p v-if="info.deadlineAt" class="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800">
            {{ t('rsvp.deadlineBefore') }} <strong>{{ formatDateTime(info.deadlineAt) }}</strong>,
            {{ isWaitlistOffer ? t('rsvp.deadlineWaitlist') : t('rsvp.deadlineReservation') }}
          </p>

          <p v-if="actionError" class="text-sm text-red-600">{{ actionError }}</p>

          <div class="flex flex-col gap-2">
            <button type="button" :disabled="acting"
              class="w-full rounded-lg bg-green-600 px-4 py-3 text-sm font-semibold text-white hover:bg-green-700 disabled:opacity-60"
              @click="doConfirm">
              {{ acting
                ? t('rsvp.acting')
                : isWaitlistOffer ? t('rsvp.confirmSeat') : t('rsvp.confirmAttendance') }}
            </button>
            <button type="button" :disabled="acting"
              class="w-full rounded-lg border border-red-300 bg-white px-4 py-3 text-sm font-semibold text-red-600 hover:bg-red-50 disabled:opacity-60"
              @click="doDecline">
              {{ isWaitlistOffer ? t('rsvp.declineSeat') : t('rsvp.declineTicket') }}
            </button>
          </div>
        </template>

        <!-- Stav: potvrdené -->
        <template v-else-if="info.status === 'confirmed'">
          <div class="rounded-xl bg-green-50 p-4 text-center">
            <p class="text-2xl">✅</p>
            <p class="mt-1 font-semibold text-green-800">{{ t('rsvp.confirmedTitle') }}</p>
            <p class="mt-1 text-sm text-green-700">{{ t('rsvp.confirmedLead') }}</p>
          </div>

          <!-- Bezplatnú vstupenku môžeš ešte zrušiť (odkaz z e-mailu so vstupenkou). -->
          <template v-if="info.canCancel">
            <p v-if="actionError" class="text-sm text-red-600">{{ actionError }}</p>
            <div class="text-center">
              <p class="text-xs text-slate-500">{{ t('rsvp.cancelHint') }}</p>
              <button type="button" :disabled="acting"
                class="mt-2 text-sm font-semibold text-red-600 hover:underline disabled:opacity-60"
                @click="doDecline">
                {{ acting ? t('rsvp.acting') : t('rsvp.cancelTicket') }}
              </button>
            </div>
          </template>
        </template>

        <!-- Stav: zrušené / nepotvrdené -->
        <div v-else class="rounded-xl bg-slate-100 p-4 text-center">
          <p class="text-2xl">{{ info.status === 'expired' ? '⏰' : '❌' }}</p>
          <p class="mt-1 font-semibold text-slate-700">
            {{ info.status === 'expired'
              ? t('rsvp.expiredTitle')
              : isWaitlistOffer ? t('rsvp.declinedSeat') : t('rsvp.declinedTicket') }}
          </p>
          <p class="mt-1 text-sm text-slate-500">
            {{ isWaitlistOffer ? t('rsvp.releasedWaitlist') : t('rsvp.released') }}
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { showRsvp, confirmRsvp, declineRsvp } from '@/api/rsvp'
import { currentLocale, t } from '@/i18n'
import type { RsvpInfo } from '@/types'

const route = useRoute()
const token = route.params.token as string

const info = ref<RsvpInfo | null>(null)
const loading = ref(true)
const notFound = ref(false)
const acting = ref(false)
const actionError = ref<string | null>(null)

/** Ponuka uvoľneného miesta z čakačky — hovorí o mieste, nie o cudzej rezervácii. */
const isWaitlistOffer = computed(() => info.value?.reason === 'waitlist')

const headerClass = computed(() => {
  switch (info.value?.status) {
    case 'confirmed': return 'bg-linear-to-br from-green-600 to-green-800 text-white'
    case 'declined':
    case 'expired': return 'bg-linear-to-br from-slate-500 to-slate-700 text-white'
    default: return 'bg-linear-to-br from-blue-600 to-blue-800 text-white'
  }
})

function formatDateTime(d: string | null) {
  if (!d) return ''
  return new Date(d).toLocaleString(currentLocale(), { day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

async function doConfirm() {
  if (acting.value) return
  acting.value = true
  actionError.value = null
  try {
    info.value = await confirmRsvp(token)
  } catch {
    actionError.value = t('rsvp.failed')
  } finally {
    acting.value = false
  }
}

async function doDecline() {
  if (acting.value) return
  acting.value = true
  actionError.value = null
  try {
    info.value = await declineRsvp(token)
  } catch {
    actionError.value = t('rsvp.failed')
  } finally {
    acting.value = false
  }
}

onMounted(async () => {
  try {
    info.value = await showRsvp(token)
  } catch {
    notFound.value = true
    loading.value = false
    return
  }
  loading.value = false

  // Jeden klik z e-mailu: ak odkaz niesol akciu a rezervácia ešte čaká, vykonáme ju.
  if (info.value.status === 'pending') {
    const action = route.query.do
    if (action === 'confirm') await doConfirm()
    else if (action === 'cancel') await doDecline()
  }
})
</script>
