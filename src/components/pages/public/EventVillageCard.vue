<script lang="ts">
import { defineComponent, onMounted, ref, watch } from "vue";
import useEvent from "../../../store/event";

export default defineComponent({
  setup() {
    const { villages, fetchEventsVillages, fetchEvents } = useEvent();
    onMounted(() => {
      fetchEventsVillages();
    });

    const selectedVillage = ref('');

    watch(selectedVillage, () => {
      fetchEvents("?location=", selectedVillage.value);
    })

    return { villages, selectedVillage };
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
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                stroke="currentColor" class="w-5 h-5">
                <path stroke-linecap="round" stroke-linejoin="round"
                  d="M3 3v1.5M3 21v-6m0 0l2.77-.693a9 9 0 016.208.682l.108.054a9 9 0 006.086.71l3.114-.732a48.524 48.524 0 01-.005-10.499l-3.11.732a9 9 0 01-6.085-.711l-.108-.054a9 9 0 00-6.208-.682L3 4.5M3 15V4.5" />
              </svg>
            </div>
          </a>
        </div>
      </div>
    </div>

    <div class="">
      <ul class="divide-y-2 divide-gray-200 divide-dashed">
        <li v-for="(village, index) in villages" :key="village.id" @click="selectedVillage = village[0].id"
          class="flex justify-between cursor-pointer hover:bg-slate-100 px-4"
          :class="{ 'bg-slate-200': village[0].id == selectedVillage }">
          <span>
            <!-- <i style="color: #3b32b3" class="fas fa-check"></i> -->
            {{ village[0].name }}
          </span>
          <span>({{ village.length }})</span>
        </li>
        <li class="flex justify-between cursor-pointer hover:bg-slate-100 px-4">
          <span>
            <!-- <i style="color: #3b32b3" class="fas fa-check"></i> -->
            Online prenosy
          </span>
          <span>(N)</span>
        </li>
      </ul>
    </div>
  </section>
</template>
