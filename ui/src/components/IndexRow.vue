<template>
  <div class="index-row">
    <div class="index-row-thumb">
      <img v-if="imageUrl" :src="imageUrl" :alt="title" />
      <div v-else class="size-full bg-slate-100" />
    </div>

    <div class="min-w-0">
      <p class="text-base md:text-[0.97rem]">
        <RouterLink v-if="showLink" :to="showLink" class="text-slate-900 no-underline hover:underline">{{ title }}</RouterLink>
        <span v-else class="text-slate-900">{{ title }}</span>
      </p>
      <p v-if="meta || viewsCount !== null" class="mt-1 flex items-center gap-2 text-xs text-slate-500">
        <span v-if="meta">{{ meta }}</span>
        <!-- Počet zobrazení verejného detailu. Vidí ho len organizátor a admin,
             backend ho verejnosti do odpovede vôbec nedáva. -->
        <span
          v-if="viewsCount !== null"
          class="inline-flex items-center gap-1"
          :title="`${viewsCount} zobrazení verejného detailu`"
        >
          <svg class="h-3.5 w-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
            <circle cx="12" cy="12" r="3" />
          </svg>
          {{ viewsCount }}
        </span>
      </p>
      <p v-if="description" class="mt-1 text-sm text-slate-700">{{ description }}</p>
      <slot name="detail" />
    </div>

    <div class="flex justify-start md:col-span-1 md:justify-center">
      <span class="index-row-status" :class="{ 'status-live': statusValue === 'published' }">{{ status }}</span>
    </div>

    <div class="index-row-actions">
      <slot name="actions" />
    </div>
  </div>
</template>

<script setup lang="ts">
withDefaults(defineProps<{
  title: string
  imageUrl?: string | null
  meta?: string
  description?: string
  status?: string
  statusValue?: string
  showLink?: string | object
  /** Počet zobrazení; null = používateľ naň nemá právo, číslo sa nevykreslí. */
  viewsCount?: number | null
}>(), {
  viewsCount: null,
})
</script>
