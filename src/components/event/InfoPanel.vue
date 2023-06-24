<script lang="ts">
import { defineComponent, PropType } from "vue";
import { Event } from "../../types/event";

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
  <div class="border-4 border-gray-300 rounded-md shadow-md my-8">
    <div class="p-3">
      <span>{{ item.canal_name }} Vás pozáva</span>
      <span> dňa {{ item.start_at_date }}</span>
      <span> o {{ item.start_at_time }}</span>
      <div>Koniec {{ item.end_at_date }}</div>
      <div>o {{ item.end_at_time }}</div>
      <div>Kde {{ item.village_name }}</div>
    </div>
  </div>
</template>
