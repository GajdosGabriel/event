<template>
  <div class="grid gap-4">
    <div class="flex items-center justify-between gap-3">
      <h1 class="text-2xl font-semibold text-slate-900">Oznamy</h1>
      <button class="btn btn-primary" @click="openCreate">+ Nový oznam</button>
    </div>

    <p v-if="loading" class="text-slate-600">Načítavam…</p>

    <div v-else class="panel-card">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-slate-200 text-left text-xs uppercase text-slate-500">
            <th class="pb-2 pr-4">Názov</th>
            <th class="pb-2 pr-4">Umiestnenie</th>
            <th class="pb-2 pr-4">Stav</th>
            <th class="pb-2 pr-4">Zobrazovanie</th>
            <th class="pb-2">Akcie</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in items" :key="item.id" class="border-b border-slate-100 last:border-0 align-top">
            <td class="py-2 pr-4">
              <div class="font-medium text-slate-900">{{ item.title }}</div>
              <div class="text-slate-500">{{ plainBody(item.body) || '—' }}</div>
            </td>
            <td class="py-2 pr-4 text-slate-600">
              <span class="announcement-chip" :class="`announcement-${item.variant}`">{{ placementLabel(item.placement) }}</span>
            </td>
            <td class="py-2 pr-4">
              <!-- Vypnutie je zmena stavu, nie mazanie — text ostane uložený. -->
              <button
                type="button"
                class="status-toggle"
                :class="isVisible(item) ? 'status-toggle-on' : 'status-toggle-off'"
                :title="isVisible(item) ? 'Vypnúť zobrazovanie — text ostane uložený' : 'Zapnúť zobrazovanie'"
                @click="toggle(item)"
              >
                <span class="status-dot" :class="isVisible(item) ? 'bg-green-500' : 'bg-slate-400'" />
                {{ isVisible(item) ? 'zobrazuje sa' : 'vypnutý' }}
              </button>
            </td>
            <td class="py-2 pr-4 text-slate-600">
              <div>Od: {{ item.publishedFrom ?? '—' }}</div>
              <div>Do: {{ item.publishedUntil ?? '—' }}</div>
            </td>
            <td class="py-2 flex gap-2">
              <button class="action-btn" @click="openEdit(item)">Upraviť</button>
              <button class="action-btn action-btn-danger" @click="remove(item)">Zmazať</button>
            </td>
          </tr>
          <tr v-if="items.length === 0">
            <td colspan="5" class="py-4 text-slate-500">Žiadne oznamy.</td>
          </tr>
        </tbody>
      </table>

      <div v-if="meta.last_page > 1" class="mt-4 flex items-center gap-2">
        <button class="btn btn-secondary" :disabled="meta.current_page <= 1" @click="loadPage(meta.current_page - 1)">‹</button>
        <span class="text-sm text-slate-600">{{ meta.current_page }} / {{ meta.last_page }}</span>
        <button class="btn btn-secondary" :disabled="meta.current_page >= meta.last_page" @click="loadPage(meta.current_page + 1)">›</button>
      </div>
    </div>

    <div v-if="showForm" class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/40 p-4">
      <div class="w-full max-w-2xl rounded-2xl border border-slate-200 bg-white p-5 shadow-xl">
        <h2 class="mb-3 text-lg font-semibold">{{ editingItem ? 'Upraviť oznam' : 'Nový oznam' }}</h2>
        <p v-if="formError" class="mb-2 text-sm text-red-600">{{ formError }}</p>

        <div class="grid gap-3 md:grid-cols-2">
          <FormField v-model="form.title" label="Názov" required />

          <FormField v-model="form.placement" type="select" label="Umiestnenie" :options="options.placements" />

          <FormField label="Text" class="md:col-span-2">
            <HtmlEditor v-model="form.body" placeholder="Text oznamu…" min-height="6rem" />
          </FormField>

          <FormField v-model="form.variant" type="select" label="Farba" :options="options.variants" />

          <FormField v-model="form.status" type="select" label="Stav" :options="options.statuses" />

          <FormField v-model="form.published_from" type="datetime" label="Zobrazovať od" allow-past />

          <FormField v-model="form.published_until" type="datetime" label="Zobrazovať do" allow-past />

          <FormField v-model="form.sort_order" type="number" label="Poradie" min="0" />
        </div>

        <div class="mt-4 grid gap-1">
          <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Náhľad</span>
          <div class="announcement-preview" :class="`announcement-${form.variant}`">
            <strong>{{ form.title || 'Názov oznamu' }}</strong>
            <div v-if="form.body" class="prose prose-sm max-w-none" v-html="form.body" />
          </div>
        </div>

        <div class="mt-4 flex gap-2">
          <button class="btn btn-primary" :disabled="saving" @click="save">{{ saving ? 'Ukladám…' : 'Uložiť' }}</button>
          <button class="btn btn-secondary" @click="showForm = false">Zrušiť</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import {
  indexAnnouncements,
  createAnnouncement,
  updateAnnouncement,
  deleteAnnouncement,
  toPayload,
} from '@/api/announcements'
import type { AnnouncementItem, AnnouncementFormOptions, AnnouncementPayload } from '@/api/announcements'
import FormField from '@/components/FormField.vue'
import HtmlEditor from '@/components/HtmlEditor.vue'
import { useToast } from '@/composables/useToast'
import { provideFormValidation } from '@/composables/useFormValidation'

const toast = useToast()
const validation = provideFormValidation()
const items = ref<AnnouncementItem[]>([])
const loading = ref(false)
const meta = ref({ current_page: 1, last_page: 1, per_page: 20, total: 0 })
const options = ref<AnnouncementFormOptions>({ statuses: [], placements: [], variants: [] })
const showForm = ref(false)
const editingItem = ref<AnnouncementItem | null>(null)
const formError = ref<string | null>(null)
const saving = ref(false)

/**
 * Formulár drží prázdne polia ako `''`, nie `null` — `<input>` aj editor pracujú
 * s reťazcom. Na `null` sa prevádzajú až v `save()`, kde ich čaká API.
 */
type AnnouncementForm = Omit<AnnouncementPayload, 'body' | 'published_from' | 'published_until'> & {
  body: string
  published_from: string
  published_until: string
}

const emptyForm = (): AnnouncementForm => ({
  placement: 'top',
  title: '',
  body: '',
  variant: 'blue',
  sort_order: 10,
  published_from: '',
  published_until: '',
  status: 'published',
})

const form = ref<AnnouncementForm>(emptyForm())

onMounted(() => loadPage(1))

async function loadPage(page: number) {
  loading.value = true
  try {
    const res = await indexAnnouncements({ page })
    items.value = res.data
    meta.value = res.meta
    options.value = res.options
  } catch {
    toast.error('Nepodarilo sa načítať oznamy.')
  } finally {
    loading.value = false
  }
}

const isVisible = (item: AnnouncementItem) => item.status === 'published'

function placementLabel(placement: string) {
  return options.value.placements.find(o => o.value === placement)?.label ?? placement
}

/** Popis je HTML z editora — v zozname stačí holý text. */
function plainBody(body: string | null) {
  if (!body) return ''
  const el = document.createElement('div')
  el.innerHTML = body
  return (el.textContent ?? '').trim()
}

function openCreate() {
  editingItem.value = null
  form.value = emptyForm()
  formError.value = null
  validation.reset()
  showForm.value = true
}

function openEdit(item: AnnouncementItem) {
  editingItem.value = item
  form.value = {
    ...toPayload(item),
    body: item.body ?? '',
    published_from: item.publishedFrom ?? '',
    published_until: item.publishedUntil ?? '',
  }
  formError.value = null
  validation.reset()
  showForm.value = true
}

function errorMessage(e: unknown) {
  return (e as { response?: { data?: { message?: string } } })?.response?.data?.message ?? 'Chyba.'
}

async function save() {
  validation.markValidated()
  formError.value = null
  saving.value = true
  try {
    const payload: AnnouncementPayload = {
      ...form.value,
      body: form.value.body || null,
      published_from: form.value.published_from || null,
      published_until: form.value.published_until || null,
    }

    if (editingItem.value) {
      const updated = await updateAnnouncement(editingItem.value.id, payload)
      const idx = items.value.findIndex(i => i.id === updated.id)
      if (idx !== -1) items.value[idx] = updated
    } else {
      await createAnnouncement(payload)
      await loadPage(1)
    }

    toast.success('Uložené.')
    showForm.value = false
  } catch (e: unknown) {
    formError.value = errorMessage(e)
  } finally {
    saving.value = false
  }
}

async function toggle(item: AnnouncementItem) {
  const next = isVisible(item) ? 'draft' : 'published'
  try {
    const updated = await updateAnnouncement(item.id, toPayload(item, { status: next }))
    const idx = items.value.findIndex(i => i.id === updated.id)
    if (idx !== -1) items.value[idx] = updated
  } catch (e: unknown) {
    toast.error(errorMessage(e))
  }
}

async function remove(item: AnnouncementItem) {
  if (!confirm('Naozaj zmazať oznam?')) return
  try {
    await deleteAnnouncement(item.id)
    items.value = items.value.filter(i => i.id !== item.id)
    toast.success('Oznam zmazaný.')
  } catch {
    toast.error('Mazanie zlyhalo.')
  }
}
</script>

<style scoped>
@reference "tailwindcss";

.announcement-chip { @apply inline-block rounded-full px-2.5 py-1 text-xs font-semibold; }
.announcement-preview { @apply rounded-lg px-3 py-2 text-center; }
.status-toggle { @apply inline-flex cursor-pointer items-center gap-2 rounded-full border px-2.5 py-1 text-xs font-semibold transition; }
.status-toggle-on { @apply border-green-300 bg-green-50 text-green-700 hover:bg-green-100; }
.status-toggle-off { @apply border-slate-300 bg-slate-50 text-slate-500 hover:bg-slate-100; }
.status-dot { @apply h-2 w-2 rounded-full; }
</style>
