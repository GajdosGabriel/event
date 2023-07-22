<script lang="ts">
import { ref } from "vue";
import useClickAway from "../utils/ClickAway";

export default {
  setup() {
    const open = ref(false);
    const outdiv = ref(null);

    function toggleHandle() {
      open.value = !open.value;
    }

    useClickAway(outdiv, () => {
      open.value = false;
    })

    return { open, toggleHandle, outdiv };
  },
};
</script>

<template>
  <div ref="outdiv">
    <button @click="toggleHandle">
      <svg xmlns="http://www.w3.org/2000/svg"
        class="h-7 w-7 cursor-pointer bg-gray-100 rounded-full text-gray-400 hover:bg-gray-200 p-1" fill="none"
        viewBox="0 0 24 24" stroke="currentColor" title="Spravovať článok">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
      </svg>
    </button>

    <!-- </router-link> -->
    <div class="bg-white text-base z-10 list-none divide-y divide-gray-100 rounded shadow w-44 absolute" v-if="open">
      <ul class="py-1" aria-labelledby="dropdownLargeButton">
        <li>
          <router-link to="/" class="text-sm hover:bg-gray-100 text-gray-700 block px-4 py-2">Upraviť</router-link>
        </li>
        <li>
          <router-link to="/" class="text-sm hover:bg-gray-100 text-gray-700 block px-4 py-2">Zmazať</router-link>
        </li>
      </ul>
      <!-- <div class="py-1">
      <a href="#" class="text-sm hover:bg-gray-100 text-gray-700 block px-4 py-2" @click="clickLogout">Odhlásiť</a>
    </div> -->
    </div>
  </div>
</template>
