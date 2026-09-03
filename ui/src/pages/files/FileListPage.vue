<template>
  <div class="grid gap-4">
    <div class="index-head">
      <div class="head-actions">
        <div>
          <h1 class="text-2xl font-semibold text-slate-900">{{ isAdmin ? t('admin.files.title') : t('admin.files.myTitle') }}</h1>
          <p class="mt-0.5 text-sm text-slate-500">
            <template v-if="searched && !loading">{{ total }} {{ plural('admin.files.counts.files', total) }}</template>
            <template v-else>&nbsp;</template>
          </p>
        </div>
      </div>

      <!-- Tá istá lišta ako nad ostatnými výpismi. Zo spoločných filtrov dáva
           súborom zmysel hľadanie, kôš na mieste stavu, zoradenie a rozsah
           dátumu (tu dátum nahratia). Časové okno ani filter kanála sem
           nepatria — súbor nemá termín a na kanáli visí až cez záznam, ku
           ktorému patrí. -->
      <ResourceFilterBar
        v-model:search="filters.search"
        v-model:status="filters.trash"
        v-model:sort="filters.sort"
        v-model:date-from="filters.dateFrom"
        v-model:date-to="filters.dateTo"
        :status-options="trashOptions"
        :all-statuses-label="t('filters.files.active')"
        :search-placeholder="t('filters.files.search')"
        :sort-options="sortOptions"
        :extra-active="extraActive"
        show-date-range
        :history-key="historyKey"
        @change="load(1)"
        @reset="resetExtraFilters"
      >
        <template #filters>
          <select v-model="filters.fileableType" class="form-input w-auto"
            :title="t('filters.files.typeTitle')" @change="load(1)">
            <option v-for="opt in typeOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
          </select>

          <select v-model="filters.kind" class="form-input w-auto"
            :title="t('filters.files.kindTitle')" @change="load(1)">
            <option value="">{{ t('filters.files.allKinds') }}</option>
            <option v-for="opt in kindOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
          </select>

          <!-- Hľadanie podľa kľúča záznamu má zmysel len v admine, kde sa chodí za
               konkrétnym ID z databázy; v dashboarde stačí filter typu. -->
          <input v-if="isAdmin" v-model.number="filters.fileableId" type="number"
            :placeholder="t('filters.files.entityId')" class="form-input w-28"
            @keydown.enter="load(1)" @change="load(1)" />
        </template>
      </ResourceFilterBar>
    </div>

    <p v-if="loading" class="text-slate-600">{{ t('common.loading') }}</p>
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
                {{ t('admin.files.primary') }}
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
                {{ t('admin.files.deleted') }}
              </span>
            </div>
          </div>

          <!-- Actions -->
          <RowActions>
            <a :href="file.url" target="_blank" rel="noopener" class="row-menu-item">{{ t('admin.files.open') }}</a>
            <a :href="file.url" :download="file.name" class="row-menu-item">{{ t('admin.files.download') }}</a>
            <button class="row-menu-item" @click="copyLink(file)">{{ t('admin.files.copyLink') }}</button>

            <div v-if="canTrash(file) || canRestore(file)" class="my-1 h-px bg-slate-100"></div>

            <!-- Live file: soft delete (recoverable), blocked while primary -->
            <template v-if="!file.deletedAt">
              <button v-if="file.isPrimary && canTrash(file)" type="button" disabled
                class="row-menu-item cursor-not-allowed opacity-50"
                :title="t('admin.files.primaryBlocked')">
                {{ t('common.remove') }}
              </button>
              <button v-else-if="canTrash(file)" type="button" class="row-menu-item row-menu-item-danger" @click="softDelete(file)">
                {{ t('admin.files.trash') }}
              </button>
            </template>

            <!-- Trashed file: restore or (admin only) purge permanently -->
            <template v-else>
              <button v-if="canRestore(file)" type="button" class="row-menu-item" @click="restoreOne(file.id)">{{ t('common.restore') }}</button>
              <button v-if="isAdmin" type="button" class="row-menu-item row-menu-item-danger" @click="hardDelete(file)">
                {{ t('admin.files.purge') }}
              </button>
            </template>
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
      {{ t('admin.files.empty') }}
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
import { listAdminFiles, listDashboardFiles, deleteFile, forceDeleteFile, restoreFile, type FileItem } from '@/api/files'
import { useToast } from '@/composables/useToast'
import RowActions from '@/components/RowActions.vue'
import ResourceFilterBar, { type FilterOption } from '@/components/ResourceFilterBar.vue'
import { fmtDate } from '@/utils/dateFormat'
import { useI18n, localeTag, type MessageKey } from '@/i18n'

// Rovnaká obrazovka pod dvoma adresami: admin vidí všetky súbory a smie ich
// mazať natrvalo, dashboard len tie zo svojich kanálov a len do koša.
const props = withDefaults(defineProps<{ scope?: 'admin' | 'dashboard' }>(), { scope: 'admin' })
const isAdmin = computed(() => props.scope === 'admin')

const { t, plural } = useI18n()
const toast = useToast()

/** `trash`: prázdne = bez zmazaných, `with_trashed` = aj zmazané, `deleted` = len zmazané. */
const filters = ref({
  fileableType: '',
  fileableId: undefined as number | undefined,
  kind: '',
  search: '',
  sort: 'newest',
  dateFrom: '',
  dateTo: '',
  trash: '',
})
const files = ref<FileItem[]>([])
const loading = ref(false)
const searched = ref(false)
const error = ref<string | null>(null)
const total = ref(0)
const currentPage = ref(1)
const lastPage = ref(1)
const preview = ref<FileItem | null>(null)

/** História hľadania sa drží zvlášť pre admin a dashboard — sú to iné rozsahy. */
const historyKey = computed(() => `${props.scope}-files`)

const typeOptions = computed<FilterOption[]>(() => [
  { value: '', label: t('filters.files.types.all') },
  { value: 'event', label: t('filters.files.types.event') },
  { value: 'canal', label: t('filters.files.types.canal') },
  { value: 'venue', label: t('filters.files.types.venue') },
])

// Druh nie je stĺpec — skladá sa z MIME typu a prípony presne tak, ako odznak
// pri názve súboru nižšie (kindMeta) a `File::scopeByKind()` na API. Keby sa
// pravidlá rozišli, filter a odznak by o tom istom súbore tvrdili niečo iné.
const kindOptions = computed<FilterOption[]>(() => [
  { value: 'image', label: t('admin.files.kinds.image') },
  { value: 'pdf', label: t('admin.files.kinds.pdf') },
  { value: 'document', label: t('admin.files.kinds.document') },
  { value: 'spreadsheet', label: t('admin.files.kinds.spreadsheet') },
  { value: 'video', label: t('admin.files.kinds.video') },
  { value: 'audio', label: t('admin.files.kinds.audio') },
  { value: 'archive', label: t('admin.files.kinds.archive') },
  { value: 'other', label: t('admin.files.kinds.file') },
])

/**
 * Kôš stojí tam, kde ostatné výpisy majú stav — pre používateľa je to tá istá
 * otázka „čo mám vidieť". Prázdna voľba znamená „bez zmazaných", nie „všetko";
 * preto lišta dostáva vlastný popisok cez `all-statuses-label`.
 */
const trashOptions = computed<FilterOption[]>(() => [
  { value: 'with_trashed', label: t('filters.files.withTrashed') },
  { value: 'deleted', label: t('filters.files.onlyDeleted') },
])

const sortOptions = computed<FilterOption[]>(() => [
  { value: 'newest', label: t('filters.sort.newest') },
  { value: 'oldest', label: t('filters.sort.oldest') },
  { value: 'name', label: t('filters.sort.name') },
  { value: 'largest', label: t('filters.files.sort.largest') },
  { value: 'smallest', label: t('filters.files.sort.smallest') },
])

/** Filtre zo slotu — lišta o nich nevie, počítadlo aj „Zrušiť filtre" ich berie odtiaľto. */
const extraActive = computed(() => {
  let n = 0
  if (filters.value.fileableType) n++
  if (filters.value.kind) n++
  if (filters.value.fileableId) n++
  return n
})

function resetExtraFilters() {
  filters.value.fileableType = ''
  filters.value.kind = ''
  filters.value.fileableId = undefined
}

// Dashboard nemaže natrvalo — FilePolicy::forceDelete() to zakazuje každému,
// takže v tomto rozsahu ani nemá zmysel akciu ponúkať.
function canTrash(file: FileItem): boolean {
  return isAdmin.value || file.permissions.delete
}

function canRestore(file: FileItem): boolean {
  return isAdmin.value || file.permissions.restore
}

async function load(page = 1) {
  loading.value = true
  searched.value = true
  error.value = null
  try {
    const params = {
      ...(filters.value.fileableType ? { fileable_type: filters.value.fileableType } : {}),
      ...(filters.value.kind ? { kind: filters.value.kind } : {}),
      ...(filters.value.search ? { search: filters.value.search } : {}),
      ...(filters.value.sort !== 'newest' ? { sort: filters.value.sort } : {}),
      ...(filters.value.dateFrom ? { date_from: filters.value.dateFrom } : {}),
      ...(filters.value.dateTo ? { date_to: filters.value.dateTo } : {}),
      // Dve polohy toho istého prepínača: „aj zmazané" vs. „len zmazané".
      ...(filters.value.trash === 'with_trashed' ? { with_trashed: true } : {}),
      ...(filters.value.trash === 'deleted' ? { deleted: true } : {}),
      page,
    }
    const res = isAdmin.value
      ? await listAdminFiles({ ...params, ...(filters.value.fileableId ? { fileable_id: filters.value.fileableId } : {}) })
      : await listDashboardFiles(params)
    files.value = res.data
    total.value = res.total
    currentPage.value = res.currentPage
    lastPage.value = res.lastPage
  } catch {
    error.value = t('admin.files.loadFailed')
  } finally {
    loading.value = false
  }
}

function errMsg(e: unknown): string | null {
  return (e as { response?: { data?: { message?: string } } })?.response?.data?.message ?? null
}

// First stage — recoverable. Soft-deleted files stay visible under the
// "Vrátane zmazaných" filter, where they can be restored or purged.
async function softDelete(file: FileItem) {
  try { await deleteFile(file.id, props.scope); await load(currentPage.value); toast.success(t('admin.files.trashed')) }
  catch (e) { toast.error(errMsg(e) ?? t('common.removeFailed')) }
}

// Second stage — irreversible: purges the DB row and the physical files.
async function hardDelete(file: FileItem) {
  if (!confirm(t('admin.files.purgeConfirm', { name: file.name }))) return
  try { await forceDeleteFile(file.id); await load(currentPage.value); toast.success(t('admin.files.purged')) }
  catch (e) { toast.error(errMsg(e) ?? t('admin.files.purgeFailed')) }
}

async function restoreOne(id: number) {
  try { await restoreFile(id, props.scope); await load(currentPage.value); toast.success(t('admin.files.restored')) }
  catch (e) { toast.error(errMsg(e) ?? t('common.restoreFailed')) }
}

async function copyLink(file: FileItem) {
  try {
    await navigator.clipboard.writeText(new URL(file.url, window.location.origin).href)
    toast.success(t('admin.files.linkCopied'))
  } catch { toast.error(t('admin.files.copyFailed')) }
}

function openPreview(file: FileItem) {
  preview.value = file
}

// ── File-kind classification ────────────────────────────────
interface KindMeta { label: string; short: string; badge: string; tile: string }

function kindMeta(file: FileItem): KindMeta {
  const mime = file.mimeType || ''
  const ext = (file.extension || '').toLowerCase()

  if (mime.startsWith('image/')) return { label: t('admin.files.kinds.image'), short: 'IMG', badge: 'bg-emerald-50 text-emerald-700 ring-emerald-200', tile: 'bg-emerald-500' }
  if (mime.startsWith('video/')) return { label: t('admin.files.kinds.video'), short: 'VID', badge: 'bg-purple-50 text-purple-700 ring-purple-200', tile: 'bg-purple-500' }
  if (mime.startsWith('audio/')) return { label: t('admin.files.kinds.audio'), short: 'AUD', badge: 'bg-pink-50 text-pink-700 ring-pink-200', tile: 'bg-pink-500' }
  if (mime === 'application/pdf' || ext === 'pdf') return { label: t('admin.files.kinds.pdf'), short: 'PDF', badge: 'bg-red-50 text-red-700 ring-red-200', tile: 'bg-red-500' }
  if (['doc', 'docx', 'rtf', 'odt'].includes(ext)) return { label: t('admin.files.kinds.document'), short: 'DOC', badge: 'bg-blue-50 text-blue-700 ring-blue-200', tile: 'bg-blue-500' }
  if (['xls', 'xlsx', 'csv', 'ods'].includes(ext)) return { label: t('admin.files.kinds.spreadsheet'), short: 'XLS', badge: 'bg-green-50 text-green-700 ring-green-200', tile: 'bg-green-600' }
  if (['zip', 'rar', '7z', 'gz', 'tar'].includes(ext)) return { label: t('admin.files.kinds.archive'), short: 'ZIP', badge: 'bg-amber-50 text-amber-700 ring-amber-200', tile: 'bg-amber-500' }
  return { label: t('admin.files.kinds.file'), short: ext.toUpperCase() || 'FILE', badge: 'bg-slate-100 text-slate-600 ring-slate-200', tile: 'bg-slate-400' }
}

// ── Owner (fileable) linking ────────────────────────────────
const OWNER_ROUTES: Record<'admin' | 'dashboard', Record<string, string>> = {
  admin: { Event: 'admin-events-show', Canal: 'admin-canals-show', Venue: 'admin-venues-show' },
  dashboard: { Event: 'dashboard-events-show', Canal: 'dashboard-canals-show', Venue: 'dashboard-venues-show' },
}
// Tie isté názvy typov ponúka aj filter nad zoznamom.
const OWNER_LABEL_KEYS: Record<string, MessageKey> = {
  Event: 'filters.files.types.event',
  Canal: 'filters.files.types.canal',
  Venue: 'filters.files.types.venue',
}

// Názov záznamu posiela dashboardový výpis (`fileable_name`); admin ho nemá,
// tam ostáva kľúč z databázy, podľa ktorého sa aj filtruje.
function ownerLabel(file: FileItem): string {
  if (!file.fileableType) return ''
  const key = OWNER_LABEL_KEYS[file.fileableType]
  const type = key ? t(key) : file.fileableType
  return file.fileableName ? `${type}: ${file.fileableName}` : `${type} #${file.fileableId}`
}

function ownerLink(file: FileItem): RouteLocationRaw | null {
  const name = file.fileableType ? OWNER_ROUTES[props.scope][file.fileableType] : null
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
  if (days < 1) return t('common.rel.today')
  if (days === 1) return t('common.rel.yesterday')
  if (days < 30) return t('common.rel.days', { n: days })
  return fmtDate(value)
}

function fullDate(value: string | null): string {
  return value ? new Date(value).toLocaleString(localeTag()) : ''
}

function onEsc(e: KeyboardEvent) {
  if (e.key === 'Escape') preview.value = null
}

onMounted(() => {
  load(1)
  window.addEventListener('keydown', onEsc)
})
onBeforeUnmount(() => window.removeEventListener('keydown', onEsc))
</script>
