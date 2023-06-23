<script lang="ts">
import { defineComponent, onMounted, ref } from "vue";
// import { Event } from "../../../types/event";
import CardAside from "../CardAside.vue";
import UseEvent from "../../../composeable/events.js";
import SubscribeForm from "../../event/subscribeForm.vue";

export default defineComponent({
  components: { CardAside, SubscribeForm },
  setup() {
    const { state, getEvents } = UseEvent();

    onMounted(() => {
      getEvents();
    });

    return { state, getEvents };
  },
});
</script>

<template>
  <div class="md:w-12/12 mx-auto p-6 h-screen">
    <div class="md:grid grid-cols-12 gap-16">
      <div class="col-span-8">
        <h1 class="text-3xl font-semibold">Title</h1>

        <div class="border-4 border-gray-300 p-3 rounded-md shadow-md my-8">panel sumár</div>
        <p>paragraf</p>
      </div>

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
