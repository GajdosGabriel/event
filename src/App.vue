<script setup lang="ts">
import FooterComponent from "./components/pages/Footer.vue";
</script>

<template>
  <router-view name="navigation" />

  <main class="bg-gray-50">
    <router-view></router-view>
  </main>
</template>
