<template>
  <div class="container mx-auto md:p-8">
    <div class="mx-auto max-w-sm">
      <div class="bg-white rounded shadow border-gray-300 border-2">
        <div class="border-b py-8 font-bold text-black text-center text-xl tracking-widest uppercase">
          Nová registrácia
        </div>

        <form @submit.prevent="handleRegister" class="bg-grey-lightest px-10 py-10">


          <div class="mb-3">
            <InputField v-model="form.first_name" :current-value="form.first_name" model="form.first_name"
              placeholder="Meno" label="Meno" />
            <div style="color: red" v-if="errors.first_name">{{ errors.first_name[0] }}</div>
          </div>


          <div class="mb-3">
            <InputField v-model="form.last_name" :current-value="form.last_name" model="form.last_name"
              placeholder="Priezvisko" label="Priezvisko" />
            <div style="color: red" v-if="errors.last_name">{{ errors.last_name[0] }}</div>
          </div>

          <div class="mb-3">
            <InputField v-model="form.email" :current-value="form.email" model="form.email" input-type="email"
              placeholder="Email" label="Email" />
            <div style="color: red" v-if="errors.email">{{ errors.email[0] }}</div>
          </div>
          <div class="mb-6">
            <InputField v-model="form.password" :current-value="form.password" model="form.password"
              :input-type="changeType ? 'text' : 'password'" placeholder="Heslo min 8 znakov" label="Heslo" />
            <span v-if="form.password" @click.prevent="togglePassword" class="cursor-pointer"
              style="font-size: 80%; margin-top: -1rem">
              {{ changeType ? "Skryť" : "Zobraziť" }} heslo</span>
            <div style="color: red" v-if="errors.password">{{ errors.password[0] }}</div>
          </div>

          <div class="mb-6">
            <InputField v-model="form.password_confirmation" :current-value="form.password_confirmation"
              :input-type="changeType ? 'text' : 'password'" model="form.password_confirmation"
              placeholder="Zopakovať heslo" label="Potvrdiť heslo" />
            <span v-if="form.password" @click.prevent="togglePassword" class="cursor-pointer"
              style="font-size: 80%; margin-top: -1rem">
              {{ changeType ? "Skryť" : "Zobraziť" }} heslo</span>
            <div style="color: red" v-if="errors.password_confirmation">{{ errors.password_confirmation[0] }}</div>
          </div>

          <div class="flex">
            <button type="submit"
              class="hover:bg-primary-dark hover:bg-gray-100 border-2 mt-5 rounded-sm w-full p-4 text-sm uppercase font-bold tracking-wider border-gray-300">
              Registrovať sa
            </button>
          </div>
        </form>

        <div class="border-t px-10 py-6">
          <div class="flex justify-between">
            <router-link class="font-bold text-primary hover:text-primary-dark no-underline" to="/login">
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

<script lang="ts">
import useUser from "../../store/user";
import InputField from "../input/InputField.vue";
import { reactive } from "vue";
import { useRouter } from "vue-router";
import { type UserForm } from "../../types/user"

export default {
  components: { InputField },
  setup() {
    const { makeRegistration, loading, errors } = useUser();
    const router = useRouter();
    const form = reactive<UserForm>({
      rememberMe: true,
      first_name: '',
      last_name: '',
      email: '',
      password: '',
    });

    const handleRegister = async () => {
      makeRegistration(form);
      await router.push("/");
    };

    return { form, handleRegister, errors };
  },

  data: function () {
    return {
      loading: false,
      changeType: false,
    };
  },

  computed: {
    isValidForm: function () {
      return this.emailIsValid();
    },

    isValidPassword: function (): boolean {
      if (this.form.password.length < 6) {
        return false;
      }
      return this.emailIsValid();
    },
  },

  methods: {
    togglePassword: function () {
      this.changeType = !this.changeType;
    },
    emailIsValid: function (): boolean {
      if (/^\w+([\.-]?\w+)*@\w+([\.-]?\w+)*(\.\w{2,3})+$/.test(this.form.email)) {
        return true;
      } else {
        // alert("Zadali ste neplatnú emailú adresu!");
        return false;
      }
    },
  },
};
</script>


