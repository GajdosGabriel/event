<script lang="ts">
import { ref, onMounted } from "vue";
import useUser from "../../composeable/user";

export default {
  setup() {
    const { getUser, fetchUser } = useUser();

    onMounted(() => {
      fetchUser();
    });

    return { getUser };
  },
  data() {
    return {
      toggle: false,
    };
  },
  mounted() {
    let self = this;
    document.addEventListener("click", (e) => {
      if (self.$refs.myref !== undefined && self.$refs.myref.contains(e.target) === false) {
        //click outside!
        self.toggle = false;
      }
    });
  },
  methods: {
    toggleClick() {
      this.toggle = !this.toggle;
    },
  },
};
</script>

<template>
   <!-- @click="toggleClick" -->
  <router-link :to="{ name: 'login.index' }">
    <button
      v-if="getUser"
     
      class="relative text-gray-100 hover:bg-gray-50 border-b border-gray-100 md:hover:bg-transparent md:border-0 pl-3 pr-4 py-2 md:hover:text-gray-300 md:p-0 font-medium flex items-center justify-between w-full md:w-auto"
    >
      {{ getUser.first_name + " " + getUser.last_name }}
      <svg class="w-4 h-4 ml-1" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
        <path
          fill-rule="evenodd"
          d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
          clip-rule="evenodd"
        ></path>
      </svg>
    </button>

    <button v-else
      class="relative text-gray-100 hover:bg-gray-50 border-b border-gray-100 md:hover:bg-transparent md:border-0 pl-3 pr-4 py-2 md:hover:text-gray-300 md:p-0 font-medium flex items-center justify-between w-full md:w-auto"
    >
      Prihlásiť
    </button>
  </router-link>
  <div
    class="bg-white text-base z-10 list-none divide-y divide-gray-100 rounded shadow my-4 w-44 absolute"
    v-if="toggle"
  >
    <ul class="py-1" aria-labelledby="dropdownLargeButton">
      <li>
        <a href="#" class="text-sm hover:bg-gray-100 text-gray-700 block px-4 py-2">Dashboard</a>
      </li>
      <li>
        <a href="#" class="text-sm hover:bg-gray-100 text-gray-700 block px-4 py-2">Settings</a>
      </li>
      <li>
        <a href="#" class="text-sm hover:bg-gray-100 text-gray-700 block px-4 py-2">Earnings</a>
      </li>
    </ul>
    <div class="py-1">
      <a href="#" class="text-sm hover:bg-gray-100 text-gray-700 block px-4 py-2">Sign out</a>
    </div>
  </div>
</template>
