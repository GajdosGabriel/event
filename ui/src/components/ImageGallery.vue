<template>
  <div v-if="images.length" class="space-y-2">
    <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-4">
      <div
        v-for="(img, idx) in images"
        :key="img.id"
        class="group relative aspect-square cursor-zoom-in overflow-hidden rounded-xl bg-slate-100"
        @click="open(idx)"
      >
        <img
          v-if="imgSrc(img)"
          :src="imgSrc(img)!"
          :alt="img.name"
          class="h-full w-full object-cover transition-transform duration-200 group-hover:scale-105"
          @error="onError($event, img)"
        />
        <div v-else class="flex h-full w-full items-center justify-center bg-slate-100">
          <svg class="h-8 w-8 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"/>
          </svg>
        </div>
      </div>
    </div>

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
          v-if="lightboxIdx !== null"
          ref="lightboxEl"
          tabindex="-1"
          class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/85 p-4"
          @click.self="close"
          @keydown.esc="close"
          @keydown.left="prev"
          @keydown.right="next"
        >
          <button class="absolute right-4 top-4 rounded-full bg-white/10 p-2 text-white hover:bg-white/20" @click="close">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>

          <button v-if="lightboxIdx > 0" class="absolute left-4 top-1/2 -translate-y-1/2 rounded-full bg-white/10 p-3 text-white hover:bg-white/20" @click="prev">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
            </svg>
          </button>
          <button v-if="lightboxIdx < images.length - 1" class="absolute right-4 top-1/2 -translate-y-1/2 rounded-full bg-white/10 p-3 text-white hover:bg-white/20" @click="next">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
            </svg>
          </button>

          <img
            :src="lightboxSrc"
            :alt="images[lightboxIdx]?.name"
            class="max-h-[90vh] max-w-[90vw] rounded-xl object-contain shadow-2xl"
          />

          <div v-if="images.length > 1" class="absolute bottom-4 left-1/2 -translate-x-1/2 rounded-full bg-black/50 px-3 py-1 text-xs text-white">
            {{ lightboxIdx + 1 }} / {{ images.length }}
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, nextTick, onMounted } from 'vue'
import { listFiles, listPublicEventFiles, type FileItem } from '@/api/files'

const props = defineProps<{
  fileableType: 'canal' | 'event' | 'venue'
  fileableId: number
  public?: boolean
}>()

const images = ref<FileItem[]>([])
const lightboxIdx = ref<number | null>(null)
const lightboxEl = ref<HTMLElement | null>(null)
const brokenSrcs = ref(new Set<number>())

const PLACEHOLDER = 'document-placeholder'

function imgSrc(img: FileItem): string | null {
  if (brokenSrcs.value.has(img.id)) return (img.url && !img.url.includes(PLACEHOLDER)) ? img.url : null
  const c = img.thumbUrl ?? img.url
  return (c && !c.includes(PLACEHOLDER)) ? c : null
}

function onError(e: Event, img: FileItem) {
  const el = e.target as HTMLImageElement
  if (!brokenSrcs.value.has(img.id) && img.url && !img.url.includes(PLACEHOLDER) && img.url !== el.src) {
    brokenSrcs.value.add(img.id)
    el.src = img.url
  }
}

const lightboxSrc = computed(() => {
  if (lightboxIdx.value === null) return ''
  const img = images.value[lightboxIdx.value]
  return img?.largeUrl ?? img?.thumbUrl ?? img?.url ?? ''
})

function open(idx: number) {
  lightboxIdx.value = idx
  nextTick(() => lightboxEl.value?.focus())
}
function close() { lightboxIdx.value = null }
function prev() { if (lightboxIdx.value !== null && lightboxIdx.value > 0) lightboxIdx.value-- }
function next() { if (lightboxIdx.value !== null && lightboxIdx.value < images.value.length - 1) lightboxIdx.value++ }

onMounted(async () => {
  try {
    const files = props.public && props.fileableType === 'event'
      ? await listPublicEventFiles(props.fileableId)
      : await listFiles({ fileable_type: props.fileableType, fileable_id: props.fileableId })
    images.value = [...files].sort((a, b) => a.sortOrder - b.sortOrder || a.id - b.id)
  } catch { /* ignore */ }
})
</script>
