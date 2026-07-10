<template>
  <div class="mx-auto my-5 w-full max-w-[1000px] px-4">
    <EventTicketsTabs :event-id="eventId" />

    <div class="mb-4">
      <h1 class="text-2xl font-semibold text-slate-900">{{ eventName || 'Lístky a registrácia' }}</h1>
      <p v-if="eventName" class="text-sm text-slate-500">Lístky a registrácia</p>
    </div>

    <p v-if="loading" class="text-slate-500">Načítavam…</p>
    <p v-else-if="loadError" class="text-red-600">{{ loadError }}</p>

    <template v-else>
      <!-- Nastavenia predaja -->
      <section class="mb-6 rounded-2xl border border-slate-200 bg-white p-5">
        <h2 class="mb-3 text-lg font-semibold text-slate-800">Nastavenia</h2>
        <p class="mb-3 text-xs text-slate-500">
          Registrácia je pre návštevníkov dostupná automaticky, keď má podujatie aspoň jeden
          aktívny typ lístka (nižšie). Bez typov sa formulár na verejnej stránke nezobrazí.
        </p>
        <label class="mb-3 flex items-start gap-2 text-sm font-medium text-slate-700">
          <input v-model="settings.workshop_lock_on_start" type="checkbox" class="mt-0.5 accent-blue-600" />
          <span>
            Po začiatku podujatia zamknúť zmeny na workshopoch
            <span class="block text-xs font-normal text-slate-500">
              Účastníci sa po začiatku podujatia už nebudú môcť na workshopy prihlásiť ani odhlásiť.
              Po skončení podujatia sú zmeny zamknuté vždy.
            </span>
          </span>
        </label>
        <div class="mt-4">
          <button type="button" class="btn btn-primary" :disabled="savingSettings" @click="saveSettings">
            {{ savingSettings ? 'Ukladám…' : 'Uložiť nastavenia' }}
          </button>
        </div>
      </section>

      <!-- Typy lístkov -->
      <section class="rounded-2xl border border-slate-200 bg-white p-5">
        <div class="mb-3 flex items-center justify-between">
          <h2 class="text-lg font-semibold text-slate-800">Typy lístkov</h2>
          <RouterLink :to="{ name: 'dashboard-events-tickets-create', params: { id: eventId } }" class="btn btn-secondary">
            + Nový typ
          </RouterLink>
        </div>

        <p v-if="!types.length" class="text-sm text-slate-400">Zatiaľ žiadne typy lístkov. Pridajte prvý (napr. „Standard", „VIP", „Zdarma").</p>

        <div v-else class="overflow-hidden rounded-xl border border-slate-200">
          <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
              <tr>
                <th class="px-4 py-3">Názov</th>
                <th class="px-4 py-3">Cena</th>
                <th class="px-4 py-3">Kapacita</th>
                <th class="px-4 py-3">Predané</th>
                <th class="px-4 py-3">Stav</th>
                <th class="px-4 py-3"></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="t in types" :key="t.id">
                <td class="px-4 py-3 font-medium text-slate-900">
                  {{ t.name }}
                  <span v-if="t.kind === 'workshop'" class="ml-1 rounded-full bg-violet-100 px-2 py-0.5 text-xs font-medium text-violet-700">
                    Workshop<template v-if="t.openToPublic"> · otvorený</template>
                  </span>
                  <span v-if="t.requiresAttendeeName" class="ml-1 text-xs font-normal text-slate-400">(mená účastníkov)</span>
                </td>
                <td class="px-4 py-3 text-slate-600">{{ t.priceAmount ? formatPrice(t.priceAmount, t.priceCurrency) : 'Zdarma' }}</td>
                <td class="px-4 py-3 text-slate-600">{{ t.capacity ?? '∞' }}</td>
                <td class="px-4 py-3 text-slate-600">
                  {{ t.soldCount ?? 0 }}
                  <span v-if="t.waitlistCount" class="ml-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">
                    +{{ t.waitlistCount }} v čakačke
                  </span>
                </td>
                <td class="px-4 py-3">
                  <span class="rounded-full px-2 py-0.5 text-xs font-medium"
                    :class="t.isActive ? 'bg-green-100 text-green-700' : 'bg-slate-200 text-slate-600'">
                    {{ t.isActive ? 'Aktívny' : 'Neaktívny' }}
                  </span>
                </td>
                <td class="px-4 py-3 text-right whitespace-nowrap">
                  <RouterLink :to="{ name: 'dashboard-events-tickets-edit', params: { id: eventId, typeId: t.id } }" class="action-btn">
                    Upraviť
                  </RouterLink>
                  <button type="button" class="action-btn ml-1 text-red-600" @click="remove(t)">Zmazať</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </template>
  </div>
</template>

<script setup lang="ts">
import { reactive, ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { showEvent } from '@/api/events'
import { indexTicketTypes, deleteTicketType, updateTicketingSettings } from '@/api/ticketTypes'
import { useToast } from '@/composables/useToast'
import EventTicketsTabs from '@/components/EventTicketsTabs.vue'
import type { TicketTypeItem } from '@/types'

const route = useRoute()
const toast = useToast()
const eventId = Number(route.params.id)

const loading = ref(false)
const loadError = ref<string | null>(null)
const savingSettings = ref(false)
const eventName = ref('')

const settings = reactive({
  workshop_lock_on_start: true,
})

const types = ref<TicketTypeItem[]>([])

function formatPrice(amount: number, currency: string | null) {
  return new Intl.NumberFormat('sk-SK', { style: 'currency', currency: currency ?? 'EUR' }).format(amount / 100)
}

async function loadAll() {
  loading.value = true
  loadError.value = null
  try {
    const ev = await showEvent('dashboard', eventId)
    eventName.value = ev.name
    settings.workshop_lock_on_start = ev.workshopLockOnStart ?? true
    types.value = await indexTicketTypes(eventId)
  } catch {
    loadError.value = 'Údaje sa nepodarilo načítať.'
  } finally {
    loading.value = false
  }
}

async function saveSettings() {
  savingSettings.value = true
  try {
    await updateTicketingSettings(eventId, {
      workshop_lock_on_start: settings.workshop_lock_on_start,
    })
    toast.success('Nastavenia uložené.')
  } catch {
    toast.error('Uloženie nastavení zlyhalo.')
  } finally {
    savingSettings.value = false
  }
}

async function remove(t: TicketTypeItem) {
  if (!t.id || !confirm(`Naozaj zmazať typ lístka „${t.name}"?`)) return
  try {
    await deleteTicketType(eventId, t.id)
    types.value = await indexTicketTypes(eventId)
    toast.success('Typ lístka zmazaný.')
  } catch {
    toast.error('Zmazanie zlyhalo.')
  }
}

onMounted(loadAll)
</script>
