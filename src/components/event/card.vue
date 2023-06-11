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
  <div class="bg-red-700 text-gray-200 p-4">
    {{ item.id }}
    {{ item.title }}
    <!-- {{ item }} -->
  </div>
</template>
