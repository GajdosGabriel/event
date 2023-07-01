<template>
    <div class="container mx-auto md:p-8 h-screen">
        <div v-if="closeCard" class="mx-auto max-w-sm">
            <div class="py-10 text-center"></div>

            <div class="bg-white rounded shadow border-gray-300 border-2">
                <div class="border-b py-8 font-bold text-black text-center text-xl tracking-widest uppercase">
                    Vstúpte!
                </div>

                <div class="bg-grey-lightest px-10 py-10">
                    <div class="mb-6 cursor-pointer" @click="linkPage('login')">
                        <h5>
                            <i class="fas fa-check"></i> Už som registrovaný
                        </h5>
                    </div>
                    <div class="mb-6">
                        <a href="/auth/facebook" title="Prihlásenie cez Facebook">
                            <h5>
                                <i class="fas fa-check"></i> Pokračovať pomocou
                                Facebooku
                            </h5>
                        </a>
                    </div>
                    <div class="cursor-pointer" @click="linkPage('register')">
                        <h5>
                            <i class="fas fa-check"></i> Vytvoriť novú
                            registráciu
                        </h5>
                    </div>
                </div>
            </div>
        </div>

        <LoginRegister v-if="showRegisterForm" />
        <LoginForm v-if="showLoginForm" />
    </div>
</template>
<script>
import LoginForm from "./LoginForm.vue";
import LoginRegister from "./RegisterForm.vue";

export default {
    components: { LoginRegister, LoginForm },
    data: function () {
        return {
            showRegisterForm: false,
            showLoginForm: false,
            closeCard: true,
            errors: []
        };
    },
    methods: {
        close: function () {
            this.$emit("closeModal", false);
        },

        linkPage(xxx) {
            if (xxx == "login") {
                this.closeCard = false;
                this.showRegisterForm = false;
                this.showLoginForm = true;
            }

            if (xxx == "register") {
                this.closeCard = false;
                this.showRegisterForm = true;
                this.showLoginForm = false;
            }
        }
    },
    created() {
        // axios.get("/sanctum/csrf-cookie").then(response => {
        //     // Login...
        // });
    }
};
</script>
