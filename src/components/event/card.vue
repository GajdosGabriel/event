<script lang="ts">
import { defineComponent, PropType } from "vue";

export default defineComponent({
  props: {
    item: {
      type: Object as PropType<Event>,
    },
  },

  setup() {},
});
</script>

<template>
  <div class="border-gray-300 border-2 rounded-sm">
    <div class="w-full max-h-24 overflow-hidden">
      <img :src="item.image_thumb" alt="" />
    </div>
    <div class="p-3">
      <span class="font-medium ">{{ item.title }}</span>
      <!-- {{ item }} -->
    </div>
  </div>
</template>
