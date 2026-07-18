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
      <p v-if="meta" class="mt-1 flex items-center gap-1.5 text-xs text-slate-500">{{ meta }}</p>
      <p v-if="description" class="mt-1 text-sm text-slate-700">{{ description }}</p>
      <slot name="detail" />
    </div>

    <div class="col-span-2 flex justify-start md:col-span-1 md:justify-center">
      <span class="index-row-status" :class="{ 'status-live': statusValue === 'published' }">{{ status }}</span>
    </div>

    <div class="index-row-actions">
      <slot name="actions" />
    </div>
  </div>
</template>

<script setup lang="ts">
defineProps<{
  title: string
  imageUrl?: string | null
  meta?: string
  description?: string
  status?: string
  statusValue?: string
  showLink?: string | object
}>()
</script>
