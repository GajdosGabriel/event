<script lang="ts">
import { defineComponent, watch, onMounted } from "vue";
// import { Event } from "@/types/event";

import CardAside from "../CardAside.vue";
import FooterComponent from "../Footer.vue";
import Spinner from "../Spinner.vue";
import UseUser from "../../../store/user.js";
import UseEvent from "../../../store/event.js";
import UserEventCard from "@/components/event/UserEventCard.vue";
import PaginationComponent from "../PaginationComponent.vue";

export default defineComponent({
  components: { CardAside, Spinner, FooterComponent, UserEventCard, PaginationComponent },
  setup() {
    const { user } = UseUser();
    const { loading,  fetchEvents, events, meta, links } = UseEvent();

    onMounted(async () => {
     await fetchEvents("/api/canal/"+ user.value.canal_id +"/event");
    });

    const paginatorUrl = (url: string) => {
      fetchEvents(url);
    };

    watch(user, () => {
     fetchEvents("/api/canal/"+ user.value.canal_id +"/event");
    });

    return { events, loading, meta, links, paginatorUrl };
  },
});
</script>

<template>
  <div class="md:w-10/12 mx-auto p-6">
    <div class="md:grid grid-cols-12 gap-10">
      <div class="col-span-8">
        <!-- <Header></Header> -->
        <Spinner v-if="loading"></Spinner>

        <div class="space-y-5">
          <div class="flex justify-between ">
            <h1 class="font-semibold text-2xl">Vaše pozvánky</h1>
            <router-link to="/user/events/create">
              <button class="btn btn-default">Nová akcia</button>
            </router-link>
          </div>

          <ul>
            <user-event-card :item="event" v-for="event in events" :key="event.id" />
          </ul>
        </div>

        <div class="col-span-8">
          <pagination-component :meta="meta" :links="links" @fetchUrl="paginatorUrl" />
        </div>
      </div>

      <div class="col-span-3">
        <CardAside>
          <template v-slot:title>Prevádzkovateľ</template>
          <template v-slot:body>Portál prevádzkuje ...</template>
        </CardAside>
      </div>
    </div>
  </div>
  <footer-component />
</template>
