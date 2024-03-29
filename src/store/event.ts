import { type Event } from './../types/event';
import axios from "axios";
import { reactive, readonly, computed } from "vue";

import useUser from "./user"

const defaultState = {
  loading: false,
  events: [] as Event[],
  event: {} as Event,
  villages: [],
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

  fetchEvents: async (url :string, query : string = '') => {
    console.log(url + query)
    state.loading = true;
    let response = await axios.get(url + query);
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
    // V prípade že event nie je v načítanom zozname, napr cez odkaz z vonku.
    if(!event) {
     event = actions.fetchEvent(id);
    }
    state.event = event;
  },

  fetchEventsVillages: async () => {
    let response = await axios.get("/event/village");
    state.villages = response.data.data;
  },

  resetEvent: () => {
    state.event = {} as Event;
  },
};

export default () => ({
  state: readonly(state),
  ...getters,
  ...actions,
});
