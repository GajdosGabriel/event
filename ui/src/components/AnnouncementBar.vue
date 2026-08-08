<template>
  <div v-if="items.length" class="announcement-strip" :class="`announcement-strip-${placement}`">
    <div
      v-for="item in items"
      :key="item.id"
      class="announcement"
      :class="`announcement-${item.variant}`"
    >
      <strong class="announcement-title">{{ item.title }}</strong>
      <div v-if="item.body" class="announcement-body prose prose-sm max-w-none" v-html="item.body" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { listActiveAnnouncements } from '@/api/announcements'
import type { AnnouncementItem, AnnouncementPlacement } from '@/api/announcements'

const props = defineProps<{ placement: AnnouncementPlacement }>()

const items = ref<AnnouncementItem[]>([])

// Oznam je dekorácia layoutu — keď sa nenačíta, stránka musí fungovať ďalej,
// preto sa chyba nikam nehlási.
onMounted(async () => {
  try {
    items.value = await listActiveAnnouncements(props.placement)
  } catch {
    items.value = []
  }
})
</script>

<style scoped>
@reference "tailwindcss";

.announcement-strip { @apply flex flex-col; }
.announcement-strip-bottom { @apply gap-2 px-3 py-3; }

.announcement { @apply px-3 py-2 text-center; }
.announcement-strip-bottom .announcement { @apply mx-auto w-full max-w-[1300px] rounded-lg shadow-sm; }
.announcement-title { @apply text-base font-semibold md:text-lg; }
/* Popis chodí ako HTML z editora — odkazy v ňom musia byť vidieť na farbe pásu. */
.announcement-body :deep(a) { @apply underline; }
.announcement-body :deep(p:last-child) { @apply mb-0; }
</style>
