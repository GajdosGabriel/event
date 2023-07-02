<script lang="ts">
import { defineComponent, watch, onMounted } from "vue";
// import { Event } from "../../../types/event";

import CardAside from "../CardAside.vue";
import FooterComponent from "../Footer.vue";
import Spinner from "../Spinner.vue";
import UseUser from "../../../store/user.js";
import UseEvent from "../../../store/event.js";
import UserEventCard from "@/components/event/UserEventCard.vue";

export default defineComponent({
  components: { CardAside, Spinner, FooterComponent, UserEventCard },
  setup() {
    const {  user } = UseUser();
    const { loading, setUrl, fetchEvents, events } = UseEvent();

    onMounted(() => {
      setUrl('/api/user/271/event');
      fetchEvents();
    });

    watch(user, () => {
       setUrl('/api/user/'+ user.canal_id +'/event');
    });


    return { user, events, loading };
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
          <h1 class="font-semibold text-2xl">User home</h1>
          <ul>
          <user-event-card :item="event" v-for="event in events" :key="event.id" />
        </ul>
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
