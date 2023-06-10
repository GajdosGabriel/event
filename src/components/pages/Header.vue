<script lang="ts">
import { defineComponent, ref } from "vue";
import { useRoute } from "vue-router";

export default defineComponent({
  setup() {
    const queryClass1 = ref("");
    const queryClass2 = ref("");
    const queryClass3 = ref("");

    if (useRoute().query.params == "likes") {
      queryClass1.value = "bg-gray-100 rounded-full";
    }

    if (useRoute().query.params == "visited") {
      queryClass2.value = "bg-gray-100 rounded-full";
    }

    if (useRoute().query.params == "newest") {
      queryClass3.value = "bg-gray-100 rounded-full";
    }

    return { queryClass1, queryClass2, queryClass3 };
  },
});
</script>

<template>
  <div class="flex justify-between p-2 rounded-t-md bg-blue-200 border-gray-300 border-2 shadow-md items-center">
    <h1 class="font-semibold text-2xl">Home</h1>

    <div class="hidden sm:block">
      <div class="flex space-x-1">
        <a href="?params=likes">
          <div class="flex hover:bg-gray-100 p-2 rounded-full h-9 w-9 items-center justify-center" :class="queryClass1">
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

        <a href="?params=visited">
          <div class="flex hover:bg-gray-100 p-2 rounded-full h-9 w-9 items-center justify-center" :class="queryClass2">
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
                d="M7.217 10.907a2.25 2.25 0 100 2.186m0-2.186c.18.324.283.696.283 1.093s-.103.77-.283 1.093m0-2.186l9.566-5.314m-9.566 7.5l9.566 5.314m0 0a2.25 2.25 0 103.935 2.186 2.25 2.25 0 00-3.935-2.186zm0-12.814a2.25 2.25 0 103.933-2.185 2.25 2.25 0 00-3.933 2.185z"
              />
            </svg>
          </div>
        </a>

        <a href="?params=newest">
          <div class="flex hover:bg-gray-100 p-2 rounded-full h-9 w-9 items-center justify-center" :class="queryClass3">
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
                d="M4.5 12.75l7.5-7.5 7.5 7.5m-15 6l7.5-7.5 7.5 7.5"
              />
            </svg>
          </div>
        </a>
      </div>
    </div>
  </div>
</template>
