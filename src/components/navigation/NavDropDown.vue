<script lang="ts">
import { ref, onMounted, computed } from "vue";
import useUser from "../../store/user";
import { useRouter } from "vue-router";
import useClickAway from "../utils/ClickAway";

export default {
  setup() {
    const { user, state, fetchToken, fetchUser, logout } = useUser();
    const router = useRouter();
    const currentRouteName = computed(() => {
      return router.currentRoute.value.name;
    });

    const open = ref(false);
    const outdiv = ref(null);

    function toggleHandle() {

      if (!user.value) {
        router.push("/login");
      }
      open.value = !open.value;
    }

    useClickAway(outdiv, () => {
      open.value = false;
    })


    onMounted(() => {
      // fetchToken();
      // if( JSON.parse(localStorage.getItem('YourItem'))) {
        fetchUser();
      // }
    });

    const clickLogout = () => {
      logout();
      router.push("/");
    };

    return { user, state, toggleHandle, outdiv, open, currentRouteName, clickLogout };
  }
};
</script>

<template>
  <!-- @click="toggleClick" -->
  <!-- <router-link :to="{ name: 'login.index' }"> -->
  <div ref="outdiv">

    <button @click="toggleHandle"
      class="relative text-gray-100 hover:bg-gray-50 border-b border-gray-100 md:hover:bg-transparent md:border-0 pl-3 pr-4 py-2 md:hover:text-gray-300 md:p-0 font-medium flex items-center justify-between w-full md:w-auto">
      {{ user ? user.full_name : "Prihlásiť" }}
      <svg v-if="user" class="w-4 h-4 ml-1" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
        <path fill-rule="evenodd"
          d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
          clip-rule="evenodd"></path>
      </svg>
    </button>

    <!-- </router-link> -->
    <div class="bg-white text-base z-10 list-none divide-y divide-gray-100 rounded shadow my-4 w-44 absolute" v-if="open">
      <ul class="py-1" aria-labelledby="dropdownLargeButton" v-if="user">
        <li v-if="currentRouteName !== 'public.index'">
          <router-link to="/" class="text-sm hover:bg-gray-100 text-gray-700 block px-4 py-2">Public</router-link>
        </li>
        <li v-if="currentRouteName !== 'dashboard.index'">
          <router-link to="/dashboard" class="text-sm hover:bg-gray-100 text-gray-700 block px-4 py-2">User</router-link>
        </li>
        <li v-if="currentRouteName !== 'admin.index'">
          <router-link to="/admin/home"
            class="text-sm hover:bg-gray-100 text-gray-700 block px-4 py-2">Admin</router-link>
        </li>
        <li class="py-1">
          <a href="#" class="text-sm hover:bg-gray-100 text-gray-700 block px-4 py-2" @click="clickLogout">Odhlásiť</a>
        </li>
      </ul>
    </div>
  </div>
</template>
