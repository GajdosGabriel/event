<script setup lang="ts">
import { defineComponent, type PropType } from "vue";

defineProps({
  item: {
    type: Object as PropType<Event>,
  },
});
</script>

<template>
  <div class="bg-red-700 text-gray-200 p-4">
    {{ item.id }}
    {{ item.title }}
    {{ item }}
  </div>
</template>
