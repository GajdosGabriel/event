<script lang="ts">
import { defineComponent, PropType } from "vue";
import UseEvent from "../../store/event.js";
import { useRouter } from "vue-router";
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

  setup(props) {
    const router = useRouter();

    const clickOnBody = () => {
      router.push(
        {
          name: 'event.show',
          params: {
            eventId: props.item.id,
            eventSlug: props.item.slug,
          },
          query: { pageTitle: props.item.title },
        }
      )
    }

    return { clickOnBody }
  }
});
</script>

<template>
  <li class="md:grid grid-cols-8 gap-4 mb-8 bg-white border-solid border-2 rounded-md shadow-sm hover:shadow-md">
    <img :src="item.image_thumb" @click="clickOnBody" class="cursor-pointer " />
    <div class="col-span-7">
      <div class="px-4 p-2">
        <div class="flex justify-between content-center ">

          <h5 class="text-lg font-semibold cursor-pointer" @click="clickOnBody">{{ item.title }}</h5>

          <post-drop-down />
        </div>

        <div class="text-gray-600 text-sm cursor-pointer" v-html="item.body.slice(0, 100)" @click="clickOnBody"></div>
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
