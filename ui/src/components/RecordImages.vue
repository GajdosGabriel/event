<template>
  <div>
    <h2 class="mb-4 text-lg font-semibold text-slate-800">{{ t(`${kind}.sections.images` as MessageKey) }}</h2>
    <!-- Uložený záznam spravuje obrázky priamo na serveri, nový ich len
         zbiera, kým sa neuloží (nahrajú sa cez `files`). -->
    <ImageManager v-if="fileableId" ref="manager" :fileable-type="fileableType" :fileable-id="fileableId" />
    <ImagePicker v-else ref="picker" />
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import ImageManager from '@/components/ImageManager.vue'
import ImagePicker from '@/components/ImagePicker.vue'
import { t, type MessageKey } from '@/i18n'

const props = defineProps<{
  kind: 'canals' | 'venues'
  fileableType: 'canal' | 'venue'
  fileableId: number | null
}>()

const manager = ref<InstanceType<typeof ImageManager> | null>(null)
const picker = ref<InstanceType<typeof ImagePicker> | null>(null)

defineExpose({
  /** Má záznam aspoň jeden obrázok (uložený alebo čakajúci na nahratie)? */
  hasImages: computed(() =>
    props.fileableId ? (manager.value?.imageCount ?? 0) > 0 : (picker.value?.files.length ?? 0) > 0,
  ),
  /** Súbory čakajúce na nahratie po prvom uložení nového záznamu. */
  pendingFiles: computed<File[]>(() => picker.value?.files ?? []),
})
</script>
