<template>
  <div class="grid gap-4">
    <div class="flex items-center justify-between gap-3">
      <h1 class="text-2xl font-semibold text-slate-900">{{ t('municipalities.title') }}</h1>
      <button class="btn btn-primary" @click="openCreate">{{ t('municipalities.new') }}</button>
    </div>

    <input v-model="search" type="text" :placeholder="t('filters.search')" class="form-input w-56" @input="onSearch" />

    <p v-if="loading" class="text-slate-600">{{ t('municipalities.loading') }}</p>

    <div v-else class="panel-card">
      <!-- Široká tabuľka sa posúva vo vlastnom rámčeku — na telefóne inak
           roztiahne celý dashboard a posúva sa stránka. -->
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-slate-200 text-left text-xs uppercase text-slate-500">
              <th class="pb-2 pr-4">{{ t('municipalities.colId') }}</th>
              <th class="pb-2 pr-4">{{ t('municipalities.colName') }}</th>
              <th class="pb-2 pr-4">{{ t('municipalities.colShort') }}</th>
              <th class="pb-2 pr-4">{{ t('municipalities.colZip') }}</th>
              <th class="pb-2">{{ t('municipalities.colActions') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in items" :key="item.id" class="border-b border-slate-100 last:border-0">
              <td class="py-2 pr-4 text-slate-400">{{ item.id }}</td>
              <td class="py-2 pr-4 font-medium text-slate-900">{{ item.name }}</td>
              <td class="py-2 pr-4 text-slate-600">{{ item.shortname ?? '—' }}</td>
              <td class="py-2 pr-4 text-slate-600">{{ item.zip ?? '—' }}</td>
              <td class="py-2">
                <RowActions>
                  <button class="row-menu-item" @click="openEdit(item)">{{ t('municipalities.edit') }}</button>
                  <button class="row-menu-item row-menu-item-danger" @click="remove(item.id)">{{ t('municipalities.remove') }}</button>
                </RowActions>
              </td>
            </tr>
            <tr v-if="items.length === 0">
              <td colspan="5" class="py-4 text-slate-500">{{ t('municipalities.empty') }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="meta.last_page > 1" class="mt-4 flex items-center gap-2">
        <button class="btn btn-secondary" :disabled="meta.current_page <= 1" @click="loadPage(meta.current_page - 1)">‹</button>
        <span class="text-sm text-slate-600">{{ meta.current_page }} / {{ meta.last_page }}</span>
        <button class="btn btn-secondary" :disabled="meta.current_page >= meta.last_page" @click="loadPage(meta.current_page + 1)">›</button>
      </div>
    </div>

    <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
      <div class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-5 shadow-xl">
        <h2 class="mb-3 text-lg font-semibold">{{ editingItem ? t('municipalities.editTitle') : t('municipalities.createTitle') }}</h2>
        <p v-if="formError" class="mb-2 text-sm text-red-600">{{ formError }}</p>
        <div class="grid gap-3">
          <FormField v-model="form.name" :label="t('municipalities.name')" required />
          <FormField v-model="form.shortname" :label="t('municipalities.shortname')" />
          <FormField v-model="form.zip" :label="t('municipalities.zip')" />
        </div>
        <div class="mt-4 flex gap-2">
          <button class="btn btn-primary" :disabled="saving" @click="save">{{ saving ? t('municipalities.saving') : t('municipalities.save') }}</button>
          <button class="btn btn-secondary" @click="showForm = false">{{ t('municipalities.cancel') }}</button>
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
import { provideFormValidation } from '@/composables/useFormValidation'
import FormField from '@/components/FormField.vue'
import RowActions from '@/components/RowActions.vue'
import { useI18n } from '@/i18n'

const SCOPE = 'dashboard' as const

const { t } = useI18n()
const toast = useToast()
const validation = provideFormValidation()
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
    toast.error(t('municipalities.loadFailed'))
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
  validation.reset()
  showForm.value = true
}

function openEdit(item: MunicipalityItem) {
  editingItem.value = item
  form.value = { name: item.name, shortname: item.shortname ?? '', zip: item.zip ?? '' }
  formError.value = null
  validation.reset()
  showForm.value = true
}

async function save() {
  validation.markValidated()
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
    toast.success(t('municipalities.saved'))
    showForm.value = false
  } catch (e: unknown) {
    formError.value = (e as { response?: { data?: { message?: string } } })?.response?.data?.message ?? t('municipalities.error')
  } finally {
    saving.value = false
  }
}

async function remove(id: number) {
  if (!confirm(t('municipalities.removeConfirm'))) return
  try {
    await deleteMunicipality(SCOPE, id)
    items.value = items.value.filter(i => i.id !== id)
    toast.success(t('municipalities.removed'))
  } catch {
    toast.error(t('municipalities.removeFailed'))
  }
}
</script>
