import axios from "axios";
import { reactive, readonly, computed } from "vue";
import type { UserForm } from "../types/user";

const defaultState = {
  loading: false,
  user: null,
  isLoggedIn: false,
  token: null,
  errors: [],

  // token: localStorage.getItem("token"),
};

const state = reactive(defaultState);

const getters = {
  user: computed(() => state.user),
  loading: computed(() => state.loading),
  errors: computed(() => state.errors),
};

const actions = {
  fetchUser: async () => {
    try {
      await axios.get("/api/user").then((response) => {
        state.user = response.data.data;
      });
    } catch (e) { }
  },

  fetchToken: async () => {
    state.token = await axios.get("/sanctum/csrf-cookie");
  },

  login: async (form: UserForm) => {
    actions.fetchToken();
    state.errors = [];
    try {
      // actions.getToken();
      state.loading = true;
      await axios.post("/login", form).then((response) => {
        // localStorage.setItem('token', response.data);
        actions.fetchUser();
        state.loading = false;
      });
    } catch (error) {
      if (error.response.status === 422) {
        state.errors = error.response.data.errors
      }
    }
  },

  register: async (form: UserForm) => {
    state.errors = [];
    state.loading = true;
    await axios.post("/register", form).then((response) => {
      // localStorage.setItem('token', response.data);
      state.token = response.data;
      actions.fetchUser();
      state.loading = false;
    });
  },

  logout: async () => {
    try {
      await axios.post("/logout").then((response) => {
        state.user = null;
      });
    } catch (e) { }
  },

  updateIsLoggedIn: (isLoggedIn) => {
    state.isLoggedIn = isLoggedIn;
  },
};

export default () => ({
  state: readonly(state),
  ...getters,
  ...actions,
});
