import axios from "axios";
import { reactive, readonly, computed } from "vue";


const defaultState = {
  events: [],
  event: {},
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
  getEvent: (id) => {
    axios.get("http://eventapi.local/api/events/" + id ).then(function (response) {
      // console.log(response.data)
      state.event = response.data;
    });
  },
};

export default () => ({
  state: readonly(state),
  ...getters,
  ...actions,
});
