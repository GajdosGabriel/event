<template>
  <div class="container mx-auto md:p-8">
    <div class="mx-auto max-w-sm">
      <div class="bg-white rounded shadow border-gray-300 border-2">
        <div class="border-b py-8 font-bold text-black text-center text-xl tracking-widest uppercase">
          Vitajte späť!
        </div>

        <form @submit.prevent="attemptLogin" class="bg-grey-lightest px-10 py-10">
          <div class="mb-3">
            <input
              v-model="form.email"
              type="email"
              class="border-2 border-gray-300 w-full p-3"
              name="email"
              placeholder="E-Mail"
              required
              autofocus
            />
            <div style="color: red" v-if="errors.email">{{ errors.email[0] }}</div>
          </div>
          <div class="mb-6">
            <input
              v-model="form.password"
              :type="inputType ? 'text' : 'password'"
              class="border-2 border-gray-300 w-full p-3"
              name="password"
              placeholder="Heslo ..."
              required
            />
            <div style="color: red" v-if="errors.password" v-text="errors.password"></div>
            <a href="#" @click.prevent="togglePassword" style="font-size: 80%; margin-top: -1rem">
              {{ inputType ? "Skryť" : "Zobraziť" }} heslo</a
            >
          </div>

          <div class="flex">
            <button
              type="submit"
              class="hover:bg-gray-200 w-full p-4 text-sm uppercase font-bold tracking-wider border-2 border-gray-300"
            >
              Vstúpiť
            </button>
          </div>
        </form>

        <div class="border-t px-10 py-6">
          <div class="flex justify-between">
            <div class="font-bold text-primary hover:text-primary-dark no-underline cursor-pointer" @click="$emit('onClickBack')">
              Späť
            </div>
            <router-link class="font-bold text-primary hover:text-primary-dark no-underline" to="/password/reset">
              Zabudnuté heslo?
            </router-link>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script lang="ts">
import { useRouter } from "vue-router";
import { reactive, watch } from "vue";
import useUser from "../../store/user";

export default {
  emits: ['onClickBack'],
  setup() {

    const router = useRouter();

    const { state, user, login, errors } = useUser();

    const form = reactive({
      rememberMe: true,
      device_name: "web",
    });

    const attemptLogin = () => {
      login(form);
    };

    watch(user, () => {
      router.push("/");
    });

    return { state, form, attemptLogin, errors };
  },
  data: function () {
    return {
      rememberMe: true,
      errors: {},
      inputType: false,
    };
  },

  computed: {
    isValidForm: function () {
      return this.emailIsValid();
    },

    isValidPassword: function () {
      if (this.password.length < 6) {
        return false;
      }
      return this.emailIsValid() && this.password;
    },
  },

  methods: {
    togglePassword: function () {
      this.inputType = !this.inputType;
    },
    emailIsValid: function (mail) {
      if (/^\w+([\.-]?\w+)*@\w+([\.-]?\w+)*(\.\w{2,3})+$/.test(this.email)) {
        return true;
      } else {
        return false;
      }
    }
  },
};
</script>

<style scoped>
.box {
  margin: 2rem;
}

.card-body {
  display: flex;
}

.inline {
  display: inline-flex;
}

.form-group {
  padding: 1rem 0rem;
}

label {
  background: #898989;
  padding: 0 0.5rem;
  color: whitesmoke;
}

.slide-fade-enter-active {
  transition: all 0.3s ease;
}

.slide-fade-leave-active {
  transition: all 0.8s cubic-bezier(1, 0.5, 0.8, 1);
}

.slide-fade-enter,
.slide-fade-leave-to

/* .slide-fade-leave-active below version 2.1.8 */ {
  transform: translateX(10px);
  opacity: 0;
}
</style>
