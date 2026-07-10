<template>
  <div class="mx-auto my-5 w-full max-w-[1000px] px-4">
    <EventTicketsTabs :event-id="eventId" />

    <h1 class="mb-4 text-2xl font-semibold text-slate-900">Lístky a registrácia</h1>

    <p v-if="loading" class="text-slate-500">Načítavam…</p>
    <p v-else-if="loadError" class="text-red-600">{{ loadError }}</p>

    <template v-else>
      <!-- Nastavenia predaja -->
      <section class="mb-6 rounded-2xl border border-slate-200 bg-white p-5">
        <h2 class="mb-3 text-lg font-semibold text-slate-800">Nastavenia</h2>
        <label class="mb-3 flex items-center gap-2 text-sm font-medium text-slate-700">
          <input v-model="settings.tickets_enabled" type="checkbox" class="accent-blue-600" />
          Povoliť registráciu / predaj lístkov
        </label>
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
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <label class="form-label">
            Celková kapacita (prázdne = neobmedzené)
            <input v-model.number="settings.capacity" type="number" min="1" class="form-input" placeholder="neobmedzené" />
          </label>
          <label class="form-label">
            Uzávierka registrácie
            <DateTimeInput v-model="settings.registration_deadline_at" class="form-input" />
          </label>
        </div>
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
          <button type="button" class="btn btn-secondary" @click="openCreate">+ Nový typ</button>
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
                  <span v-if="t.kind === 'workshop'" class="ml-1 rounded-full bg-violet-100 px-2 py-0.5 text-xs font-medium text-violet-700">Workshop</span>
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
                  <button type="button" class="action-btn" @click="openEdit(t)">Upraviť</button>
                  <button type="button" class="action-btn ml-1 text-red-600" @click="remove(t)">Zmazať</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </template>

    <!-- Modal typu lístka -->
    <Teleport to="body">
      <div v-if="modal.show" class="fixed inset-0 z-600 flex items-center justify-center bg-black/40 p-4" @mousedown.self="modal.show = false">
        <div class="w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-6 shadow-xl">
          <h2 class="mb-4 text-lg font-semibold text-slate-900">{{ modal.editing ? 'Upraviť typ lístka' : 'Nový typ lístka' }}</h2>
          <p v-if="modal.error" class="mb-3 text-sm text-red-600">{{ modal.error }}</p>
          <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <label class="form-label sm:col-span-2">
              Názov *
              <input v-model.trim="modal.form.name" type="text" class="form-input" placeholder="napr. Standard" />
            </label>
            <label class="form-label sm:col-span-2">
              Druh
              <select v-model="modal.form.kind" class="form-input">
                <option value="ticket">Vstupenka</option>
                <option value="workshop">Workshop (len pre registrovaných účastníkov)</option>
              </select>
            </label>
            <label class="form-label sm:col-span-2">
              Popis
              <input v-model.trim="modal.form.description" type="text" class="form-input" placeholder="nepovinné" />
            </label>
            <template v-if="modal.form.kind === 'workshop'">
              <label class="form-label">
                Začiatok workshopu
                <DateTimeInput v-model="modal.form.starts_at" class="form-input" />
              </label>
              <label class="form-label">
                Koniec workshopu
                <DateTimeInput v-model="modal.form.ends_at" class="form-input" />
              </label>
            </template>
            <label class="form-label">
              Cena (€, 0 = zdarma)
              <input v-model="modal.priceEuro" type="number" min="0" step="0.01" class="form-input" placeholder="0" />
            </label>
            <label class="form-label">
              Kapacita (prázdne = neobmedzené)
              <input v-model.number="modal.form.capacity" type="number" min="1" class="form-input" placeholder="neobmedzené" />
            </label>
            <label class="form-label">
              Min. na objednávku
              <input v-model.number="modal.form.min_per_order" type="number" min="1" class="form-input" />
            </label>
            <label class="form-label">
              Max. na objednávku
              <input v-model.number="modal.form.max_per_order" type="number" min="1" class="form-input" />
            </label>
            <label class="form-label">
              Predaj od
              <DateTimeInput v-model="modal.form.sale_starts_at" class="form-input" />
            </label>
            <label class="form-label">
              Predaj do
              <DateTimeInput v-model="modal.form.sale_ends_at" class="form-input" />
            </label>
            <label class="flex items-center gap-2 text-sm font-medium text-slate-700">
              <input v-model="modal.form.requires_attendee_name" type="checkbox" class="accent-blue-600" />
              Vyžadovať mená účastníkov
            </label>
            <label class="flex items-center gap-2 text-sm font-medium text-slate-700">
              <input v-model="modal.form.is_active" type="checkbox" class="accent-blue-600" />
              Aktívny (v predaji)
            </label>
          </div>
          <div class="mt-5 flex gap-2">
            <button type="button" class="btn btn-primary" :disabled="modal.saving" @click="saveType">
              {{ modal.saving ? 'Ukladám…' : 'Uložiť' }}
            </button>
            <button type="button" class="btn btn-secondary" @click="modal.show = false">Zrušiť</button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { reactive, ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { showEvent } from '@/api/events'
import {
  indexTicketTypes,
  createTicketType,
  updateTicketType,
  deleteTicketType,
  updateTicketingSettings,
  type TicketTypePayload,
} from '@/api/ticketTypes'
import { useToast } from '@/composables/useToast'
import DateTimeInput from '@/components/DateTimeInput.vue'
import EventTicketsTabs from '@/components/EventTicketsTabs.vue'
import type { TicketTypeItem } from '@/types'

const route = useRoute()
const toast = useToast()
const eventId = Number(route.params.id)

const loading = ref(false)
const loadError = ref<string | null>(null)
const savingSettings = ref(false)

const settings = reactive({
  tickets_enabled: false,
  workshop_lock_on_start: true,
  capacity: null as number | null,
  registration_deadline_at: '' as string,
})

const types = ref<TicketTypeItem[]>([])

const modal = reactive({
  show: false,
  editing: null as number | null,
  saving: false,
  error: null as string | null,
  priceEuro: '' as string,
  form: emptyTypeForm(),
})

function emptyTypeForm() {
  return {
    name: '',
    kind: 'ticket' as 'ticket' | 'workshop',
    description: '',
    starts_at: '' as string,
    ends_at: '' as string,
    capacity: null as number | null,
    min_per_order: 1,
    max_per_order: 10,
    requires_attendee_name: false,
    is_active: true,
    sale_starts_at: '' as string,
    sale_ends_at: '' as string,
  }
}

function formatPrice(amount: number, currency: string | null) {
  return new Intl.NumberFormat('sk-SK', { style: 'currency', currency: currency ?? 'EUR' }).format(amount / 100)
}

async function loadAll() {
  loading.value = true
  loadError.value = null
  try {
    const ev = await showEvent('dashboard', eventId)
    settings.tickets_enabled = ev.ticketsEnabled ?? false
    settings.workshop_lock_on_start = ev.workshopLockOnStart ?? true
    settings.capacity = ev.capacity ?? null
    settings.registration_deadline_at = ev.registrationDeadlineAt?.slice(0, 16) ?? ''
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
      tickets_enabled: settings.tickets_enabled,
      workshop_lock_on_start: settings.workshop_lock_on_start,
      capacity: settings.capacity || null,
      registration_deadline_at: settings.registration_deadline_at || null,
    })
    toast.success('Nastavenia uložené.')
  } catch {
    toast.error('Uloženie nastavení zlyhalo.')
  } finally {
    savingSettings.value = false
  }
}

function openCreate() {
  modal.editing = null
  modal.error = null
  modal.priceEuro = ''
  modal.form = emptyTypeForm()
  modal.show = true
}

function openEdit(t: TicketTypeItem) {
  modal.editing = t.id ?? null
  modal.error = null
  modal.priceEuro = t.priceAmount ? (t.priceAmount / 100).toString() : ''
  modal.form = {
    name: t.name,
    kind: t.kind ?? 'ticket',
    description: t.description ?? '',
    starts_at: t.startsAt?.slice(0, 16) ?? '',
    ends_at: t.endsAt?.slice(0, 16) ?? '',
    capacity: t.capacity ?? null,
    min_per_order: t.minPerOrder,
    max_per_order: t.maxPerOrder,
    requires_attendee_name: t.requiresAttendeeName,
    is_active: t.isActive,
    sale_starts_at: t.saleStartsAt?.slice(0, 16) ?? '',
    sale_ends_at: t.saleEndsAt?.slice(0, 16) ?? '',
  }
  modal.show = true
}

async function saveType() {
  modal.saving = true
  modal.error = null
  try {
    const payload: TicketTypePayload = {
      name: modal.form.name,
      kind: modal.form.kind,
      description: modal.form.description || null,
      starts_at: modal.form.kind === 'workshop' ? modal.form.starts_at || null : null,
      ends_at: modal.form.kind === 'workshop' ? modal.form.ends_at || null : null,
      price_amount: modal.priceEuro ? Math.round(parseFloat(modal.priceEuro) * 100) : 0,
      capacity: modal.form.capacity || null,
      min_per_order: modal.form.min_per_order,
      max_per_order: modal.form.max_per_order,
      requires_attendee_name: modal.form.requires_attendee_name,
      is_active: modal.form.is_active,
      sale_starts_at: modal.form.sale_starts_at || null,
      sale_ends_at: modal.form.sale_ends_at || null,
    }
    if (modal.editing) {
      await updateTicketType(eventId, modal.editing, payload)
    } else {
      await createTicketType(eventId, payload)
    }
    modal.show = false
    types.value = await indexTicketTypes(eventId)
    toast.success('Typ lístka uložený.')
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    modal.error = err.response?.data?.message ?? 'Uloženie zlyhalo.'
  } finally {
    modal.saving = false
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
