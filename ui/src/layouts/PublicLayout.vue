<template>
  <div class="grid min-h-screen grid-rows-[auto_1fr_auto] bg-slate-100">
    <header class="flex items-center justify-between bg-slate-900 px-5 py-4 text-white">
      <RouterLink to="/" class="font-bold text-white no-underline">Event</RouterLink>
      <nav class="flex gap-4">
        <template v-if="auth.isAuthenticated">
          <RouterLink to="/dashboard" class="text-slate-300 no-underline hover:text-white">Dashboard</RouterLink>
          <button class="text-slate-300 hover:text-white bg-transparent border-0 cursor-pointer" @click="handleLogout">Odhlásiť</button>
        </template>
        <template v-else>
          <RouterLink to="/login" class="text-slate-300 no-underline hover:text-white">Prihlásenie</RouterLink>
          <RouterLink to="/register" class="text-slate-300 no-underline hover:text-white">Registrácia</RouterLink>
        </template>
      </nav>
    </header>

    <main class="mx-auto w-full max-w-[1300px]">
      <RouterView />
    </main>

    <footer class="border-t border-slate-200 bg-white px-5 py-4 text-slate-600">
      © {{ new Date().getFullYear() }} Event
    </footer>
  </div>
</template>

<script setup lang="ts">
import { useAuthStore } from '@/stores/auth'
import { useRouter } from 'vue-router'

const auth = useAuthStore()
const router = useRouter()

async function handleLogout() {
  await auth.logout()
  router.push({ name: 'home' })
}
</script>
