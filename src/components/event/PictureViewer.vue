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
  <figure>
    <img :src="item.image_url" :alt="item.slug" :title="item.slug" class="w-full my-10 rounded-md" />
  </figure>
</template>
