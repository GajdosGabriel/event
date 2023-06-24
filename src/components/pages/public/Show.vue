<script lang="ts">
import { defineComponent, onBeforeUnmount, onMounted, ref } from "vue";
import { Event } from "../../../types/event";
import { useRoute } from "vue-router";
import CardAside from "../CardAside.vue";
import UseEvent from "../../../composeable/events.js";
import SubscribeForm from "../../event/subscribeForm.vue";
import PictureViewer from "../../event/PictureViewer.vue";

export default defineComponent({
  components: { CardAside, SubscribeForm, PictureViewer },
  setup() {
    const {
      params: { eventId },
    } = useRoute();
    const { state, getEvent, resetEvent } = UseEvent();

    onMounted(() => {
      getEvent(eventId);
    });

    onBeforeUnmount(() => {
      resetEvent();
    });

    return { state, getEvent };
  },
});
</script>

<template>
  <div class="md:w-10/12 mx-auto p-6 h-screen">
    <div class="md:grid grid-cols-12 gap-10">
      <article class="col-span-8">
        <h1 class="text-3xl font-semibold">{{ state.event.title }}</h1>

        <div class="border-4 border-gray-300 rounded-md shadow-md my-8">
          <div class="p-3">
            <span>{{ state.event.canal_name }} Vás pozáva</span>
            <span> dňa {{ state.event.start_at_date }}</span>
            <span> o {{ state.event.start_at_time }}</span>
            <div>Koniec {{ state.event.end_at_date }}</div>
            <div>o {{ state.event.end_at_time }}</div>
            <div>Kde {{ state.event.village_name }}</div>
          </div>
        </div>
        <div v-html="state.event.body"></div>
       <picture-viewer :item="state.event"/>
      </article>

      <div class="col-span-3">
        <subscribe-form />
        <CardAside>
          <template v-slot:title>Info o akcii</template>
          <template v-slot:body>Body text</template>
        </CardAside>

        <CardAside>
          <template v-slot:title>Diskusia s organizátorom</template>
          <template v-slot:body>Body text</template>
        </CardAside>
      </div>
    </div>
  </div>
</template>
