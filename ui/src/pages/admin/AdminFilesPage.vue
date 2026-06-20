<template>
  <div class="grid gap-4">
    <h1 class="text-2xl font-semibold text-slate-900">Správa súborov</h1>

    <div class="panel-card flex flex-wrap gap-3">
      <select v-model="fileableType" class="form-input w-auto">
        <option value="event">Event</option>
        <option value="canal">Kanál</option>
        <option value="venue">Miesto</option>
      </select>
      <input v-model.number="fileableId" type="number" placeholder="ID entity" class="form-input w-32" />
      <button class="btn btn-secondary" :disabled="!fileableId" @click="load">Načítať</button>
    </div>

    <p v-if="loading" class="text-slate-600">Načítavam…</p>

    <div v-else-if="files.length" class="panel-card">
      <ul class="grid gap-2">
        <li v-for="file in files" :key="file.id" class="flex items-center gap-3 rounded-lg border border-slate-200 p-3">
          <img v-if="file.previewUrl" :src="file.previewUrl" class="size-10 rounded object-cover" />
          <span class="flex-1 min-w-0 truncate text-sm text-slate-900">{{ file.name }}</span>
          <span class="text-xs text-slate-500">{{ formatSize(file.sizeBytes) }}</span>
          <span v-if="file.deletedAt" class="text-xs text-red-600">Zmazaný</span>
          <button v-if="!file.deletedAt" class="action-btn action-btn-danger" @click="remove(file.id)">Zmazať</button>
          <button v-else class="action-btn" @click="restore(file.id)">Obnoviť</button>
        </li>
      </ul>
    </div>
    <p v-else-if="searched" class="text-slate-500">Žiadne súbory.</p>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { listFiles, deleteFile, restoreFile, type FileItem } from '@/api/files'
import { useToast } from '@/composables/useToast'

const toast = useToast()
const fileableType = ref('event')
const fileableId = ref<number | null>(null)
const files = ref<FileItem[]>([])
const loading = ref(false)
const searched = ref(false)

async function load() {
  if (!fileableId.value) return
  loading.value = true; searched.value = true
  try { files.value = await listFiles({ fileable_type: fileableType.value, fileable_id: fileableId.value }) }
  catch { toast.error('Nepodarilo sa načítať súbory.') }
  finally { loading.value = false }
}

async function remove(id: number) {
  try { await deleteFile(id, 'admin'); files.value = files.value.filter(f => f.id !== id); toast.success('Súbor zmazaný.') }
  catch { toast.error('Mazanie zlyhalo.') }
}

async function restore(id: number) {
  try { await restoreFile(id, 'admin'); await load(); toast.success('Súbor obnovený.') }
  catch { toast.error('Obnova zlyhala.') }
}

function formatSize(bytes: number) {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / 1024 / 1024).toFixed(1)} MB`
}
</script>
