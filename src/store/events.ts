import axios from "axios";
import { reactive, readonly, computed } from "vue";
import type {Event} from '../types/event'


const defaultState = {
  events: [],
  event: {},
  url: "/api/events",
  meta: {},
  links: {},
};

const state = reactive(defaultState);

const getters = {
  fetchEvents: computed(() => state.events),
};

const actions = {
  fetchEvents: async () => {
    let response = await axios.get(state.url);
    state.events = response.data.data;
    state.meta = response.data.meta;
    state.links = response.data.links;
  },
  fetchEvent: async (id: string | string[]) => {
    let response = await  axios.get("/api/events/" + id);
    state.event = response.data.data as Event;
  },

  resetEvent: () => {
    state.event = {};
  },

  paginationUrl: (url: string) => {
    state.url = url;
  },
};

export default () => ({
  state: readonly(state),
  ...getters,
  ...actions,
});