<template>
  <div class="grid gap-4">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-semibold text-slate-900">Nastavenia organizácií</h1>
      <button class="btn btn-primary" @click="openCreate">+ Nová organizácia</button>
    </div>

    <p v-if="loading" class="text-slate-600">Načítavam…</p>

    <div v-else class="panel-card">
      <ul class="grid gap-2">
        <li v-for="org in orgs" :key="org.id" class="flex items-center gap-3 rounded-lg border border-slate-200 p-3">
          <span class="flex-1 font-medium text-slate-900">{{ org.name }}</span>
          <span class="text-xs text-slate-500">{{ org.status }}</span>
          <button class="action-btn" @click="openEdit(org)">Upraviť</button>
          <button class="action-btn action-btn-danger" @click="remove(org.id)">Zmazať</button>
        </li>
        <li v-if="orgs.length === 0" class="text-slate-500">Žiadne organizácie.</li>
      </ul>
    </div>

    <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
      <div class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-5 shadow-xl">
        <h2 class="mb-3 text-lg font-semibold">{{ editingOrg ? 'Upraviť' : 'Nová' }} organizácia</h2>
        <p v-if="formError" class="mb-2 text-sm text-red-600">{{ formError }}</p>
        <div class="grid gap-3">
          <label class="form-label">Názov <input v-model="form.name" type="text" class="form-input" required /></label>
          <label class="form-label">Email <input v-model="form.email" type="email" class="form-input" /></label>
          <label class="form-label">Web <input v-model="form.website" type="url" class="form-input" /></label>
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
import { listOrganizations, createOrganization, updateOrganization, deleteOrganization } from '@/api/organizations'
import type { OrganizationItem } from '@/types'
import { useToast } from '@/composables/useToast'

const toast = useToast()
const orgs = ref<OrganizationItem[]>([])
const loading = ref(false)
const showForm = ref(false)
const editingOrg = ref<OrganizationItem | null>(null)
const form = ref({ name: '', email: '', website: '' })
const formError = ref<string | null>(null)
const saving = ref(false)

onMounted(async () => {
  loading.value = true
  try { orgs.value = (await listOrganizations('admin')).data } catch {}
  finally { loading.value = false }
})

function openCreate() { editingOrg.value = null; form.value = { name: '', email: '', website: '' }; showForm.value = true }
function openEdit(org: OrganizationItem) { editingOrg.value = org; form.value = { name: org.name, email: org.email ?? '', website: org.website ?? '' }; showForm.value = true }

async function save() {
  formError.value = null; saving.value = true
  try {
    if (editingOrg.value) {
      const updated = await updateOrganization('admin', editingOrg.value.id, form.value)
      const idx = orgs.value.findIndex(o => o.id === updated.id)
      if (idx !== -1) orgs.value[idx] = updated
    } else {
      const created = await createOrganization('admin', form.value)
      orgs.value.unshift(created)
    }
    toast.success('Uložené.'); showForm.value = false
  } catch (e: unknown) {
    formError.value = (e as { response?: { data?: { message?: string } } })?.response?.data?.message ?? 'Chyba.'
  } finally { saving.value = false }
}

async function remove(id: number) {
  if (!confirm('Naozaj zmazať?')) return
  try { await deleteOrganization('admin', id); orgs.value = orgs.value.filter(o => o.id !== id); toast.success('Zmazané.') }
  catch { toast.error('Mazanie zlyhalo.') }
}
</script>
