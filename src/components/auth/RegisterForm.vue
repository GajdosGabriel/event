<template>
  <div class="container mx-auto md:p-8">
    <div class="mx-auto max-w-sm">
      <div class="bg-white rounded shadow border-gray-300 border-2">
        <div class="border-b py-8 font-bold text-black text-center text-xl tracking-widest uppercase">
          Nová registrácia
        </div>

        <form @submit.prevent="handleRegister" class="bg-grey-lightest px-10 py-10">
          <div class="mb-3">
            <input
              v-model="form.first_name"
              type="text"
              class="border-2 border-gray-300 w-full p-3"
              name="first_name"
              placeholder="Meno"
              required
              autofocus
            />
            <div style="color: red" v-text="getErrors.first_name"></div>
          </div>
          <div class="mb-3">
            <input
              v-model="form.last_name"
              type="text"
              class="border-2 border-gray-300 w-full p-3"
              name="last_name"
              placeholder="Priezvisko"
              required
            />
            <div style="color: red" v-text="getErrors.last_name"></div>
          </div>

          <div class="mb-3">
            <input
              v-model="form.email"
              type="email"
              class="border-2 border-gray-300 w-full p-3"
              name="email"
              placeholder="E-Mail"
              required
            />
            <div style="color: red" v-text="getErrors.email"></div>
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
            <div style="color: red" v-text="getErrors.password"></div>
          </div>

          <div class="mb-6">
            <input
              v-model="form.password_confirmation"
              :type="inputType ? 'text' : 'password'"
              class="border-2 border-gray-300 w-full p-3"
              placeholder="Potvrdiť heslo"
              required
            />
            <span
              v-if="form.password"
              @click.prevent="togglePassword"
              class="cursor-pointer"
              style="font-size: 80%; margin-top: -1rem"
            >
              {{ inputType ? "Skryť" : "Zobraziť" }} heslo</span
            >
            <div style="color: red" v-text="getErrors.password_confirmation"></div>
          </div>

          <div class="flex">
            <button
              type="submit"
              class="hover:bg-primary-dark hover:bg-gray-100 border-2 rounded-sm w-full p-4 text-sm uppercase font-bold tracking-wider border-gray-300"
            >
              Registrovať sa
            </button>
          </div>
        </form>

        <div class="border-t px-10 py-6">
          <div class="flex justify-between">
            <router-link class="font-bold text-primary hover:text-primary-dark no-underline" to="/prihlasenie">
              Späť
            </router-link>
            <router-link class="font-bold text-primary hover:text-primary-dark no-underline" to="/facebook">
              Registrácia pomocou Facebooku
            </router-link>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import useUser from "../../store/user";
import { reactive } from "vue";

export default {
  setup() {
    const { register, getLoading, getErrors } = useUser();
    const form = reactive({
      rememberMe: true,
    });

    const handleRegister = () => {
      register(form);
    };

    return { form, handleRegister, getErrors };
  },

  data: function () {
    return {
      loading: false,
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
        //                    alert("Zadali ste neplatnú emailú adresu!");
        return false;
      }
    },
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

.slide-fade-enter, .slide-fade-leave-to
        /* .slide-fade-leave-active below version 2.1.8 */ {
  transform: translateX(10px);
  opacity: 0;
}
</style>
