<template>
  <!-- Flex, nie pevná mriežka riadkov: pásy s oznamami sa nevykreslia, keď
       žiadny aktívny oznam nie je, a počet riadkov by tak nesedel. -->
  <div class="flex min-h-screen flex-col bg-slate-100">
    <AnnouncementBar placement="top" />

    <!-- Hlavná navigácia. Na širokej obrazovke stojí celá v riadku, na telefóne
         sa odkazy schovajú pod tlačidlo menu — inak by sa buď zalomili do
         druhého riadku, alebo by sa na ne nezmestilo nič okrem loga. -->
    <header class="relative z-40 bg-slate-900 text-white">
      <div class="flex items-center justify-between gap-3 px-4 py-3 sm:px-5">
        <div class="flex min-w-0 items-center gap-6">
          <RouterLink to="/" class="font-bold text-white no-underline">Event</RouterLink>
          <nav class="hidden items-center gap-5 md:flex">
            <RouterLink
              v-for="link in mainLinks"
              :key="link.to"
              :to="link.to"
              class="nav-top-link inline-flex items-center gap-1.5"
              :class="{ active: isCurrent(link.to) }"
            >
              <AppIcon :name="link.icon" class="h-4 w-4 shrink-0" />
              {{ link.label }}
            </RouterLink>
          </nav>
        </div>

        <nav class="flex items-center gap-2 sm:gap-3">
          <LangSwitcher />
          <template v-if="auth.isAuthenticated">
            <UserDropdown />
          </template>
          <template v-else>
            <RouterLink to="/login" class="hidden text-sm text-slate-300 no-underline hover:text-white sm:inline">
              {{ t('nav.login') }}
            </RouterLink>
            <RouterLink
              to="/register"
              class="hidden rounded-lg bg-blue-600 px-3 py-1.5 text-sm text-white no-underline hover:bg-blue-500 sm:inline-block"
            >
              {{ t('nav.register') }}
            </RouterLink>
          </template>

          <button
            type="button"
            class="-mr-1 rounded-lg p-2 text-slate-300 hover:bg-white/10 hover:text-white md:hidden"
            :aria-label="t('nav.menu')"
            :aria-expanded="menuOpen"
            aria-controls="public-mobile-nav"
            @click="menuOpen = !menuOpen"
          >
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
              <path v-if="menuOpen" d="M6 6l12 12M18 6L6 18" />
              <template v-else><path d="M4 7h16" /><path d="M4 12h16" /><path d="M4 17h16" /></template>
            </svg>
          </button>
        </nav>
      </div>

      <!-- Rozbalené menu prekrýva obsah namiesto toho, aby ho odtláčalo —
           hlavička tak nemení výšku a stránka pod ňou nepodskočí. -->
      <nav
        v-if="menuOpen"
        id="public-mobile-nav"
        class="absolute inset-x-0 top-full border-t border-white/10 bg-slate-900 px-4 pb-4 pt-2 shadow-lg md:hidden"
      >
        <RouterLink
          v-for="link in mainLinks"
          :key="link.to"
          :to="link.to"
          class="nav-top-link flex items-center gap-2 rounded-lg px-2 py-2.5 hover:bg-white/10"
          :class="{ active: isCurrent(link.to) }"
        >
          <AppIcon :name="link.icon" class="h-4 w-4 shrink-0" />
          {{ link.label }}
        </RouterLink>

        <div v-if="!auth.isAuthenticated" class="mt-2 grid gap-2 border-t border-white/10 pt-3 sm:hidden">
          <RouterLink to="/login" class="rounded-lg px-2 py-2.5 text-sm text-slate-200 no-underline hover:bg-white/10 hover:text-white">
            {{ t('nav.login') }}
          </RouterLink>
          <RouterLink to="/register" class="rounded-lg bg-blue-600 px-3 py-2.5 text-center text-sm text-white no-underline hover:bg-blue-500">
            {{ t('nav.register') }}
          </RouterLink>
        </div>
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
        <!-- Archív. Skončené podujatia zmiznú z výpisov, ale ich stránky žijú
             ďalej kvôli odkazom z vyhľadávača a zo zdieľaní — pätička je to
             miesto, odkiaľ na ne vedie odkaz z celého portálu. -->
        <RouterLink :to="publicArchivePath()" class="text-slate-600 hover:text-slate-900">{{ t('public.seo.archiveHeading') }}</RouterLink>
        <RouterLink to="/obchodne-podmienky" class="text-slate-600 hover:text-slate-900">{{ t('legal.terms') }}</RouterLink>
        <RouterLink to="/ochrana-osobnych-udajov" class="text-slate-600 hover:text-slate-900">{{ t('legal.privacy') }}</RouterLink>
      </nav>
    </footer>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import UserDropdown from '@/components/UserDropdown.vue'
import AnnouncementBar from '@/components/AnnouncementBar.vue'
import LangSwitcher from '@/components/LangSwitcher.vue'
import AppIcon, { type IconName } from '@/components/AppIcon.vue'
import { publicArchivePath } from '@/utils/publicUrl'
import { useI18n } from '@/i18n'

const auth = useAuthStore()
const route = useRoute()
const { t } = useI18n()

// Computed, nie konštanta — popisky sa musia prekresliť pri prepnutí jazyka.
//
// „Podujatia" tu zámerne nie sú: úvodná stránka je zoznam podujatí, takže
// odkaz vedľa loga viedol takmer tam, odkiaľ človek klikol. V navigácii má
// zmysel len to, čo sa z úvodnej stránky inak nedá dosiahnuť.
// Ikony sa neberú z lokálneho `<svg>`, ale z registra v AppIcon — tá istá
// ikona tak vyzerá v navigácii rovnako ako všade inde v aplikácii.
const mainLinks = computed<Array<{ to: string; label: string; icon: IconName }>>(() => [
  { to: '/podujatia/tento-vikend', label: t('nav.weekend'), icon: 'calendar' },
  { to: '/nahrat-plagat', label: t('nav.uploadPoster'), icon: 'upload' },
])

/**
 * Ktorý odkaz je práve otvorený. Porovnáva sa aj začiatok cesty, aby zostal
 * zvýraznený aj na podstránke danej sekcie.
 *
 * Samotné zvýraznenie je v `.nav-top-link.active` (styles.css) — rovnako ako
 * `.aside-link.active` v dashboarde a `.nav-tab.active` na kartách. V celom
 * projekte platí jedno pravidlo: aktívna položka navigácie nesie triedu
 * `active` a ako vyzerá, rieši CSS na jednom mieste.
 */
function isCurrent(to: string): boolean {
  return route.path === to || route.path.startsWith(`${to}/`)
}

const menuOpen = ref(false)

// Zavrieť po prechode: klik na odkaz v menu inak nechá panel otvorený nad
// novou stránkou.
watch(() => route.fullPath, () => { menuOpen.value = false })

onMounted(() => {
  if (auth.isAuthenticated && !auth.identity) {
    auth.fetchIdentity()
  }
})
</script>
