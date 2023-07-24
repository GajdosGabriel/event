<script lang="ts">
import { defineComponent, PropType } from "vue";
import UseEvent from "../../store/event.js";
import type { Event } from "../../types/event";
import PostDropDown from "../navigation/PostDropDown.vue";

export default defineComponent({
  components: { PostDropDown },
  props: {
    item: {
      type: Object as PropType<Event>,
      required: true,
    },
  },

  setup() { },
});
</script>

<template>
  <li class="md:grid grid-cols-8 gap-4 mb-8 bg-white border-solid border-2 rounded-md shadow-sm hover:shadow-md">
    <router-link :to="{
      name: 'event.show',
      params: {
        eventId: item.id,
        eventSlug: item.slug,
      },
      query: { pageTitle: item.title },
    }">
      <img :src="item.image_thumb" />
    </router-link>
    <div class="col-span-7">
      <div class="px-4 p-2">
        <div class="flex justify-between content-center ">
          <router-link :to="{
            name: 'event.show',
            params: {
              eventId: item.id,
              eventSlug: item.slug,
            },
            query: { pageTitle: item.title },
          }">
            <h5 class="text-lg font-semibold">{{ item.title }}</h5>
          </router-link>

          <post-drop-down />
        </div>

        <div class="text-gray-600 text-sm" v-html="item.body.slice(0, 100)"></div>
      </div>
    </div>

    <div class="col-span-8 bg-gray-100 p-2 rounded-md flex justify-between">

      <div class=""><span class="font-light">Kde:</span> {{ item.village_name }}</div>

      <div class=""><span class="font-light">Čas:</span> {{ item.start_at_date + " " + item.start_at_time }}</div>

      <div>
        <a href="#" class="hover:underline"><span class="font-light">Organizátor:</span> {{ item.canal_name }} </a>
      </div>
    </div>
  </li>
</template>
