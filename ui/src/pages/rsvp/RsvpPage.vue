<template>
  <div class="mx-auto w-full max-w-md px-4 py-8">
    <div v-if="loading" class="flex items-center justify-center gap-2 py-16 text-slate-500">
      <span class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-slate-300 border-t-blue-600" />
      Načítavam…
    </div>

    <div v-else-if="notFound" class="rounded-2xl border border-red-200 bg-red-50 p-6 text-center">
      <p class="mb-2 text-lg font-semibold text-red-700">Odkaz je neplatný</p>
      <p class="mb-4 text-sm text-red-600">Tento odkaz na potvrdenie už neplatí alebo neexistuje.</p>
      <RouterLink to="/" class="text-sm text-blue-600 hover:underline">← Späť na úvod</RouterLink>
    </div>

    <div v-else-if="info" class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
      <!-- Hlavička -->
      <div class="p-6" :class="headerClass">
        <p class="text-xs font-semibold uppercase tracking-wider opacity-80">Potvrdenie účasti</p>
        <h1 class="mt-1 text-2xl font-bold">{{ info.event?.name }}</h1>
        <p v-if="info.event?.dateRangeLabel" class="mt-1 text-sm opacity-90">{{ info.event.dateRangeLabel }}</p>
      </div>

      <div class="space-y-5 p-6">
        <!-- Zhrnutie -->
        <div>
          <p class="text-sm text-slate-600">
            <strong>{{ info.holderName }}</strong> {{ info.isPaid ? 'ti objednal(a) vstupenku' : 'ti rezervoval(a) miesto' }}
            na toto podujatie.
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
            Potvrď prosím <strong>do {{ formatDateTime(info.deadlineAt) }}</strong>, inak sa rezervácia automaticky zruší.
          </p>

          <p v-if="actionError" class="text-sm text-red-600">{{ actionError }}</p>

          <div class="flex flex-col gap-2">
            <button type="button" :disabled="acting"
              class="w-full rounded-lg bg-green-600 px-4 py-3 text-sm font-semibold text-white hover:bg-green-700 disabled:opacity-60"
              @click="doConfirm">
              {{ acting ? 'Spracúvam…' : 'Potvrdiť účasť' }}
            </button>
            <button type="button" :disabled="acting"
              class="w-full rounded-lg border border-red-300 bg-white px-4 py-3 text-sm font-semibold text-red-600 hover:bg-red-50 disabled:opacity-60"
              @click="doDecline">
              Zrušiť lístok
            </button>
          </div>
        </template>

        <!-- Stav: potvrdené -->
        <div v-else-if="info.status === 'confirmed'" class="rounded-xl bg-green-50 p-4 text-center">
          <p class="text-2xl">✅</p>
          <p class="mt-1 font-semibold text-green-800">Účasť potvrdená</p>
          <p class="mt-1 text-sm text-green-700">Vstupenku s QR kódom sme ti poslali e-mailom. Uvidíme sa na akcii!</p>
        </div>

        <!-- Stav: zrušené / nepotvrdené -->
        <div v-else class="rounded-xl bg-slate-100 p-4 text-center">
          <p class="text-2xl">{{ info.status === 'expired' ? '⏰' : '❌' }}</p>
          <p class="mt-1 font-semibold text-slate-700">
            {{ info.status === 'expired' ? 'Lehota na potvrdenie uplynula' : 'Lístok zrušený' }}
          </p>
          <p class="mt-1 text-sm text-slate-500">Miesto sme uvoľnili ďalším záujemcom.</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { showRsvp, confirmRsvp, declineRsvp } from '@/api/rsvp'
import type { RsvpInfo } from '@/types'

const route = useRoute()
const token = route.params.token as string

const info = ref<RsvpInfo | null>(null)
const loading = ref(true)
const notFound = ref(false)
const acting = ref(false)
const actionError = ref<string | null>(null)

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
  return new Date(d).toLocaleString('sk-SK', { day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

async function doConfirm() {
  if (acting.value) return
  acting.value = true
  actionError.value = null
  try {
    info.value = await confirmRsvp(token)
  } catch {
    actionError.value = 'Akciu sa nepodarilo dokončiť. Skús to znova.'
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
    actionError.value = 'Akciu sa nepodarilo dokončiť. Skús to znova.'
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
