<template>
  <div class="space-y-4">
    <!-- Upload zone -->
    <div
      class="relative flex flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed px-6 py-8 text-center transition"
      :class="isDraggingOver ? 'border-blue-400 bg-blue-50' : 'border-slate-200 bg-slate-50 hover:border-slate-300'"
      @dragover.prevent="isDraggingOver = true"
      @dragleave="isDraggingOver = false"
      @drop.prevent="onDrop"
    >
      <svg class="h-8 w-8 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/>
      </svg>
      <p class="text-sm text-slate-500">
        Presunte obrazky sem alebo
        <label class="cursor-pointer text-blue-600 hover:underline">
          vyberte zo zariadenia
          <input ref="fileInputEl" type="file" multiple accept="image/*" class="sr-only" @change="onFileInput" />
        </label>
      </p>
      <p class="text-xs text-slate-400">JPG, PNG, WebP — max 10 MB / subor</p>
    </div>

    <!-- Image grid -->
    <div v-if="allItems.length" class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
      <div
        v-for="(item, idx) in allItems"
        :key="item.key"
        class="group relative rounded-xl border-2 bg-slate-100 transition select-none"
        :class="[
          item.type === 'uploaded' && item.data.isPrimary ? 'border-blue-500' : 'border-slate-200',
          item.type === 'pending' ? 'border-blue-300' : '',
          dragging === item.key ? 'opacity-40 scale-95' : '',
          dragOverIdx === idx && item.type === 'uploaded' ? 'ring-2 ring-blue-400' : '',
        ]"
        :draggable="item.type === 'uploaded'"
        @dragstart="item.type === 'uploaded' && onDragStart(item.key, idx)"
        @dragover.prevent="item.type === 'uploaded' && (dragOverIdx = idx)"
        @dragleave="dragOverIdx = null"
        @drop.prevent="item.type === 'uploaded' && onReorderDrop(idx)"
        @dragend="onDragEnd"
      >
        <!-- Aspect-square container -->
        <div class="aspect-square overflow-hidden rounded-[10px]">
          <!-- Pending preview -->
          <template v-if="item.type === 'pending'">
            <img :src="item.objectUrl" :alt="item.name" class="h-full w-full object-cover opacity-60" />
          </template>

          <!-- Uploaded image -->
          <template v-else>
            <img
              v-if="imgSrc(item.data)"
              :src="imgSrc(item.data)!"
              :alt="item.data.name"
              class="h-full w-full cursor-zoom-in object-cover"
              @load="onImgLoad(item.data)"
              @error="onImgError($event, item.data)"
              @click="openLightbox(item.data)"
            />
            <div v-else class="flex h-full w-full items-center justify-center bg-slate-100">
              <svg class="h-8 w-8 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"/>
              </svg>
            </div>
          </template>
        </div>

        <!-- Pending spinner overlay -->
        <div v-if="item.type === 'pending'" class="absolute inset-0 flex flex-col items-center justify-center gap-1 rounded-[10px] bg-black/20">
          <span class="inline-block h-5 w-5 animate-spin rounded-full border-2 border-white/40 border-t-white" />
          <span class="text-[10px] font-semibold text-white drop-shadow">Nahravame...</span>
        </div>

        <!-- Uploaded: primary badge -->
        <div v-if="item.type === 'uploaded' && item.data.isPrimary" class="absolute left-1.5 top-1.5 rounded-full bg-blue-600 px-2 py-0.5 text-[10px] font-semibold text-white shadow">
          Hlavna
        </div>

        <!-- Uploaded: drag handle -->
        <div v-if="item.type === 'uploaded'" class="absolute right-1.5 top-1.5 cursor-grab rounded-lg bg-white/90 p-1 opacity-0 shadow transition group-hover:opacity-100">
          <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" d="M4 8h16M4 16h16"/>
          </svg>
        </div>

        <!-- Uploaded: action bar -->
        <div v-if="item.type === 'uploaded'" class="absolute inset-x-0 bottom-0 flex gap-1 rounded-b-[10px] bg-white/95 p-1.5 opacity-0 transition group-hover:opacity-100">
          <button
            v-if="!item.data.isPrimary"
            class="flex-1 rounded-lg bg-blue-50 py-1 text-xs font-medium text-blue-700 hover:bg-blue-100 disabled:opacity-50"
            :disabled="settingPrimary === item.data.id"
            @click.stop="setPrimary(item.data)"
          >Hlavna</button>
          <button
            class="flex-1 rounded-lg bg-red-50 py-1 text-xs font-medium text-red-600 hover:bg-red-100 disabled:opacity-50"
            :disabled="deleting === item.data.id"
            @click.stop="remove(item.data)"
          >{{ deleting === item.data.id ? '...' : 'Zmazat' }}</button>
        </div>
      </div>
    </div>

    <p v-else class="text-sm text-slate-400">Ziadne obrazky.</p>
    <p v-if="uploadError" class="text-sm text-red-600">{{ uploadError }}</p>

    <!-- Lightbox -->
    <Teleport to="body">
      <Transition
        enter-active-class="transition duration-150"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition duration-100"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div
          v-if="lightbox"
          ref="lightboxEl"
          tabindex="-1"
          class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/85 p-4"
          @click.self="closeLightbox"
          @keydown.esc="closeLightbox"
          @keydown.left="lightboxPrev"
          @keydown.right="lightboxNext"
        >
          <button class="absolute right-4 top-4 rounded-full bg-white/10 p-2 text-white hover:bg-white/20" @click="closeLightbox">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>

          <button
            v-if="lightboxIdx !== null && lightboxIdx > 0"
            class="absolute left-4 top-1/2 -translate-y-1/2 rounded-full bg-white/10 p-3 text-white hover:bg-white/20"
            @click="lightboxPrev"
          >
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
            </svg>
          </button>

          <button
            v-if="lightboxIdx !== null && lightboxIdx < images.length - 1"
            class="absolute right-4 top-1/2 -translate-y-1/2 rounded-full bg-white/10 p-3 text-white hover:bg-white/20"
            @click="lightboxNext"
          >
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
            </svg>
          </button>

          <img
            :src="lightbox.largeUrl ?? lightbox.thumbUrl ?? lightbox.url"
            :alt="lightbox.name"
            class="max-h-[90vh] max-w-[90vw] rounded-xl object-contain shadow-2xl"
          />

          <div v-if="images.length > 1" class="absolute bottom-4 left-1/2 -translate-x-1/2 rounded-full bg-black/50 px-3 py-1 text-xs text-white">
            {{ lightboxIdx !== null ? lightboxIdx + 1 : 0 }} / {{ images.length }}
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, nextTick, onMounted } from 'vue'
import { listFiles, uploadFiles, updateFile, deleteFile, reorderFiles, type FileItem } from '@/api/files'
import { useToast } from '@/composables/useToast'

const props = defineProps<{
  fileableType: 'canal' | 'event' | 'venue'
  fileableId: number
}>()

interface PendingItem {
  type: 'pending'
  key: string
  name: string
  objectUrl: string
}
interface UploadedItem {
  type: 'uploaded'
  key: string
  data: FileItem
}
type GridItem = PendingItem | UploadedItem

const toast = useToast()
const images = ref<FileItem[]>([])
const pendingPreviews = ref<PendingItem[]>([])
const uploading = ref(false)
const uploadError = ref<string | null>(null)
const settingPrimary = ref<number | null>(null)
const deleting = ref<number | null>(null)
const isDraggingOver = ref(false)
const fileInputEl = ref<HTMLInputElement | null>(null)

// objectUrls kept alive until server image loads successfully
// Map<fileId, objectUrl>
const postUploadPreviews = ref(new Map<number, string>())

const allItems = computed<GridItem[]>(() => [
  ...images.value.map(img => ({ type: 'uploaded' as const, key: `u-${img.id}`, data: img })),
  ...pendingPreviews.value,
])

// Image src: prefer objectUrl while server image hasn't loaded yet
const failedSrcs = ref(new Set<number>())

function imgSrc(img: FileItem): string | null {
  // Show objectUrl preview until server image loads (avoids white square during thumb generation)
  const pending = postUploadPreviews.value.get(img.id)
  if (pending) return pending

  if (failedSrcs.value.has(img.id)) {
    return (img.url && !img.url.includes('document-placeholder')) ? img.url : null
  }

  const candidate = img.thumbUrl ?? img.url
  return (candidate && !candidate.includes('document-placeholder')) ? candidate : null
}

function onImgLoad(img: FileItem) {
  // Server image loaded — safe to release objectUrl preview
  const objectUrl = postUploadPreviews.value.get(img.id)
  if (objectUrl) {
    postUploadPreviews.value.delete(img.id)
    URL.revokeObjectURL(objectUrl)
  }
  failedSrcs.value.delete(img.id)
}

function onImgError(e: Event, img: FileItem) {
  const el = e.target as HTMLImageElement
  const pending = postUploadPreviews.value.get(img.id)
  // If objectUrl is showing and fails, try server URL
  if (pending) {
    const serverSrc = img.thumbUrl ?? img.url
    if (serverSrc && !serverSrc.includes('document-placeholder')) {
      el.src = serverSrc
      return
    }
    // No valid server URL either — clear objectUrl and show placeholder div
    postUploadPreviews.value.delete(img.id)
    URL.revokeObjectURL(pending)
    return
  }
  // thumbUrl failed, try url
  if (!failedSrcs.value.has(img.id)) {
    failedSrcs.value.add(img.id)
    const fallback = img.url
    if (fallback && !fallback.includes('document-placeholder') && fallback !== el.src) {
      el.src = fallback
      return
    }
  }
}

// Drag-to-reorder
const dragging = ref<string | null>(null)
const draggingFromIdx = ref<number | null>(null)
const dragOverIdx = ref<number | null>(null)

function onDragStart(key: string, idx: number) {
  dragging.value = key
  draggingFromIdx.value = idx
}

async function onReorderDrop(toIdx: number) {
  if (draggingFromIdx.value === null || draggingFromIdx.value === toIdx) return
  const arr = [...images.value]
  const [moved] = arr.splice(draggingFromIdx.value, 1)
  arr.splice(toIdx, 0, moved)
  const reordered = arr.map((img, i) => ({ ...img, sortOrder: i }))
  images.value = reordered
  dragOverIdx.value = null
  try {
    await reorderFiles(reordered.map(img => ({ id: img.id, sort_order: img.sortOrder })))
  } catch {
    toast.error('Zmena poradia zlyhala.')
    await load()
  }
}

function onDragEnd() {
  dragging.value = null
  draggingFromIdx.value = null
  dragOverIdx.value = null
}

// Lightbox
const lightboxEl = ref<HTMLElement | null>(null)
const lightboxIdx = ref<number | null>(null)
const lightbox = computed<FileItem | null>(() =>
  lightboxIdx.value !== null ? (images.value[lightboxIdx.value] ?? null) : null
)

function openLightbox(img: FileItem) {
  const idx = images.value.findIndex(i => i.id === img.id)
  lightboxIdx.value = idx >= 0 ? idx : 0
  nextTick(() => lightboxEl.value?.focus())
}

function closeLightbox() {
  lightboxIdx.value = null
}

function lightboxPrev() {
  if (lightboxIdx.value !== null && lightboxIdx.value > 0) lightboxIdx.value--
}

function lightboxNext() {
  if (lightboxIdx.value !== null && lightboxIdx.value < images.value.length - 1) lightboxIdx.value++
}

watch(lightbox, (val) => {
  if (val) nextTick(() => lightboxEl.value?.focus())
})

// Load
async function load() {
  try {
    const files = await listFiles({ fileable_type: props.fileableType, fileable_id: props.fileableId })
    images.value = [...files].sort((a, b) => a.sortOrder - b.sortOrder || a.id - b.id)
  } catch { /* ignore */ }
}

// Upload
async function uploadBatch(files: File[]) {
  uploadError.value = null
  const previews: PendingItem[] = files.map(f => ({
    type: 'pending' as const,
    key: `p-${f.name}-${f.size}-${Date.now()}-${Math.random()}`,
    name: f.name,
    objectUrl: URL.createObjectURL(f),
  }))
  pendingPreviews.value = [...pendingPreviews.value, ...previews]
  uploading.value = true

  try {
    const fd = new FormData()
    fd.append('fileable_type', props.fileableType)
    fd.append('fileable_id', String(props.fileableId))
    fd.append('type', 'image')
    fd.append('make_primary', images.value.length === 0 ? '1' : '0')
    files.forEach(f => fd.append('files[]', f))

    const uploaded = await uploadFiles(fd)

    // Build objectUrl map BEFORE removing pending previews
    // Each uploaded file gets the objectUrl of its matching preview (by index)
    uploaded.forEach((fileItem, i) => {
      const preview = previews[i]
      if (preview) {
        postUploadPreviews.value.set(fileItem.id, preview.objectUrl)
      }
    })

    images.value = [...images.value, ...uploaded].sort((a, b) => a.sortOrder - b.sortOrder || a.id - b.id)
    await nextTick()
    toast.success(`Nahrane ${uploaded.length} obrazkov.`)
  } catch {
    uploadError.value = 'Nahravanie zlyhalo.'
    // Revoke objectUrls that won't be transferred
    previews.forEach(p => URL.revokeObjectURL(p.objectUrl))
  } finally {
    const keys = new Set(previews.map(p => p.key))
    pendingPreviews.value = pendingPreviews.value.filter(p => !keys.has(p.key))
    // Note: objectUrls are NOT revoked here — they live in postUploadPreviews until @load fires
    uploading.value = false
    if (fileInputEl.value) fileInputEl.value.value = ''
  }
}

function onFileInput(e: Event) {
  const files = Array.from((e.target as HTMLInputElement).files ?? [])
  if (files.length) uploadBatch(files)
}

function onDrop(e: DragEvent) {
  isDraggingOver.value = false
  const files = Array.from(e.dataTransfer?.files ?? []).filter(f => f.type.startsWith('image/'))
  if (files.length) uploadBatch(files)
}

async function setPrimary(img: FileItem) {
  settingPrimary.value = img.id
  try {
    await updateFile(img.id, { is_primary: true })
    images.value = images.value.map(i => ({ ...i, isPrimary: i.id === img.id }))
  } catch {
    toast.error('Nepodarilo sa nastavit hlavnu fotku.')
  } finally {
    settingPrimary.value = null
  }
}

async function remove(img: FileItem) {
  if (!confirm('Zmazat obrazok?')) return
  deleting.value = img.id
  try {
    await deleteFile(img.id)
    images.value = images.value.filter(i => i.id !== img.id)
    if (img.isPrimary && images.value.length) await setPrimary(images.value[0])
    // Clean up any pending preview for deleted image
    const objectUrl = postUploadPreviews.value.get(img.id)
    if (objectUrl) {
      postUploadPreviews.value.delete(img.id)
      URL.revokeObjectURL(objectUrl)
    }
  } catch {
    toast.error('Mazanie zlyhalo.')
  } finally {
    deleting.value = null
  }
}

onMounted(load)
</script>
