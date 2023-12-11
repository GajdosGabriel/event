<script lang="ts">
import { defineComponent, onMounted, ref } from "vue";
// import { Event } from "../../../types/event";
import Header from "../Header.vue";
import IndexCard from "../../event/IndexCard.vue";
import CardAside from "../CardAside.vue";
import PaginationComponent from "../PaginationComponent.vue";
import FooterComponent from "../Footer.vue";
import Spinner from "../Spinner.vue";

import UseEvent from "../../../store/event.js";
import EventVillageCard from "./EventVillageCard.vue";

export default defineComponent({
  components: { Header, IndexCard, CardAside, PaginationComponent, Spinner, FooterComponent, EventVillageCard },
  setup() {
    const { loading, meta, links, fetchEvents, events} = UseEvent();

    onMounted(() => {
      fetchEvents("/");
    });

    const paginatorUrl = (url: string) => {
      fetchEvents(url);
    };

    return { events, loading, meta, links, paginatorUrl };
  },
});
</script>

<template>
  <div class="md:w-10/12 mx-auto p-6">
    <div class="md:grid grid-cols-12 gap-10">
      <div class="col-span-8">
        <Header></Header>
        <Spinner v-if="loading"></Spinner>

        <div class="grid lg:grid-cols-3 sm:grid-cols-2 gap-8 pt-8">
          <!-- <div v-for="event in fetchEvents" :key="event.id">
          {{ event.id }}
          </div> -->
          <index-card :item="event" v-for="event in events" :key="event.id"></index-card>
        </div>
      </div>

      <div class="col-span-3">
        <event-village-card />

        <CardAside>
          <template v-slot:title>Aside</template>
          <template v-slot:body>Body text</template>
        </CardAside>
      </div>

      <div class="col-span-8">
        <pagination-component :meta="meta" :links="links" @fetchUrl="paginatorUrl" />
      </div>
    </div>
  </div>
  <footer-component />
</template>
