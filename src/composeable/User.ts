import axios from "axios";
import { reactive, readonly, computed } from "vue";
import type { UserForm } from "../types/user";


const defaultState = {
  user: {},
  isLoggedIn: false,
  token: localStorage.getItem('token')
};

const state = reactive(defaultState);

const getters = {
  getUser: computed(() => state.user),
};

const actions = {
  fetchUser: async () => {
    try {
      await axios.get("http://eventapi.local/api/user")
        .then(
          response => {
            state.user = response.data.data;
            if (response.data.data) {
              actions.updateIsLoggedIn(true);
            }
          }
        )


    } catch (e) { }
  },

  login: async (form: UserForm) => {
    console.log(form);
    axios.post("http://eventapi.local/api/login", form)
      .then(
        response => {
          localStorage.setItem('token', response.data);
          state.token = response.data
          actions.fetchUser();
        }
      )
  },

  getUser: async () => {
    let response = await axios.get("http://eventapi.local/api/user/");
    state.user = response.data.data;
  },

  updateIsLoggedIn: (isLoggedIn) => {
    state.isLoggedIn = isLoggedIn;
  }
};

export default () => ({
  state: readonly(state),
  ...getters,
  ...actions,
});
