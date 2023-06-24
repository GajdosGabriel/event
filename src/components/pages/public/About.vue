<script lang="ts">
import { defineComponent, onMounted, ref } from "vue";
// import { Event } from "../../../types/event";
import Header from "../Header.vue";
import Card from "../../event/Card.vue";
import CardAside from "../CardAside.vue";
import PaginationComponent from "../PaginationComponent.vue";
import FooterComponent from "../Footer.vue";

import UseEvent from "../../../composeable/events.js";

export default defineComponent({
  components: { Header, Card, CardAside, PaginationComponent, FooterComponent },
  setup() {
    const { state, getEvents, paginationUrl } = UseEvent();

    onMounted(() => {
      getEvents();
    });

    const paginatorUrl = (url:string) => {
           paginationUrl(url);
           getEvents();
        };

    return { state, getEvents, paginatorUrl };
  },
});
</script>

<template>
  <div class="md:w-10/12 mx-auto p-6 h-screen">
    <div class="md:grid grid-cols-12 gap-10">
      <div class="col-span-8">
        <Header></Header>

        <div class="grid lg:grid-cols-3 sm:grid-cols-2 gap-8 pt-8">
          <!-- <div v-for="event in getEvents" :key="event.id">
          {{ event.id }}
          </div> -->
          <Card :item="event" v-for="event in state.events" :key="event.id"></Card>
        </div>
      </div>

      <div class="col-span-3">
        <CardAside>
          <template v-slot:title>Aside</template>
          <template v-slot:body>Body text</template>
        </CardAside>
      </div>

      <div class="col-span-8">
        <pagination-component :meta="state.meta" :links="state.links" @fetchUrl="paginatorUrl"></pagination-component>
      </div>
    </div>
  </div>
</template>
