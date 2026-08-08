<template>
  <!-- Flex, nie pevná mriežka riadkov: pásy s oznamami sa nevykreslia, keď
       žiadny aktívny oznam nie je, a počet riadkov by tak nesedel. -->
  <div class="flex min-h-screen flex-col bg-slate-100">
    <AnnouncementBar placement="top" />

    <header class="flex items-center justify-between bg-slate-900 px-5 py-3 text-white">
      <RouterLink to="/" class="font-bold text-white no-underline">Event</RouterLink>
      <nav class="flex items-center gap-3">
        <template v-if="auth.isAuthenticated">
          <UserDropdown />
        </template>
        <template v-else>
          <RouterLink to="/login" class="text-sm text-slate-300 no-underline hover:text-white">Prihlásenie</RouterLink>
          <RouterLink
            to="/register"
            class="rounded-lg bg-blue-600 px-3 py-1.5 text-sm text-white no-underline hover:bg-blue-500"
          >
            Registrácia
          </RouterLink>
        </template>
      </nav>
    </header>

    <main class="mx-auto w-full max-w-[1300px] flex-1">
      <RouterView />
    </main>

    <AnnouncementBar placement="bottom" />

    <footer class="border-t border-slate-200 bg-white px-5 py-4 text-slate-600">
      © {{ new Date().getFullYear() }} Event
    </footer>
  </div>
</template>

<script setup lang="ts">
import { onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import UserDropdown from '@/components/UserDropdown.vue'
import AnnouncementBar from '@/components/AnnouncementBar.vue'

const auth = useAuthStore()

onMounted(() => {
  if (auth.isAuthenticated && !auth.identity) {
    auth.fetchIdentity()
  }
})
</script>
