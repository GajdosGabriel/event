<template>
  <div class="grid gap-4">
    <div class="flex items-center justify-between gap-3">
      <h1 class="text-2xl font-semibold text-slate-900">Obce</h1>
      <button class="btn btn-primary" @click="openCreate">+ Nová obec</button>
    </div>

    <input v-model="search" type="text" placeholder="Hľadať…" class="form-input w-56" @input="onSearch" />

    <p v-if="loading" class="text-slate-600">Načítavam…</p>

    <div v-else class="panel-card">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-slate-200 text-left text-xs uppercase text-slate-500">
            <th class="pb-2 pr-4">ID</th>
            <th class="pb-2 pr-4">Názov</th>
            <th class="pb-2 pr-4">Skratka</th>
            <th class="pb-2 pr-4">PSČ</th>
            <th class="pb-2">Akcie</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in items" :key="item.id" class="border-b border-slate-100 last:border-0">
            <td class="py-2 pr-4 text-slate-400">{{ item.id }}</td>
            <td class="py-2 pr-4 font-medium text-slate-900">{{ item.name }}</td>
            <td class="py-2 pr-4 text-slate-600">{{ item.shortname ?? '—' }}</td>
            <td class="py-2 pr-4 text-slate-600">{{ item.zip ?? '—' }}</td>
            <td class="py-2 flex gap-2">
              <button class="action-btn" @click="openEdit(item)">Upraviť</button>
              <button class="action-btn action-btn-danger" @click="remove(item.id)">Zmazať</button>
            </td>
          </tr>
          <tr v-if="items.length === 0">
            <td colspan="5" class="py-4 text-slate-500">Žiadne obce.</td>
          </tr>
        </tbody>
      </table>

      <div v-if="meta.last_page > 1" class="mt-4 flex items-center gap-2">
        <button class="btn btn-secondary" :disabled="meta.current_page <= 1" @click="loadPage(meta.current_page - 1)">‹</button>
        <span class="text-sm text-slate-600">{{ meta.current_page }} / {{ meta.last_page }}</span>
        <button class="btn btn-secondary" :disabled="meta.current_page >= meta.last_page" @click="loadPage(meta.current_page + 1)">›</button>
      </div>
    </div>

    <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
      <div class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-5 shadow-xl">
        <h2 class="mb-3 text-lg font-semibold">{{ editingItem ? 'Upraviť' : 'Nová' }} obec</h2>
        <p v-if="formError" class="mb-2 text-sm text-red-600">{{ formError }}</p>
        <div class="grid gap-3">
          <label class="form-label">Názov <input v-model="form.name" type="text" class="form-input" required /></label>
          <label class="form-label">Skratka <input v-model="form.shortname" type="text" class="form-input" /></label>
          <label class="form-label">PSČ <input v-model="form.zip" type="text" class="form-input" /></label>
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
import { indexMunicipalities, createMunicipality, updateMunicipality, deleteMunicipality } from '@/api/municipalities'
import type { MunicipalityItem } from '@/types'
import { useToast } from '@/composables/useToast'

const SCOPE = 'admin' as const

const toast = useToast()
const items = ref<MunicipalityItem[]>([])
const loading = ref(false)
const search = ref('')
const meta = ref({ current_page: 1, last_page: 1, per_page: 20, total: 0 })
const showForm = ref(false)
const editingItem = ref<MunicipalityItem | null>(null)
const form = ref({ name: '', shortname: '', zip: '' })
const formError = ref<string | null>(null)
const saving = ref(false)
let searchTimer: ReturnType<typeof setTimeout> | null = null

onMounted(() => loadPage(1))

async function loadPage(page: number) {
  loading.value = true
  try {
    const res = await indexMunicipalities(SCOPE, { page, search: search.value || undefined })
    items.value = res.data
    meta.value = res.meta
  } catch {
    toast.error('Nepodarilo sa načítať obce.')
  } finally {
    loading.value = false
  }
}

function onSearch() {
  if (searchTimer) clearTimeout(searchTimer)
  searchTimer = setTimeout(() => loadPage(1), 400)
}

function openCreate() {
  editingItem.value = null
  form.value = { name: '', shortname: '', zip: '' }
  formError.value = null
  showForm.value = true
}

function openEdit(item: MunicipalityItem) {
  editingItem.value = item
  form.value = { name: item.name, shortname: item.shortname ?? '', zip: item.zip ?? '' }
  formError.value = null
  showForm.value = true
}

async function save() {
  formError.value = null
  saving.value = true
  try {
    const payload = { name: form.value.name, shortname: form.value.shortname || null, zip: form.value.zip || null }
    if (editingItem.value) {
      const updated = await updateMunicipality(SCOPE, editingItem.value.id, payload)
      const idx = items.value.findIndex(i => i.id === updated.id)
      if (idx !== -1) items.value[idx] = updated
    } else {
      await createMunicipality(SCOPE, payload)
      await loadPage(1)
    }
    toast.success('Uložené.')
    showForm.value = false
  } catch (e: unknown) {
    formError.value = (e as { response?: { data?: { message?: string } } })?.response?.data?.message ?? 'Chyba.'
  } finally {
    saving.value = false
  }
}

async function remove(id: number) {
  if (!confirm('Naozaj zmazať obec?')) return
  try {
    await deleteMunicipality(SCOPE, id)
    items.value = items.value.filter(i => i.id !== id)
    toast.success('Obec zmazaná.')
  } catch {
    toast.error('Mazanie zlyhalo.')
  }
}
</script>
