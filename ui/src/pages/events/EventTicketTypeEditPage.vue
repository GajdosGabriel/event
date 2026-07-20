<template>
  <div class="mx-auto my-5 w-full max-w-[1000px] px-4">
    <EventTicketsTabs :event-id="eventId" />

    <div class="mb-4">
      <h1 class="text-2xl font-semibold text-slate-900">{{ isEdit ? 'Upraviť typ lístka' : 'Nový typ lístka' }}</h1>
      <p v-if="eventName" class="text-sm text-slate-500">{{ eventName }}</p>
    </div>

    <p v-if="loading" class="text-slate-500">Načítavam…</p>
    <p v-else-if="loadError" class="text-red-600">{{ loadError }}</p>

    <template v-else>
      <!-- Šablóny — rýchly štart pri vytváraní. Predvyplnia názov a rozumné
           defaulty, aby používateľ nezačínal z prázdneho formulára. -->
      <section v-if="!isEdit" class="mb-5 rounded-2xl border border-slate-200 bg-white p-5">
        <h2 class="text-sm font-semibold text-slate-800">Začnite šablónou</h2>
        <p class="mb-3 text-xs text-slate-500">
          Vyberte typ lístka — predvyplníme názov aj nastavenia. Všetko sa dá nižšie doladiť.
        </p>
        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-5">
          <button v-for="tpl in templates" :key="tpl.key" type="button"
            class="flex flex-col items-start gap-0.5 rounded-xl border p-3 text-left transition-colors"
            :class="selectedTemplate === tpl.key
              ? 'border-blue-500 bg-blue-50 ring-1 ring-blue-500'
              : 'border-slate-200 hover:border-blue-300 hover:bg-slate-50'"
            @click="applyTemplate(tpl)">
            <span class="text-xl leading-none">{{ tpl.icon }}</span>
            <span class="mt-1 text-sm font-semibold text-slate-800">{{ tpl.title }}</span>
            <span class="text-xs leading-snug text-slate-500">{{ tpl.subtitle }}</span>
          </button>
        </div>
      </section>

      <section class="rounded-2xl border border-slate-200 bg-white p-5">
        <p v-if="error" class="mb-3 text-sm text-red-600">{{ error }}</p>

        <!-- Základné polia — vždy viditeľné -->
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <label class="form-label sm:col-span-2">
            Názov *
            <input v-model.trim="form.name" type="text" class="form-input" placeholder="napr. Štandard" />
          </label>
          <label class="form-label">
            Cena (€, 0 = zdarma)
            <input v-model="priceEuro" type="number" min="0" step="0.01" class="form-input" placeholder="0" />
          </label>
          <label class="form-label">
            Kapacita (prázdne = neobmedzené)
            <input v-model.number="form.capacity" type="number" min="1" class="form-input" placeholder="neobmedzené" />
          </label>
          <label class="flex items-center gap-2 text-sm font-medium text-slate-700 sm:col-span-2">
            <input v-model="form.is_active" type="checkbox" class="accent-blue-600" />
            Aktívny (v predaji)
          </label>
        </div>

        <!-- Rozšírené nastavenia — schované, nech základ pôsobí jednoducho -->
        <button type="button"
          class="mt-4 flex items-center gap-1.5 text-sm font-medium text-slate-600 transition-colors hover:text-slate-900"
          @click="showAdvanced = !showAdvanced">
          <svg class="h-4 w-4 transition-transform" :class="{ 'rotate-90': showAdvanced }" viewBox="0 0 20 20" fill="currentColor">
            <path d="M7 5l6 5-6 5V5z" />
          </svg>
          Rozšírené nastavenia
        </button>

        <div v-show="showAdvanced" class="mt-3 grid grid-cols-1 gap-3 border-t border-slate-100 pt-4 sm:grid-cols-2">
          <label class="form-label sm:col-span-2">
            Druh
            <select v-model="kindOption" class="form-input">
              <option v-for="opt in kindOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
          </label>
          <label class="form-label sm:col-span-2">
            Popis
            <input v-model.trim="form.description" type="text" class="form-input" placeholder="nepovinné" />
          </label>
          <template v-if="form.kind === 'workshop'">
            <label class="form-label">
              {{ tf('workshop_starts_at', 'Začiatok workshopu') }}
              <DateTimeInput v-model="form.starts_at" class="form-input" />
            </label>
            <label class="form-label">
              {{ tf('workshop_ends_at', 'Koniec workshopu') }}
              <DateTimeInput v-model="form.ends_at" class="form-input" />
            </label>
          </template>
          <label class="form-label">
            Min. na objednávku
            <input v-model.number="form.min_per_order" type="number" min="1" class="form-input" />
          </label>
          <label class="form-label">
            Max. na objednávku
            <input v-model.number="form.max_per_order" type="number" min="1" class="form-input" />
          </label>
          <label class="form-label">
            {{ tf('sale_starts_at', 'Predaj od') }}
            <DateTimeInput v-model="form.sale_starts_at" class="form-input" />
          </label>
          <label class="form-label">
            {{ tf('sale_ends_at', 'Predaj do') }}
            <DateTimeInput v-model="form.sale_ends_at" class="form-input" />
          </label>
          <label class="flex items-center gap-2 text-sm font-medium text-slate-700 sm:col-span-2">
            <input v-model="form.requires_attendee_name" type="checkbox" class="accent-blue-600" />
            Vyžadovať mená účastníkov
          </label>
        </div>

        <div class="mt-6 flex gap-2">
          <button type="button" class="btn btn-primary" :disabled="saving" @click="save">
            {{ saving ? 'Ukladám…' : 'Uložiť' }}
          </button>
          <RouterLink :to="{ name: 'dashboard-events-tickets', params: { id: eventId } }" class="btn btn-secondary">
            Zrušiť
          </RouterLink>
        </div>
      </section>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, reactive, ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { showEvent } from '@/api/events'
import {
  indexTicketTypes,
  createTicketType,
  updateTicketType,
  type TicketTypePayload,
} from '@/api/ticketTypes'
import { useToast } from '@/composables/useToast'
import DateTimeInput from '@/components/DateTimeInput.vue'
import EventTicketsTabs from '@/components/EventTicketsTabs.vue'
import type { SelectOption } from '@/types'

const route = useRoute()
const router = useRouter()
const toast = useToast()

const eventId = Number(route.params.id)
const typeId = route.params.typeId ? Number(route.params.typeId) : null
const isEdit = computed(() => typeId !== null)

const loading = ref(true)
const loadError = ref<string | null>(null)
const saving = ref(false)
const error = ref<string | null>(null)
const eventName = ref('')
const priceEuro = ref('')

// Možnosti „Druhu" a popisky polí drží backend (lang + policy). Fallback pre istotu.
const kindOptions = ref<SelectOption[]>([
  { value: 'ticket', label: 'Vstupenka' },
  { value: 'workshop', label: 'Workshop (len pre registrovaných účastníkov)' },
  { value: 'workshop_open', label: 'Workshop (aj pre neregistrovaných na evente)' },
])
const labels = ref<Record<string, string>>({})
function tf(key: string, fallback: string): string {
  return labels.value[key] ?? fallback
}

// Termíny akcie — z nich predvyplňujeme workshop a koniec predaja.
const eventStartAt = ref<string | null>(null)
const eventEndAt = ref<string | null>(null)
/** ISO dátum z akcie na formát <input type="datetime-local">. */
function toInputDate(iso: string | null): string {
  return iso ? iso.slice(0, 16) : ''
}

const form = reactive(emptyTypeForm())

// Rozšírené polia sú pri vytváraní schované (jednoduchý štart), pri úprave
// otvorené — kto edituje existujúci typ, chce vidieť všetko.
const showAdvanced = ref(isEdit.value)

// ── Šablóny typov lístkov ───────────────────────────────────
type TypeForm = ReturnType<typeof emptyTypeForm>
interface TicketTemplate {
  key: string
  icon: string
  title: string
  subtitle: string
  patch: Partial<TypeForm>
  price: string
}

const templates: TicketTemplate[] = [
  { key: 'free',     icon: '🎟️', title: 'Vstup zdarma', subtitle: 'Bezplatná registrácia',       patch: { name: 'Vstup zdarma', kind: 'ticket', requires_attendee_name: false }, price: '0' },
  { key: 'standard', icon: '🎫', title: 'Štandard',     subtitle: 'Bežná platená vstupenka',      patch: { name: 'Štandardný lístok', kind: 'ticket' }, price: '' },
  { key: 'vip',      icon: '⭐', title: 'VIP',          subtitle: 'Prémiová vstupenka',           patch: { name: 'VIP', kind: 'ticket' }, price: '' },
  { key: 'workshop', icon: '🛠️', title: 'Workshop',     subtitle: 'Pre prihlásených účastníkov',  patch: { name: 'Workshop', kind: 'workshop', requires_attendee_name: true }, price: '' },
  { key: 'custom',   icon: '✏️', title: 'Vlastný',      subtitle: 'Začať od nuly',                patch: { name: '' }, price: '' },
]

const selectedTemplate = ref<string | null>(null)

function applyTemplate(tpl: TicketTemplate) {
  const base = emptyTypeForm()
  // Zachovaj rozumný default konca predaja (koniec akcie), tak ako pri prvom načítaní.
  base.sale_ends_at = toInputDate(eventEndAt.value)
  Object.assign(base, tpl.patch)
  if (base.kind === 'workshop') {
    base.starts_at = toInputDate(eventStartAt.value)
    base.ends_at = toInputDate(eventEndAt.value)
    showAdvanced.value = true // ukáž časy workshopu hneď
  }
  Object.assign(form, base)
  priceEuro.value = tpl.price
  selectedTemplate.value = tpl.key
}

function emptyTypeForm() {
  return {
    name: '',
    kind: 'ticket' as 'ticket' | 'workshop',
    open_to_public: false,
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

// „Druh" v UI má 3 možnosti; v dátach je to kind (ticket/workshop) + príznak
// open_to_public. Proxy ich mapuje obojsmerne.
const kindOption = computed<'ticket' | 'workshop' | 'workshop_open'>({
  get() {
    if (form.kind !== 'workshop') return 'ticket'
    return form.open_to_public ? 'workshop_open' : 'workshop'
  },
  set(value) {
    form.kind = value === 'ticket' ? 'ticket' : 'workshop'
    form.open_to_public = value === 'workshop_open'
    // Workshop sa spravidla koná počas akcie — predvyplň jeho termín z akcie
    // (pre obe varianty), ak ho používateľ ešte nevyplnil.
    if (form.kind === 'workshop') {
      if (!form.starts_at) form.starts_at = toInputDate(eventStartAt.value)
      if (!form.ends_at) form.ends_at = toInputDate(eventEndAt.value)
    }
  },
})

async function load() {
  loading.value = true
  loadError.value = null
  try {
    const ev = await showEvent('dashboard', eventId)
    eventName.value = ev.name
    if (ev.ticketTypeKindOptions?.length) kindOptions.value = ev.ticketTypeKindOptions
    if (ev.ticketTypeLabels) labels.value = ev.ticketTypeLabels
    eventStartAt.value = ev.startAt
    eventEndAt.value = ev.endAt

    if (isEdit.value) {
      const list = await indexTicketTypes(eventId)
      const t = list.find((x) => x.id === typeId)
      if (!t) {
        loadError.value = 'Typ lístka sa nenašiel.'
        return
      }
      priceEuro.value = t.priceAmount ? (t.priceAmount / 100).toString() : ''
      Object.assign(form, {
        name: t.name,
        kind: t.kind ?? 'ticket',
        open_to_public: t.openToPublic ?? false,
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
      })
    } else {
      // Predaj spravidla končí koncom akcie — predvyplň ako rozumný default.
      form.sale_ends_at = toInputDate(eventEndAt.value)
    }
  } catch {
    loadError.value = 'Údaje sa nepodarilo načítať.'
  } finally {
    loading.value = false
  }
}

async function save() {
  saving.value = true
  error.value = null
  try {
    const payload: TicketTypePayload = {
      name: form.name,
      kind: form.kind,
      open_to_public: form.kind === 'workshop' ? form.open_to_public : false,
      description: form.description || null,
      starts_at: form.kind === 'workshop' ? form.starts_at || null : null,
      ends_at: form.kind === 'workshop' ? form.ends_at || null : null,
      price_amount: priceEuro.value ? Math.round(parseFloat(priceEuro.value) * 100) : 0,
      capacity: form.capacity || null,
      min_per_order: form.min_per_order,
      max_per_order: form.max_per_order,
      requires_attendee_name: form.requires_attendee_name,
      is_active: form.is_active,
      sale_starts_at: form.sale_starts_at || null,
      sale_ends_at: form.sale_ends_at || null,
    }
    if (typeId !== null) {
      await updateTicketType(eventId, typeId, payload)
    } else {
      await createTicketType(eventId, payload)
    }
    toast.success('Typ lístka uložený.')
    router.push({ name: 'dashboard-events-tickets', params: { id: eventId } })
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    error.value = err.response?.data?.message ?? 'Uloženie zlyhalo.'
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>
