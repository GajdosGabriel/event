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

  setup() {

  },
});
</script>

<template>
  <router-link
    :to="{
      name: 'event.show',
      params: {
        eventId: 'item.id',
        eventSlug: 'item.slug',
      },
      query: { pageTitle: 'item.title' },
    }"
  >
    <div class="md:grid grid-cols-8 gap-4">
      <div class="col-span-6">
        <div class="post-header">
          <div class="title flex justify-between">
            <h5 class="text-lg font-semibold"><a href="#"> Title </a></h5>

            <div>drop down</div>
          </div>

          <div class="text-gray-600 text-sm">limit 200 text</div>
        </div>
      </div>

      <div class="col-span-2 bg-gray-100 p-2 rounded-md">
        <a href="#">
          <div class="">miesto</div>
        </a>
        <div class="">čas</div>

        <div>
          Pridal:
          <a href="#" class="hover:underline"> Organizátor </a>
        </div>
      </div>
    </div>
  </router-link>
</template>
