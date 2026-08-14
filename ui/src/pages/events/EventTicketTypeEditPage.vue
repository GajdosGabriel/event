<template>
  <div class="mx-auto my-5 w-full max-w-[1000px] px-4">
    <EventTicketsTabs :event-id="eventId" />

    <div class="mb-4">
      <h1 class="text-2xl font-semibold text-slate-900">
        {{ isEdit ? t('tickets.type.editTitle') : t('tickets.type.createTitle') }}
      </h1>
      <p v-if="eventName" class="text-sm text-slate-500">{{ eventName }}</p>
    </div>

    <p v-if="loading" class="text-slate-500">{{ t('tickets.type.loading') }}</p>
    <p v-else-if="loadError" class="text-red-600">{{ loadError }}</p>

    <template v-else>
      <!-- Šablóny — rýchly štart pri vytváraní. Predvyplnia názov a rozumné
           defaulty, aby používateľ nezačínal z prázdneho formulára. -->
      <section v-if="!isEdit" class="mb-5 rounded-2xl border border-slate-200 bg-white p-5">
        <h2 class="text-sm font-semibold text-slate-800">{{ t('tickets.type.templatesTitle') }}</h2>
        <p class="mb-3 text-xs text-slate-500">
          {{ t('tickets.type.templatesLead') }}
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
          <FormField v-model="form.name" :label="t('tickets.type.name')" required trim :placeholder="t('tickets.type.namePlaceholder')" class="sm:col-span-2" />
          <FormField v-model="priceEuro" type="number" :label="t('tickets.type.price')" min="0" step="0.01" placeholder="0" />
          <FormField v-model="form.capacity" type="number" :label="t('tickets.type.capacity')" min="1" :placeholder="t('tickets.type.capacityPlaceholder')" />
          <FormField v-model="form.is_active" type="checkbox" :label="t('tickets.type.isActive')" class="sm:col-span-2" />
        </div>

        <!-- Rozšírené nastavenia — schované, nech základ pôsobí jednoducho -->
        <button type="button"
          class="mt-4 flex items-center gap-1.5 text-sm font-medium text-slate-600 transition-colors hover:text-slate-900"
          @click="showAdvanced = !showAdvanced">
          <svg class="h-4 w-4 transition-transform" :class="{ 'rotate-90': showAdvanced }" viewBox="0 0 20 20" fill="currentColor">
            <path d="M7 5l6 5-6 5V5z" />
          </svg>
          {{ t('tickets.type.advanced') }}
        </button>

        <div v-show="showAdvanced" class="mt-3 grid grid-cols-1 gap-3 border-t border-slate-100 pt-4 sm:grid-cols-2">
          <FormField v-model="kindOption" type="select" :label="t('tickets.type.kind')" :options="kindOptions" class="sm:col-span-2" />
          <FormField v-model="form.description" :label="t('tickets.type.description')" trim :placeholder="t('tickets.type.descriptionPlaceholder')" class="sm:col-span-2" />
          <template v-if="form.kind === 'workshop'">
            <FormField v-model="form.starts_at" type="datetime" :label="tf('workshop_starts_at', t('tickets.type.workshopStart'))" />
            <FormField v-model="form.ends_at" type="datetime" :label="tf('workshop_ends_at', t('tickets.type.workshopEnd'))" />
          </template>
          <FormField v-model="form.min_per_order" type="number" :label="t('tickets.type.minPerOrder')" min="1" />
          <FormField v-model="form.max_per_order" type="number" :label="t('tickets.type.maxPerOrder')" min="1" />
          <FormField v-model="form.sale_starts_at" type="datetime" :label="tf('sale_starts_at', t('tickets.type.saleFrom'))" />
          <FormField v-model="form.sale_ends_at" type="datetime" :label="tf('sale_ends_at', t('tickets.type.saleTo'))" />
          <FormField v-model="form.requires_attendee_name" type="checkbox" :label="t('tickets.type.requiresAttendeeName')" class="sm:col-span-2" />
        </div>

        <div class="mt-6 flex gap-2">
          <button type="button" class="btn btn-primary" :disabled="saving" @click="save">
            {{ saving ? t('tickets.type.saving') : t('tickets.type.save') }}
          </button>
          <RouterLink :to="{ name: 'dashboard-events-tickets', params: { id: eventId } }" class="btn btn-secondary">
            {{ t('tickets.type.cancel') }}
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
import { t } from '@/i18n'
import { useToast } from '@/composables/useToast'
import { provideFormValidation } from '@/composables/useFormValidation'
import FormField from '@/components/FormField.vue'
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
/** Cena v eurách; API pracuje s centmi, prevod je až v `save()`. */
const priceEuro = ref<number | null>(null)

const validation = provideFormValidation()

// Možnosti „Druhu" a popisky polí drží backend (lang + policy). Fallback pre istotu.
const kindOptions = ref<SelectOption[]>([
  { value: 'ticket', label: t('tickets.type.kinds.ticket') },
  { value: 'workshop', label: t('tickets.type.kinds.workshop') },
  { value: 'workshop_open', label: t('tickets.type.kinds.workshopOpen') },
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
  price: number | null
}

// Popisky aj predvyplnený názov sú zo slovníka — `name` je obsah, ktorý sa
// uloží k lístku, takže má byť v jazyku toho, kto ho zakladá.
const templates = computed<TicketTemplate[]>(() => [
  { key: 'free',     icon: '🎟️', title: t('tickets.type.templates.free.title'),     subtitle: t('tickets.type.templates.free.subtitle'),     patch: { name: t('tickets.type.templates.free.name'), kind: 'ticket', requires_attendee_name: false }, price: 0 },
  { key: 'standard', icon: '🎫', title: t('tickets.type.templates.standard.title'), subtitle: t('tickets.type.templates.standard.subtitle'), patch: { name: t('tickets.type.templates.standard.name'), kind: 'ticket' }, price: null },
  { key: 'vip',      icon: '⭐', title: t('tickets.type.templates.vip.title'),      subtitle: t('tickets.type.templates.vip.subtitle'),      patch: { name: t('tickets.type.templates.vip.name'), kind: 'ticket' }, price: null },
  { key: 'workshop', icon: '🛠️', title: t('tickets.type.templates.workshop.title'), subtitle: t('tickets.type.templates.workshop.subtitle'), patch: { name: t('tickets.type.templates.workshop.name'), kind: 'workshop', requires_attendee_name: true }, price: null },
  { key: 'custom',   icon: '✏️', title: t('tickets.type.templates.custom.title'),   subtitle: t('tickets.type.templates.custom.subtitle'),   patch: { name: '' }, price: null },
])

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
      const type = list.find((x) => x.id === typeId)
      if (!type) {
        loadError.value = t('tickets.type.notFound')
        return
      }
      priceEuro.value = type.priceAmount ? type.priceAmount / 100 : null
      Object.assign(form, {
        name: type.name,
        kind: type.kind ?? 'ticket',
        open_to_public: type.openToPublic ?? false,
        description: type.description ?? '',
        starts_at: type.startsAt?.slice(0, 16) ?? '',
        ends_at: type.endsAt?.slice(0, 16) ?? '',
        capacity: type.capacity ?? null,
        min_per_order: type.minPerOrder,
        max_per_order: type.maxPerOrder,
        requires_attendee_name: type.requiresAttendeeName,
        is_active: type.isActive,
        sale_starts_at: type.saleStartsAt?.slice(0, 16) ?? '',
        sale_ends_at: type.saleEndsAt?.slice(0, 16) ?? '',
      })
    } else {
      // Predaj spravidla končí koncom akcie — predvyplň ako rozumný default.
      form.sale_ends_at = toInputDate(eventEndAt.value)
    }
  } catch {
    loadError.value = t('tickets.type.loadFailed')
  } finally {
    loading.value = false
  }
}

async function save() {
  validation.markValidated()
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
      price_amount: priceEuro.value ? Math.round(priceEuro.value * 100) : 0,
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
    toast.success(t('tickets.type.saved'))
    router.push({ name: 'dashboard-events-tickets', params: { id: eventId } })
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    error.value = err.response?.data?.message ?? t('tickets.type.saveFailed')
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>
