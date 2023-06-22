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
  <div class="border-gray-300 border-2 rounded-sm hover:shadow-md cursor-pointer">
    <div class="overflow-hidden max-h-40 ">
      <img :src="item.image_thumb" alt="" class="w-full" />
    </div>
    <div class="p-3">
      <span class="font-medium ">{{ item.title }}</span>
     
      <div class="font-light text-sm">Dňa {{ item.created_at }}</div>
    </div>


  </div>
</template>
