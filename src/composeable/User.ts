import axios from "axios";
import { reactive, readonly, computed } from "vue";


const defaultState = {
  user: {},
};

const state = reactive(defaultState);

const getters = {
  getUser: computed(() => state.user),
};

const actions = {
  getUser: async () => {
    let response = await  axios.get("http://eventapi.local/api/user/");
    state.user = response.data.data;
  },
};

export default () => ({
  state: readonly(state),
  ...getters,
  ...actions,
});
