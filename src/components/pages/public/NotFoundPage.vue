<script lang="ts">
import { defineComponent } from "vue";
// import { Event } from "../../../types/event";
import CardAside from "../CardAside.vue";

export default defineComponent({
  components: { CardAside },
  setup() {},
});
</script>

<template>
  <div class="md:w-10/12 mx-auto p-6 h-screen">
    <div class="md:grid grid-cols-12 gap-10">
      <article class="col-span-8">
        <h1 class="text-3xl font-semibold">404 Stránka sa nenašla</h1>

        <button
          @click="$router.go(-1)"
          class="bg-blue-800 text-gray-200 text-2xl p-2 px-5 rounded-md mt-6 font-medium hover:bg-blue-600"
        >
          Späť
        </button>
      </article>

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
