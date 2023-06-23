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
    state.events = (await response).data.data;
    state.meta = (await response).data.meta;
    state.links = (await response).data.links;
},
  getEvent: (id:number) => {
    axios.get(state.url + id).then(function (response) {
      // console.log(response.data)
      state.event = response.data;
    });
  },

  paginationhUrl: (url:string) => {
    state.url = url;
},
};

export default () => ({
  state: readonly(state),
  ...getters,
  ...actions,
});
