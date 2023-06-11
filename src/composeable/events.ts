import axios from "axios";
import { reactive, readonly, computed } from "vue";


const defaultState = {
  events: [],
};

const state = reactive(defaultState);

const getters = {
  getEvents: computed(() => state.events),
};

const actions = {
  fetch: () => {
    axios.get("http://eventapi.local/api/events").then(function (response) {
      // console.log(response.data)
      state.events = response.data.data;
    });
  },
};

export default () => ({
  state: readonly(state),
  ...getters,
  ...actions,
});
