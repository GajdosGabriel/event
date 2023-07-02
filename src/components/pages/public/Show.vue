<script lang="ts">
import { defineComponent, onBeforeUnmount, onMounted, ref } from "vue";
import Footer from "../Footer.vue";
import type { Event } from "../../../types/event";
import { useRoute } from "vue-router";
import CardAside from "../CardAside.vue";
import UseEvent from "../../../store/event.js";
import SubscribeForm from "../../event/subscribeForm.vue";
import PictureViewer from "../../event/PictureViewer.vue";
import InfoPanel from "../../event/InfoPanel.vue";
import Spinner from "../Spinner.vue";
import DropDown from "../../navigation/PostDropDown.vue";

export default defineComponent({
  components: { CardAside, SubscribeForm, PictureViewer, InfoPanel, Spinner, Footer, DropDown },
  setup() {
    const {
      params: { eventId },
    } = useRoute();
    const { event, loading, fetchEvent, resetEvent } = UseEvent();

    onMounted(() => {
      fetchEvent(eventId);
    });

    onBeforeUnmount(() => {
      resetEvent();
    });

    return { event, fetchEvent, loading };
  },
});
</script>

<template>
  <div class="md:w-10/12 mx-auto p-6">
    <div class="md:grid grid-cols-12 gap-10">
      <article class="col-span-8">
        <Spinner v-if="loading"></Spinner>
        <div class="flex justify-between ">
          <h1 class="text-3xl font-semibold">{{ event.title }}</h1>
          <drop-down/>
        </div>
      
        <div class="md:grid grid-cols-12 gap-10 mt-6">
          <div class="col-span-7 space-y-2" v-html="event.body"></div>
          <div class="col-span-5">
            <info-panel :item="event" />
            <picture-viewer :item="event" />
          </div>
        </div>

        <picture-viewer :item="event" />
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

  <Footer/>
</template>
