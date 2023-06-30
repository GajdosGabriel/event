<script lang="ts">
import { defineComponent, PropType } from "vue";
import type { Event } from "../../types/vent";

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
  <figure>
    <img :src="item.image_url" :alt="item.title" :title="item.slug" class="w-full my-10 rounded-md" />
  </figure>
</template>
