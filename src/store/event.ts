import axios from "axios";
import { reactive, readonly, computed } from "vue";
import type { Event } from "../types/event";

const defaultState = {
  loading: false,
  events: [],
  event: {} as Event,
  villages: [],
  url: "/api/events",
  meta: {},
  links: {},
};

const state = reactive(defaultState);

const getters = {
  events: computed(() => state.events),
  event: computed(() => state.event),
  villages: computed(() => state.villages),
  loading: computed(() => state.loading),
  meta: computed(() => state.meta),
  links: computed(() => state.links),
};

const actions = {
  fetchEvents: async () => {
    state.loading = true;
    let response = await axios.get(state.url);
    state.events = response.data.data;
    state.meta = response.data.meta;
    state.links = response.data.links;
    state.loading = false;
  },
  fetchEvent: async (id: string | string[]) => {
    state.loading = true;
    let response = await axios.get("/api/events/" + id);
    state.event = response.data.data as Event;

    state.loading = false;
  },

  findEvent: (id: number) => {
    let event = state.events.find( e => e.id == id);
    state.event = event;
  },

  fetchEventsVillages: async () => {
    let response = await axios.get("/api/event/village");
    state.villages = response.data.data;
  },

  resetEvent: () => {
    state.event = {};
  },

  paginationUrl: (url: string) => {
    state.url = url;
  },

  setUrl: (url: string) => {
    state.url = url;
  },
};

export default () => ({
  state: readonly(state),
  ...getters,
  ...actions,
});
