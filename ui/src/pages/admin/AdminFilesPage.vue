<template>
  <div class="grid gap-4">
    <div class="flex flex-wrap items-end justify-between gap-3">
      <div>
        <h1 class="text-2xl font-semibold text-slate-900">Správa súborov</h1>
        <p class="mt-0.5 text-sm text-slate-500">
          <template v-if="searched && !loading">{{ total }} {{ pluralFiles(total) }}</template>
          <template v-else>&nbsp;</template>
        </p>
      </div>
    </div>

    <!-- Filter toolbar -->
    <div class="panel-card flex flex-wrap items-center gap-2 !py-3">
      <div class="relative w-full max-w-xs">
        <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
          viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
          <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" clip-rule="evenodd" />
        </svg>
        <input v-model="filters.search" type="search" placeholder="Hľadať názov súboru…"
          class="form-input h-10 pl-9" @input="onSearchInput" />
      </div>

      <!-- Type segmented control -->
      <div class="inline-flex h-10 items-center rounded-lg border border-slate-300 bg-white p-0.5">
        <button v-for="opt in typeOptions" :key="opt.value" type="button"
          class="h-9 rounded-md px-3 text-sm font-medium transition-colors"
          :class="filters.fileable_type === opt.value ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100'"
          @click="setType(opt.value)">
          {{ opt.label }}
        </button>
      </div>

      <input v-model.number="filters.fileable_id" type="number" placeholder="ID entity"
        class="form-input h-10 w-28" @keydown.enter="load(1)" @change="load(1)" />

      <label class="flex h-10 cursor-pointer select-none items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-700"
        :class="{ 'border-red-300 bg-red-50 text-red-700': filters.with_trashed }">
        <input type="checkbox" v-model="filters.with_trashed" class="accent-red-500" @change="load(1)" />
        Vrátane zmazaných
      </label>

      <button v-if="activeFilterCount > 0" type="button"
        class="inline-flex h-10 items-center gap-1.5 rounded-lg px-3 text-sm font-medium text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-700"
        @click="resetFilters">
        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
        Zrušiť filtre
        <span class="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-blue-100 px-1 text-xs font-semibold text-blue-700">{{ activeFilterCount }}</span>
      </button>
    </div>

    <p v-if="loading" class="text-slate-600">Načítavam…</p>
    <p v-else-if="error" class="text-red-600">{{ error }}</p>

    <div v-else-if="files.length" class="panel-card !p-0">
      <ul class="divide-y divide-slate-100">
        <li v-for="file in files" :key="file.id"
          class="group flex items-center gap-4 px-4 py-3 transition-colors hover:bg-slate-50"
          :class="{ 'bg-red-50/50': file.deletedAt }">
          <!-- Thumbnail / type tile -->
          <button type="button"
            class="relative h-12 w-12 shrink-0 overflow-hidden rounded-lg ring-1 ring-slate-200 transition-transform"
            :class="file.thumbUrl ? 'cursor-zoom-in hover:scale-105' : 'cursor-default'"
            @click="file.thumbUrl && openPreview(file)">
            <img v-if="file.thumbUrl" :src="file.thumbUrl" :alt="file.name" class="h-full w-full object-cover" />
            <span v-else class="flex h-full w-full items-center justify-center text-[0.6rem] font-bold uppercase tracking-tight text-white"
              :class="kindMeta(file).tile">
              {{ file.extension || kindMeta(file).short }}
            </span>
          </button>

          <!-- Main -->
          <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2">
              <p class="truncate text-sm font-medium text-slate-900" :title="file.originalName || file.name">
                {{ file.name }}
              </p>
              <span v-if="file.isPrimary"
                class="inline-flex shrink-0 items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-[0.68rem] font-semibold text-amber-700 ring-1 ring-inset ring-amber-200">
                <svg class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor"><path d="M9.05 2.93c.3-.92 1.6-.92 1.9 0l1.36 4.18a1 1 0 00.95.69h4.4c.97 0 1.37 1.24.59 1.81l-3.56 2.59a1 1 0 00-.36 1.12l1.36 4.18c.3.92-.76 1.69-1.54 1.12l-3.56-2.59a1 1 0 00-1.18 0l-3.56 2.59c-.78.57-1.84-.2-1.54-1.12l1.36-4.18a1 1 0 00-.36-1.12L1.4 9.61c-.78-.57-.38-1.81.59-1.81h4.4a1 1 0 00.95-.69L9.05 2.93z"/></svg>
                Primary
              </span>
            </div>
            <div class="mt-1 flex flex-wrap items-center gap-1.5 text-xs text-slate-500">
              <span class="rounded px-1.5 py-0.5 font-medium ring-1 ring-inset" :class="kindMeta(file).badge">
                {{ kindMeta(file).label }}
              </span>
              <span class="text-slate-400">{{ formatSize(file.sizeBytes) }}</span>
              <RouterLink v-if="ownerLink(file)" :to="ownerLink(file)!"
                class="inline-flex items-center gap-1 rounded-full bg-teal-50 px-2 py-0.5 font-medium text-teal-700 no-underline ring-1 ring-inset ring-teal-200 hover:bg-teal-100">
                <span>{{ ownerLabel(file) }}</span>
              </RouterLink>
              <span v-else-if="file.fileableType" class="text-slate-400">{{ ownerLabel(file) }}</span>
              <span v-if="file.createdAt" class="text-slate-400" :title="fullDate(file.createdAt)">· {{ relTime(file.createdAt) }}</span>
              <span v-if="file.deletedAt"
                class="rounded-full bg-red-50 px-2 py-0.5 font-medium text-red-600 ring-1 ring-inset ring-red-200">
                Zmazaný
              </span>
            </div>
          </div>

          <!-- Actions -->
          <RowActions>
            <a :href="file.url" target="_blank" rel="noopener" class="row-menu-item">Otvoriť</a>
            <a :href="file.url" :download="file.name" class="row-menu-item">Stiahnuť</a>
            <button class="row-menu-item" @click="copyLink(file)">Kopírovať odkaz</button>
            <button v-if="!file.deletedAt" class="row-menu-item row-menu-item-danger" @click="remove(file.id)">Zmazať</button>
            <button v-else class="row-menu-item" @click="restoreOne(file.id)">Obnoviť</button>
          </RowActions>
        </li>
      </ul>

      <!-- Pagination -->
      <div v-if="lastPage > 1" class="flex items-center justify-center gap-2 border-t border-slate-100 py-3">
        <button class="btn btn-secondary btn-sm" :disabled="currentPage <= 1" @click="load(currentPage - 1)">←</button>
        <span class="text-sm text-slate-600">{{ currentPage }} / {{ lastPage }}</span>
        <button class="btn btn-secondary btn-sm" :disabled="currentPage >= lastPage" @click="load(currentPage + 1)">→</button>
      </div>
    </div>

    <p v-else-if="searched" class="rounded-xl border border-dashed border-slate-300 p-10 text-center text-slate-400">
      Žiadne súbory nezodpovedajú filtru.
    </p>

    <!-- Lightbox -->
    <div v-if="preview" class="fixed inset-0 z-[600] flex items-center justify-center bg-black/70 p-4" @click="preview = null">
      <div class="max-h-full max-w-3xl">
        <img :src="preview.largeUrl || preview.url" :alt="preview.name" class="max-h-[85vh] rounded-lg object-contain shadow-2xl" @click.stop />
        <p class="mt-2 text-center text-sm text-white/80">{{ preview.name }} · {{ formatSize(preview.sizeBytes) }}</p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import type { RouteLocationRaw } from 'vue-router'
import { listAdminFiles, deleteFile, restoreFile, type FileItem } from '@/api/files'
import { useToast } from '@/composables/useToast'
import RowActions from '@/components/RowActions.vue'

const toast = useToast()

const filters = ref({ fileable_type: '', fileable_id: undefined as number | undefined, search: '', with_trashed: false })
const files = ref<FileItem[]>([])
const loading = ref(false)
const searched = ref(false)
const error = ref<string | null>(null)
const total = ref(0)
const currentPage = ref(1)
const lastPage = ref(1)
const preview = ref<FileItem | null>(null)

const typeOptions = [
  { value: '', label: 'Všetky' },
  { value: 'event', label: 'Event' },
  { value: 'canal', label: 'Kanál' },
  { value: 'venue', label: 'Miesto' },
]

const activeFilterCount = computed(() => {
  let n = 0
  if (filters.value.search) n++
  if (filters.value.fileable_type) n++
  if (filters.value.fileable_id) n++
  if (filters.value.with_trashed) n++
  return n
})

let searchTimer: ReturnType<typeof setTimeout>
function onSearchInput() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => load(1), 350)
}

function setType(value: string) {
  filters.value.fileable_type = value
  load(1)
}

function resetFilters() {
  filters.value = { fileable_type: '', fileable_id: undefined, search: '', with_trashed: false }
  load(1)
}

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

async function copyLink(file: FileItem) {
  try {
    await navigator.clipboard.writeText(new URL(file.url, window.location.origin).href)
    toast.success('Odkaz skopírovaný.')
  } catch { toast.error('Kopírovanie zlyhalo.') }
}

function openPreview(file: FileItem) {
  preview.value = file
}

// ── File-kind classification ────────────────────────────────
interface KindMeta { label: string; short: string; badge: string; tile: string }

function kindMeta(file: FileItem): KindMeta {
  const mime = file.mimeType || ''
  const ext = (file.extension || '').toLowerCase()

  if (mime.startsWith('image/')) return { label: 'Obrázok', short: 'IMG', badge: 'bg-emerald-50 text-emerald-700 ring-emerald-200', tile: 'bg-emerald-500' }
  if (mime.startsWith('video/')) return { label: 'Video', short: 'VID', badge: 'bg-purple-50 text-purple-700 ring-purple-200', tile: 'bg-purple-500' }
  if (mime.startsWith('audio/')) return { label: 'Audio', short: 'AUD', badge: 'bg-pink-50 text-pink-700 ring-pink-200', tile: 'bg-pink-500' }
  if (mime === 'application/pdf' || ext === 'pdf') return { label: 'PDF', short: 'PDF', badge: 'bg-red-50 text-red-700 ring-red-200', tile: 'bg-red-500' }
  if (['doc', 'docx', 'rtf', 'odt'].includes(ext)) return { label: 'Dokument', short: 'DOC', badge: 'bg-blue-50 text-blue-700 ring-blue-200', tile: 'bg-blue-500' }
  if (['xls', 'xlsx', 'csv', 'ods'].includes(ext)) return { label: 'Tabuľka', short: 'XLS', badge: 'bg-green-50 text-green-700 ring-green-200', tile: 'bg-green-600' }
  if (['zip', 'rar', '7z', 'gz', 'tar'].includes(ext)) return { label: 'Archív', short: 'ZIP', badge: 'bg-amber-50 text-amber-700 ring-amber-200', tile: 'bg-amber-500' }
  return { label: 'Súbor', short: ext.toUpperCase() || 'FILE', badge: 'bg-slate-100 text-slate-600 ring-slate-200', tile: 'bg-slate-400' }
}

// ── Owner (fileable) linking ────────────────────────────────
const OWNER_ROUTES: Record<string, string> = { Event: 'admin-events-show', Canal: 'admin-canals-show', Venue: 'admin-venues-show' }
const OWNER_LABELS: Record<string, string> = { Event: 'Event', Canal: 'Kanál', Venue: 'Miesto' }

function ownerLabel(file: FileItem): string {
  if (!file.fileableType) return ''
  return `${OWNER_LABELS[file.fileableType] ?? file.fileableType} #${file.fileableId}`
}

function ownerLink(file: FileItem): RouteLocationRaw | null {
  const name = file.fileableType ? OWNER_ROUTES[file.fileableType] : null
  if (!name || !file.fileableId) return null
  return { name, params: { id: file.fileableId } }
}

// ── Formatting ──────────────────────────────────────────────
function formatSize(bytes: number) {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / 1024 / 1024).toFixed(1)} MB`
}

function relTime(value: string | null): string {
  if (!value) return ''
  const then = new Date(value).getTime()
  if (Number.isNaN(then)) return ''
  const days = Math.floor((Date.now() - then) / 86400000)
  if (days < 1) return 'dnes'
  if (days === 1) return 'včera'
  if (days < 30) return `pred ${days} d`
  return new Date(value).toLocaleDateString('sk-SK', { day: 'numeric', month: 'numeric', year: 'numeric' })
}

function fullDate(value: string | null): string {
  return value ? new Date(value).toLocaleString('sk-SK') : ''
}

function pluralFiles(n: number): string {
  if (n === 1) return 'súbor'
  if (n >= 2 && n <= 4) return 'súbory'
  return 'súborov'
}

function onEsc(e: KeyboardEvent) {
  if (e.key === 'Escape') preview.value = null
}

onMounted(() => {
  load(1)
  window.addEventListener('keydown', onEsc)
})
onBeforeUnmount(() => {
  window.removeEventListener('keydown', onEsc)
  clearTimeout(searchTimer)
})
</script>
