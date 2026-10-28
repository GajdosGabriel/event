<template>
  <!-- Logo verejnej časti: znak + „tixio." s červenou bodkou. Farbu písma
       dedí z rodiča, takže sedí v tmavej hlavičke aj na svetlom podklade. -->
  <span class="inline-flex items-center gap-2 font-extrabold tracking-tight">
    <BrandMark class="h-7 w-7 shrink-0" />
    <span class="text-xl leading-none">tixio<span class="text-rose-500">.</span></span>
  </span>
</template>

<script setup lang="ts">
import BrandMark from './BrandMark.vue'
</script>
