<script lang="ts">
import { defineComponent, onMounted, ref } from "vue";
import { useRoute } from "vue-router";
import useEvent from "../../../store/event";

export default defineComponent({
  setup() {
    const { villages, fetchEventsVillages } = useEvent();
    onMounted(() => {
      fetchEventsVillages();
    });

    return { villages };
  },
});
</script>

<template>
  <section class="border-gray-700 rounded-md border-2 mb-10">
    <div class="flex justify-between p-2 rounded-t-md bg-blue-200 border-gray-300 border-2 shadow-md items-center">
      <h3 class="font-semibold text-lg">Akcie v mestách</h3>

      <div class="hidden sm:block">
        <div class="flex space-x-1">
          <a href="#">
            <div class="flex hover:bg-gray-100 p-2 rounded-full h-9 w-9 items-center justify-center">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
                stroke-width="1.5"
                stroke="currentColor"
                class="w-5 h-5"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"
                />
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
              </svg>
            </div>
          </a>
        </div>
      </div>
    </div>

    <div class="p-4">
      <ul class="divide-y-2 divide-gray-200 divide-dashed">
        <li v-for="village in villages" :key="village.id" class="flex justify-between">
          <router-link to="#">
            <!-- <i style="color: #3b32b3" class="fas fa-check"></i> -->
            {{ village.fullname }}
          </router-link>
          <span>(N)</span>
        </li>
        <li class="flex justify-between">
          <router-link to="#">
            <!-- <i style="color: #3b32b3" class="fas fa-check"></i> -->
            Online prenosy
          </router-link>
          <span>(N)</span>
        </li>
      </ul>
    </div>
  </section>
</template>
