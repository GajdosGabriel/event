<script setup lang="ts">
import pageComponent from "./components/pages/page_3_2.vue";
</script>

<template>
  <router-view name="navigation" />

  <main class="bg-gray-50">
    <pageComponent>
      <template v-slot:content>
        <router-view></router-view>
      </template>

      <template v-slot:aside>
        <router-view name="aside"></router-view>
      </template>
    </pageComponent>
  </main>
</template>
