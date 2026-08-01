<template>
  <div v-if="allFiles.length" class="space-y-2">
    <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-4">
      <div
        v-for="file in allFiles"
        :key="file.id"
        class="group relative aspect-square overflow-hidden rounded-xl bg-slate-100"
        :class="isImage(file) ? 'cursor-zoom-in' : 'cursor-pointer'"
        @click="handleClick(file)"
      >
        <!-- Image (also used for a PDF/DOC's generated preview thumbnail, when one exists) -->
        <img
          v-if="imgSrc(file)"
          :src="imgSrc(file)!"
          :alt="file.name"
          class="h-full w-full object-cover transition-transform duration-200 group-hover:scale-105"
          @error="onError($event, file)"
        />

        <!-- Document tile (no preview available yet/at all — e.g. conversion failed) -->
        <div v-else
          class="flex h-full w-full flex-col items-center justify-center gap-2 bg-red-50 p-3 transition-colors group-hover:bg-red-100">
          <svg class="h-10 w-10 shrink-0 text-red-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
          </svg>
          <span class="line-clamp-2 w-full text-center text-xs text-slate-600 leading-tight">{{ file.name }}</span>
        </div>

        <!-- Document badge: overlaid on a generated preview so it's still clear this is a PDF/DOC -->
        <div v-if="!isImage(file) && imgSrc(file)" class="absolute left-1.5 top-1.5 rounded bg-black/70 px-1.5 py-0.5 text-[9px] font-semibold uppercase text-white">
          {{ extensionLabel(file) }}
        </div>
      </div>
    </div>

    <!-- Lightbox (images only) -->
    <ImageLightbox v-model:index="lightboxIdx" :images="lightboxImages" />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { listFiles, listPublicEventFiles, listPublicVenueFiles, type FileItem } from '@/api/files'
import { isImageFile as isImage, extensionLabel, openOriginal, useFilePreview } from '@/composables/useFilePreview'
import ImageLightbox from '@/components/ImageLightbox.vue'

const props = defineProps<{
  fileableType: 'canal' | 'event' | 'venue'
  fileableId: number
  public?: boolean
}>()

const allFiles = ref<FileItem[]>([])
const lightboxIdx = ref<number | null>(null)

const { imgSrc, onImgError: onError } = useFilePreview()

const imageFiles = computed(() => allFiles.value.filter(f => isImage(f)))

const lightboxImages = computed(() => imageFiles.value.map(img => ({
  src: img.largeUrl ?? img.thumbUrl ?? img.url ?? '',
  zoomSrc: img.url ?? undefined,
  alt: img.name,
})))

function handleClick(file: FileItem) {
  if (!isImage(file)) {
    openOriginal(file)
    return
  }
  const idx = imageFiles.value.findIndex(f => f.id === file.id)
  if (idx !== -1) lightboxIdx.value = idx
}

onMounted(async () => {
  try {
    const files = props.public
      ? props.fileableType === 'venue'
        ? await listPublicVenueFiles(props.fileableId)
        : await listPublicEventFiles(props.fileableId)
      : await listFiles({ fileable_type: props.fileableType, fileable_id: props.fileableId })
    allFiles.value = [...files].sort((a, b) => a.sortOrder - b.sortOrder || a.id - b.id)
  } catch { /* ignore */ }
})
</script>
