import axios from "axios";
import { reactive, readonly, computed } from "vue";


const defaultState = {
  events: [],
  event: {},
  url: "http://eventapi.local/api/events",
  meta: {},
  links: {},
};

const state = reactive(defaultState);

const getters = {
  getEvents: computed(() => state.events),
};

const actions = {
  getEvents: async () => {
    let response = await axios.get(state.url);
    state.events = response.data.data;
    state.meta = response.data.meta;
    state.links = response.data.links;
  },
  getEvent: async (id: number) => {
    let response = await  axios.get(state.url + '/' + id);
    state.event = response.data.data;
  },

  paginationhUrl: (url: string) => {
    state.url = url;
  },
};

export default () => ({
  state: readonly(state),
  ...getters,
  ...actions,
});
