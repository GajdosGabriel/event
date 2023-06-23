<template>
  <div class="flex justify-center my-10 space-x-3">
    <button
      @click="fetchPaginate(links.prev)"
      class="flex items-center justify-center h-8 p-3 font-semibold bg-gray-400 border-1 border-gray-600 rounded-sm cursor-pointer"
      :disabled="!links.first"
    >
      <!--  Icon -->
      <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7" />
      </svg>
    </button>
    <div class="flex items-center justify-center h-8 p-3 font-semibold bg-gray-400 border-1 border-gray-600 rounded-sm">
      {{ meta.current_page }} / {{ meta.last_page }}
    </div>
    <button
      @click="fetchPaginate(links.next)"
      class="flex items-center justify-center h-8 p-3 font-semibold bg-gray-400 border-1 border-gray-600 rounded-sm cursor-pointer"
      :disabled="!links.next"
    >
      <!--  Icon -->
      <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7" />
      </svg>
    </button>
  </div>
</template>
<script lang="ts">
export default {
  props: ["meta", "links"],

  methods: {
    fetchPaginate(url:string) {
      this.$emit("fetchUrl", url);
    },
  },
};
</script>
