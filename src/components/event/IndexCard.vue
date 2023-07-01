<script lang="ts">
import { defineComponent, PropType } from "vue";
import UseEvent from "../../store/event.js";
import type { Event } from "../../types/event";

export default defineComponent({
  props: {
    item: {
      type: Object as PropType<Event>,
      required: true,
    },
  },

  setup(props) {
    const { state } = UseEvent();
  },
});
</script>

<template>
  <router-link
    :to="{
      name: 'event.show',
      params: {
        eventId: item.id,
        eventSlug: item.slug,
      },
      query: { pageTitle: item.title },
    }"
  >
    <div class="border-gray-300 border-2 rounded-sm hover:shadow-md cursor-pointer h-full">
      <div class="overflow-hidden max-h-36">
        <img :src="item.image_thumb" alt="" class="w-full" />
      </div>
      <div class="p-3">
        <span class="font-medium">{{ item.title }}</span>

        <div class="font-light text-sm">Dňa {{ item.created_at }}</div>
      </div>
    </div>
  </router-link>
</template>
