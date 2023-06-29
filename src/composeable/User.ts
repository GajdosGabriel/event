import axios from "axios";
import { reactive, readonly, computed } from "vue";
import type { UserForm } from "../types/user";


const defaultState = {
  loading: false,
  user: null,
  isLoggedIn: false,
  token: localStorage.getItem('token')
};

const state = reactive(defaultState);

const getters = {
  getUser: computed(() => state.user),
  getLoading: computed(() => state.loading),
};

const actions = {
  fetchUser: async () => {
    try {
      await axios.get("/api/user")
        .then(
          response => {
            state.user = response.data.data;
            if (response.data.data) {
              // actions.updateIsLoggedIn(true);
            }
          }
        )


    } catch (e) { }
  },

  login: async (form: UserForm) => {
    state.loading = true;
    axios.post("/login", form)
      .then(
        response => {
          // localStorage.setItem('token', response.data);
          state.token = response.data
          actions.fetchUser();
          state.loading = false;
        }
      )
  },

  register: async (form: UserForm) => {
    state.loading = true;
    axios.post("/register", form)
      .then(
        response => {
          // localStorage.setItem('token', response.data);
          state.token = response.data
          actions.fetchUser();
          state.loading = false;
        }
      )
  },

  // getUser: async () => {
  //   let response = await axios.get("http://eventapi.local/api/user");
  //   state.user = response.data;
  // },

  updateIsLoggedIn: (isLoggedIn) => {
    state.isLoggedIn = isLoggedIn;
  }
};

export default () => ({
  state: readonly(state),
  ...getters,
  ...actions,
});
