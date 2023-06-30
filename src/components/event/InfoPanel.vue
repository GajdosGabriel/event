<script lang="ts">
import { defineComponent, PropType } from "vue";
import type { Event } from "../../types/event";

export default defineComponent({
  props: {
    item: {
      type: Object as PropType<Event>,
      required: true
    },
  },

  setup() { },
});
</script>
<template>
  <div class="border-4 border-gray-300 rounded-md shadow-md">
    <div class="p-3 text-lg">
      <div class="font-semibold">{{ item.canal_name }}</div>
      <div>dňa {{ item.start_at_date }} o {{ item.start_at_time }}</div>
      <div>Koniec</div>
      <div>dňa {{ item.end_at_date }} o {{ item.end_at_time }}</div>
      <div>Miesto <span class="font-semibold">{{ item.village_name }}</span></div>
    </div>
  </div>
</template>
