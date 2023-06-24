<script lang="ts">
import { defineComponent, onBeforeUnmount, onMounted, ref } from "vue";
import { Event } from "../../../types/event";
import { useRoute } from "vue-router";
import CardAside from "../CardAside.vue";
import UseEvent from "../../../composeable/events.js";
import SubscribeForm from "../../event/subscribeForm.vue";
import PictureViewer from "../../event/PictureViewer.vue";
import InfoPanel from "../../event/InfoPanel.vue";

export default defineComponent({
  components: { CardAside, SubscribeForm, PictureViewer, InfoPanel },
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

        <div class="md:grid grid-cols-12 gap-10  mt-6">
          <div class="col-span-7" v-html="state.event.body"></div>
          <div class="col-span-5">
            <info-panel :item="state.event" />
            <picture-viewer :item="state.event" />
          </div>
        </div>

        <picture-viewer :item="state.event" />
      </article>

      <section class="col-span-3">
        <subscribe-form />
        <CardAside>
          <template v-slot:title>Info o akcii</template>
          <template v-slot:body>Body text</template>
        </CardAside>

        <CardAside>
          <template v-slot:title>Diskusia s organizátorom</template>
          <template v-slot:body>Body text</template>
        </CardAside>
      </section>
    </div>
  </div>
</template>
