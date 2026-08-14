<template>
  <!-- Flex, nie pevná mriežka riadkov: pásy s oznamami sa nevykreslia, keď
       žiadny aktívny oznam nie je, a počet riadkov by tak nesedel. -->
  <div class="flex min-h-screen flex-col bg-slate-100">
    <AnnouncementBar placement="top" />

    <header class="flex items-center justify-between bg-slate-900 px-5 py-3 text-white">
      <RouterLink to="/" class="font-bold text-white no-underline">Event</RouterLink>
      <nav class="flex items-center gap-3">
        <LangSwitcher />
        <template v-if="auth.isAuthenticated">
          <UserDropdown />
        </template>
        <template v-else>
          <RouterLink to="/login" class="text-sm text-slate-300 no-underline hover:text-white">
            {{ t('nav.login') }}
          </RouterLink>
          <RouterLink
            to="/register"
            class="rounded-lg bg-blue-600 px-3 py-1.5 text-sm text-white no-underline hover:bg-blue-500"
          >
            {{ t('nav.register') }}
          </RouterLink>
        </template>
      </nav>
    </header>

    <main class="mx-auto w-full max-w-[1300px] flex-1">
      <RouterView />
    </main>

    <AnnouncementBar placement="bottom" />

    <!-- Odkazy na právne dokumenty musia byť dostupné z každej stránky, nielen
         z registrácie — spotrebiteľ si ich má vedieť nájsť kedykoľvek. -->
    <footer class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 bg-white px-5 py-4 text-sm text-slate-600">
      <span>© {{ new Date().getFullYear() }} Event</span>
      <nav class="flex flex-wrap gap-4">
        <RouterLink to="/obchodne-podmienky" class="text-slate-600 hover:text-slate-900">{{ t('legal.terms') }}</RouterLink>
        <RouterLink to="/ochrana-osobnych-udajov" class="text-slate-600 hover:text-slate-900">{{ t('legal.privacy') }}</RouterLink>
      </nav>
    </footer>
  </div>
</template>

<script setup lang="ts">
import { onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import UserDropdown from '@/components/UserDropdown.vue'
import AnnouncementBar from '@/components/AnnouncementBar.vue'
import LangSwitcher from '@/components/LangSwitcher.vue'
import { useI18n } from '@/i18n'

const auth = useAuthStore()
const { t } = useI18n()

onMounted(() => {
  if (auth.isAuthenticated && !auth.identity) {
    auth.fetchIdentity()
  }
})
</script>
