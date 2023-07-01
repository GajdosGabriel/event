<script lang="ts">
import { defineComponent } from "vue";
// import { Event } from "../../../types/event";

import CardAside from "../CardAside.vue";
import FooterComponent from "../Footer.vue";
import Spinner from "../Spinner.vue";
import UseUser from "../../../store/user.js";
import UserEventCard from "@/components/event/UserEventCard.vue";

export default defineComponent({
  components: { CardAside, Spinner, FooterComponent, UserEventCard },
  setup() {
    const { loading, user } = UseUser();

    return { user, loading };
  },
});
</script>

<template>
  <div class="md:w-10/12 mx-auto p-6">
    <div class="md:grid grid-cols-12 gap-10">
      <div class="col-span-8 h-screen">
        <!-- <Header></Header> -->
        <Spinner v-if="loading"></Spinner>

        <div class="space-y-5">
          <h1 class="font-semibold text-2xl">User home</h1>
          <user-event-card :item="{ ffffffffffffff: 'dddasdf' }" />
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
