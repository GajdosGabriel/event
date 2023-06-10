<script setup lang="ts">
import pageComponent from "./components/pages/page_3_2.vue";
</script>

<template>
  <router-view name="navigation" />

  <main class="bg-gray-50">
    <pageComponent>
      <router-view></router-view>
    </pageComponent>
  </main>
</template>
