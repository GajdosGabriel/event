<template>
  <div class="picker">
    <!-- Drop zone / trigger -->
    <div
      class="drop-zone"
      :class="{ 'drag-over': dragging }"
      @click="input?.click()"
      @dragover.prevent="dragging = true"
      @dragleave.prevent="dragging = false"
      @drop.prevent="onDrop"
    >
      <svg class="h-7 w-7 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/>
      </svg>
      <span class="text-sm text-slate-500">Kliknite alebo presuňte súbory sem</span>
      <span class="text-xs text-slate-400">JPG, PNG, WebP, GIF</span>
    </div>

    <input
      ref="input"
      type="file"
      accept="image/*"
      multiple
      class="hidden"
      @change="onFileInput"
    />

    <!-- Previews -->
    <div v-if="items.length" class="previews">
      <div v-for="(item, i) in items" :key="item.preview" class="preview-item">
        <img :src="item.preview" :alt="item.file.name" class="preview-img" />
        <button class="remove-btn" type="button" @click="remove(i)" title="Odstrániť">
          <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
          </svg>
        </button>
        <span class="preview-name">{{ item.file.name }}</span>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onBeforeUnmount } from 'vue'

interface PickerItem {
  file: File
  preview: string
}

const input = ref<HTMLInputElement | null>(null)
const dragging = ref(false)
const items = ref<PickerItem[]>([])

function addFiles(files: FileList | File[]) {
  for (const file of Array.from(files)) {
    if (!file.type.startsWith('image/')) continue
    items.value.push({ file, preview: URL.createObjectURL(file) })
  }
}

function onFileInput(e: Event) {
  const el = e.target as HTMLInputElement
  if (el.files) addFiles(el.files)
  el.value = ''
}

function onDrop(e: DragEvent) {
  dragging.value = false
  if (e.dataTransfer?.files) addFiles(e.dataTransfer.files)
}

function remove(i: number) {
  URL.revokeObjectURL(items.value[i].preview)
  items.value.splice(i, 1)
}

onBeforeUnmount(() => {
  items.value.forEach(it => URL.revokeObjectURL(it.preview))
})

defineExpose({
  files: computed(() => items.value.map(it => it.file)),
  clear() { items.value.forEach(it => URL.revokeObjectURL(it.preview)); items.value = [] },
})
</script>

<style scoped>
@reference "tailwindcss";

.picker { @apply flex flex-col gap-3; }

.drop-zone {
  @apply flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-slate-300 bg-slate-50 py-8 transition-colors hover:border-blue-400 hover:bg-blue-50;
}
.drag-over { @apply border-blue-500 bg-blue-50; }

.previews {
  @apply grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-4;
}

.preview-item {
  @apply relative aspect-square overflow-hidden rounded-xl border border-slate-200 bg-slate-100;
}
.preview-img { @apply h-full w-full object-cover; }
.remove-btn {
  @apply absolute right-1 top-1 flex h-5 w-5 items-center justify-center rounded-full bg-black/60 text-white hover:bg-black/80;
}
.preview-name {
  @apply absolute bottom-0 left-0 right-0 truncate bg-black/40 px-2 py-0.5 text-[10px] text-white;
}
</style>
