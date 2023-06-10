<script lang="ts">
import { defineComponent, onMounted, ref } from "vue";
import Header from "../Header.vue";
import Card from "../../templates/card.vue";

import UseEvent from "../../../composeable/events.js";

export default defineComponent({
  components: { Header, Card },
  setup() {
    const { state, getEvents, setEvents } = UseEvent();

    onMounted(() => {
      setEvents();
    });

    return { state, getEvents };
  },
});
</script>

<template>
  <div class="md:w-10/12 mx-auto p-6 h-screen">
    <div class="md:grid grid-cols-12 gap-10">
      <div class="col-span-8">
        <Header></Header>

        <div class="grid lg:grid-cols-4 sm:grid-cols-2 gap-8 pt-8">
          <!-- <div v-for="event in getEvents" :key="event.id">
          {{ event.id }}
          </div> -->
          <Card :item="event" v-for="event in getEvents" :key="event.id"></Card>
        </div>
      </div>

      <div class="col-span-3">
        <!-- <slot name="aside">Aside je prázdny</slot> -->
      </div>
    </div>
  </div>
</template>
