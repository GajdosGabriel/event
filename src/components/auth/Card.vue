<template>
    <div class="container mx-auto md:p-8 h-screen">
        <div v-if="showComponent == 'card'" class="mx-auto max-w-sm">
            <div class="py-10 text-center"></div>

            <div class="bg-white rounded shadow border-gray-300 border-2">
                <div class="border-b py-8 font-bold text-black text-center text-xl tracking-widest uppercase">
                    Vstúpte!
                </div>

                <div class="bg-grey-lightest px-10 py-10">
                    <div class="mb-6 cursor-pointer" @click="activeComponent('loginForm')">
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
                    <div class="cursor-pointer" @click="activeComponent('registerForm')">
                        <h5>
                            <i class="fas fa-check"></i> Vytvoriť novú
                            registráciu
                        </h5>
                    </div>
                </div>
            </div>
        </div>

        <LoginRegister v-if="showComponent == 'registerForm'" @onClickBack="activeComponent('card')" />
        <LoginForm v-if="showComponent == 'loginForm'" @onClickBack="activeComponent('card')" />
    </div>
</template>
<script lang="ts">
import LoginForm from "./LoginForm.vue";
import LoginRegister from "./RegisterForm.vue";

export default {
    components: { LoginRegister, LoginForm },
    data: function () {
        return {
            showComponent: 'card',
            errors: []
        };
    },
    methods: {
        activeComponent: function (nameOfComponent: string) {
            this.showComponent = nameOfComponent;
        }
    }
};
</script>
