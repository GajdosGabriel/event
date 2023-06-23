<script lang="ts">
import { defineComponent, PropType, onMounted } from "vue";
import UseEvent from "../../composeable/events.js";

export default defineComponent({
  props: {
    item: {
      type: Object as PropType<Event>,
    },
  },

  setup() {


    const { state } = UseEvent();

 

  },
});
</script>

<template>
  <router-link :to="{
    name: 'event.show',
    params: {
      eventId: item.id,
      eventSlug: item.slug,
    }
  }">
    <div class="border-gray-300 border-2 rounded-sm hover:shadow-md cursor-pointer h-full">
      <div class="overflow-hidden max-h-40 ">
        <img :src="item.image_thumb" alt="" class="w-full" />
      </div>
      <div class="p-3">
        <span class="font-medium ">{{ item.title }}</span>

        <div class="font-light text-sm">Dňa {{ item.created_at }}</div>
      </div>


    </div>
  </router-link>
</template>
