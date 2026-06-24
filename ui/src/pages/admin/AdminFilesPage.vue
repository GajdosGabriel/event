<template>
  <div class="grid gap-4">
    <div class="index-head">
      <div class="head-actions">
        <h1 class="text-2xl font-semibold text-slate-900">Správa súborov</h1>
      </div>
    </div>

    <!-- Filtre -->
    <div class="panel-card flex flex-wrap gap-3 items-end">
      <label class="form-label text-sm">
        Typ
        <select v-model="filters.fileable_type" class="form-input w-36" @change="load(1)">
          <option value="">— všetky —</option>
          <option value="event">Event</option>
          <option value="canal">Kanál</option>
          <option value="venue">Miesto</option>
        </select>
      </label>
      <label class="form-label text-sm">
        ID entity
        <input v-model.number="filters.fileable_id" type="number" placeholder="voliteľné" class="form-input w-32"
          @keydown.enter="load(1)" />
      </label>
      <label class="form-label text-sm">
        Hľadať názov
        <input v-model="filters.search" type="text" placeholder="názov súboru…" class="form-input w-48"
          @keydown.enter="load(1)" />
      </label>
      <label class="flex items-center gap-2 text-sm text-slate-700 pb-0.5 cursor-pointer">
        <input type="checkbox" v-model="filters.with_trashed" class="accent-red-600" @change="load(1)" />
        Vrátane zmazaných
      </label>
      <button class="btn btn-secondary" @click="load(1)">Hľadať</button>
    </div>

    <p v-if="loading" class="text-slate-600">Načítavam…</p>
    <p v-else-if="error" class="text-red-600">{{ error }}</p>

    <div v-else-if="files.length" class="panel-card">
      <p class="mb-3 text-sm text-slate-500">Celkom: {{ total }}</p>
      <ul class="grid gap-2">
        <li v-for="file in files" :key="file.id"
          class="flex items-center gap-3 rounded-lg border p-3"
          :class="file.deletedAt ? 'border-red-200 bg-red-50' : 'border-slate-200'">
          <img v-if="file.thumbUrl" :src="file.thumbUrl" class="size-10 shrink-0 rounded object-cover" />
          <div v-else class="size-10 shrink-0 rounded bg-slate-100 flex items-center justify-center text-slate-400">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
          </div>
          <div class="flex-1 min-w-0">
            <p class="truncate text-sm font-medium text-slate-900">{{ file.name }}</p>
            <p class="text-xs text-slate-500">{{ file.mimeType }} · {{ formatSize(file.sizeBytes) }}</p>
          </div>
          <span v-if="file.isPrimary" class="text-xs text-blue-600 font-semibold">Primary</span>
          <span v-if="file.deletedAt" class="text-xs text-red-600 font-medium">Zmazaný</span>
          <a :href="file.url" target="_blank" class="action-btn">Otvoriť</a>
          <button v-if="!file.deletedAt" class="action-btn action-btn-danger" @click="remove(file.id)">Zmazať</button>
          <button v-else class="action-btn" @click="restoreOne(file.id)">Obnoviť</button>
        </li>
      </ul>

      <!-- Stránkovanie -->
      <div v-if="lastPage > 1" class="mt-4 flex items-center gap-2">
        <button class="btn btn-secondary btn-sm" :disabled="currentPage <= 1" @click="load(currentPage - 1)">←</button>
        <span class="text-sm text-slate-600">{{ currentPage }} / {{ lastPage }}</span>
        <button class="btn btn-secondary btn-sm" :disabled="currentPage >= lastPage" @click="load(currentPage + 1)">→</button>
      </div>
    </div>

    <p v-else-if="searched" class="text-slate-500">Žiadne súbory.</p>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { listAdminFiles, deleteFile, restoreFile, type FileItem } from '@/api/files'
import { useToast } from '@/composables/useToast'

const toast = useToast()

const filters = ref({ fileable_type: '', fileable_id: undefined as number | undefined, search: '', with_trashed: false })
const files = ref<FileItem[]>([])
const loading = ref(false)
const searched = ref(false)
const error = ref<string | null>(null)
const total = ref(0)
const currentPage = ref(1)
const lastPage = ref(1)

async function load(page = 1) {
  loading.value = true
  searched.value = true
  error.value = null
  try {
    const params = {
      ...(filters.value.fileable_type ? { fileable_type: filters.value.fileable_type } : {}),
      ...(filters.value.fileable_id ? { fileable_id: filters.value.fileable_id } : {}),
      ...(filters.value.search ? { search: filters.value.search } : {}),
      ...(filters.value.with_trashed ? { with_trashed: true } : {}),
      page,
    }
    const res = await listAdminFiles(params)
    files.value = res.data
    total.value = res.total
    currentPage.value = res.currentPage
    lastPage.value = res.lastPage
  } catch {
    error.value = 'Nepodarilo sa načítať súbory.'
  } finally {
    loading.value = false
  }
}

async function remove(id: number) {
  try { await deleteFile(id, 'admin'); await load(currentPage.value); toast.success('Súbor zmazaný.') }
  catch { toast.error('Mazanie zlyhalo.') }
}

async function restoreOne(id: number) {
  try { await restoreFile(id, 'admin'); await load(currentPage.value); toast.success('Súbor obnovený.') }
  catch { toast.error('Obnova zlyhala.') }
}

function formatSize(bytes: number) {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / 1024 / 1024).toFixed(1)} MB`
}

onMounted(() => load(1))
</script>
