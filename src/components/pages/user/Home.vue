<script lang="ts">
import { defineComponent, onMounted, ref } from "vue";
// import { Event } from "../../../types/event";
import Header from "../Header.vue";
import IndexCard from "../../event/IndexCard.vue";
import CardAside from "../CardAside.vue";
import PaginationComponent from "../PaginationComponent.vue";
import FooterComponent from "../Footer.vue";
import Spinner from "../Spinner.vue";
import UseUser from "../../../store/user.js";


export default defineComponent({
  components: { Header, IndexCard, CardAside, PaginationComponent, Spinner, FooterComponent },
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
            <h1 class="font-semibold text-2xl ">User home</h1>
    
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
