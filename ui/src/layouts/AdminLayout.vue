<template>
  <div class="layout-shell admin-layout" :class="{ collapsed }">
    <aside class="aside" :class="{ 'more-open': moreOpen }">
      <RouterLink to="/admin" class="aside-brand">
        <BrandMark class="brand-mark" />
        <span class="brand-copy">
          <strong>tixio<span class="text-rose-400">.</span></strong>
          <small>{{ t('nav.admin') }}</small>
        </span>
      </RouterLink>

      <!-- Hore len štyri denne používané sekcie, zvyšok v rozbaľovacích
           skupinách (ako „Správa" v dashboarde) — plochý zoznam 17 položiek
           prestal byť prehľadný. Otvorené skupiny si panel pamätá; skupina
           s aktívnou stránkou sa otvorí sama. -->
      <nav class="aside-nav">
        <template v-for="section in sections" :key="section.key">
          <button
            v-if="section.label"
            type="button"
            class="group-toggle"
            :class="{ open: isOpen(section.key) }"
            :aria-expanded="isOpen(section.key)"
            @click="toggleGroup(section.key)"
          >
            <span class="group-label">{{ t(section.label) }}</span>
            <span v-if="!isOpen(section.key) && sectionActive(section)" class="group-dot" />
            <svg class="group-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path stroke-linecap="round" stroke-linejoin="round" d="M9 6l6 6-6 6"/></svg>
          </button>
          <div
            v-show="!section.label || collapsed || isOpen(section.key)"
            class="nav-links"
            :class="section.label ? 'group-links' : 'primary-links'"
          >
            <RouterLink
              v-for="item in section.items"
              :key="item.to"
              :to="item.to"
              class="aside-link"
              :class="{ active: inSection(item.to) }"
              :title="collapsed ? t(item.label) : undefined"
            >
              <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path v-for="d in item.icon" :key="d" stroke-linecap="round" stroke-linejoin="round" :d="d"/></svg>
              <span class="nav-label">{{ t(item.label) }}</span>
              <span v-if="item.badge && item.badge() > 0" class="nav-badge">{{ item.badge() > 99 ? '99+' : item.badge() }}</span>
            </RouterLink>
          </div>
        </template>
        <!-- Len na telefóne: spodná lišta drží štyri hlavné sekcie, skupiny
             rozbalí toto tlačidlo ako panel nad lištou. -->
        <button
          type="button"
          class="aside-link more-btn"
          :class="{ active: !moreOpen && groupActive }"
          :aria-expanded="moreOpen"
          @click="moreOpen = !moreOpen"
        >
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" :d="moreOpen ? 'M6 6l12 12M18 6L6 18' : 'M4 6h16M4 12h16M4 18h16'"/></svg>
          <span class="nav-label">{{ moreOpen ? t('common.close') : t('nav.menu') }}</span>
        </button>
      </nav>

      <div v-if="moreOpen" class="more-panel">
        <section v-for="section in groups" :key="section.key" class="more-section">
          <h3 class="more-heading">{{ t(section.label!) }}</h3>
          <div class="more-grid">
            <RouterLink
              v-for="item in section.items"
              :key="item.to"
              :to="item.to"
              class="more-link"
              :class="{ active: inSection(item.to) }"
            >
              <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path v-for="d in item.icon" :key="d" stroke-linecap="round" stroke-linejoin="round" :d="d"/></svg>
              <span>{{ t(item.label) }}</span>
            </RouterLink>
          </div>
        </section>
      </div>

      <button class="toggle-btn" @click="toggle" :title="collapsed ? t('nav.expand') : t('nav.collapse')">
        <svg class="toggle-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" :d="collapsed ? 'M9 5l7 7-7 7' : 'M15 19l-7-7 7-7'" />
        </svg>
        <span class="nav-label">{{ t('nav.collapse') }}</span>
      </button>
    </aside>

    <div class="content-shell">
      <header class="header">
        <RouterLink to="/admin" class="brand">{{ t('nav.admin') }}</RouterLink>
        <nav class="header-nav">
          <LangSwitcher variant="amber" />
          <NotificationBell />
          <UserDropdown variant="amber" logout-to="login" />
        </nav>
      </header>

      <main class="body">
        <div class="body-inner">
          <div class="page-content">
            <!-- A different record needs fresh page state; query filters keep the current page. -->
            <RouterView :key="route.path" />
          </div>

          <aside v-if="munResource" class="right-aside">
            <MunicipalityAside scope="admin" :resource="munResource" />
          </aside>
        </div>
      </main>

      <footer class="footer flex flex-wrap items-center justify-between gap-3 text-sm">
        <span>© {{ new Date().getFullYear() }} Tixio</span>
        <nav class="flex flex-wrap gap-4">
          <RouterLink to="/obchodne-podmienky" class="text-slate-600 hover:text-slate-900">{{ t('legal.terms') }}</RouterLink>
          <RouterLink to="/ochrana-osobnych-udajov" class="text-slate-600 hover:text-slate-900">{{ t('legal.privacy') }}</RouterLink>
        </nav>
      </footer>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import UserDropdown from '@/components/UserDropdown.vue'
import NotificationBell from '@/components/NotificationBell.vue'
import MunicipalityAside from '@/components/MunicipalityAside.vue'
import LangSwitcher from '@/components/LangSwitcher.vue'
import BrandMark from '@/components/BrandMark.vue'
import { useI18n, type MessageKey } from '@/i18n'
import { useAreaHead } from '@/composables/useAreaHead'
import { supportSummary } from '@/api/support'

const auth = useAuthStore()
const route = useRoute()
const { t } = useI18n()
useAreaHead('admin')

// Odznak Podpory: vlákna, ktoré čakajú na odpoveď. Obnovuje sa pri prechode
// medzi stránkami — rovnako ako odznak Správ v dashboarde, bez pollingu.
const supportOpen = ref(0)

async function refreshSupport() {
  try {
    supportOpen.value = (await supportSummary()).inbox ?? 0
  } catch {
    supportOpen.value = 0
  }
}

onMounted(refreshSupport)
watch(() => route.path, refreshSupport)

/** Rozbalené menu na telefóne — po výbere sekcie sa zbalí samo. */
const moreOpen = ref(false)
watch(() => route.path, () => { moreOpen.value = false })

const STORAGE_KEY = 'admin-sidebar-collapsed'
const collapsed = ref(localStorage.getItem(STORAGE_KEY) === '1')

function toggle() {
  collapsed.value = !collapsed.value
  localStorage.setItem(STORAGE_KEY, collapsed.value ? '1' : '0')
}

// Položka menu ostáva zvýraznená aj na detailoch a podstránkach sekcie
// (napr. /dashboard/events/12/edit). RouterLink si to sám neodvodí, lebo
// detailné routy sú súrodenci indexu, nie jeho potomkovia.
function inSection(prefix: string): boolean {
  return route.path === prefix || route.path.startsWith(prefix + '/')
}

interface NavItem {
  to: string
  label: MessageKey
  /** Ťahy ikony (viewBox 24×24, obrys). */
  icon: string[]
  badge?: () => number
}
interface NavSection {
  key: string
  /** Bez nadpisu = hlavné položky, vždy viditeľné. */
  label?: MessageKey
  items: NavItem[]
}

const GEAR = 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z'

const sections: NavSection[] = [
  {
    key: 'main',
    items: [
      { to: '/admin/events', label: 'nav.events', icon: ['M5 4h14a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V6a2 2 0 012-2z', 'M16 2v4M8 2v4M3 10h18'] },
      { to: '/admin/canals', label: 'nav.canals', icon: ['M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0'] },
      { to: '/admin/podpora', label: 'nav.support', icon: ['M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z'], badge: () => supportOpen.value },
      { to: '/admin/users', label: 'nav.users', icon: ['M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z'] },
    ],
  },
  {
    key: 'catalog',
    label: 'nav.groupCatalog',
    items: [
      { to: '/admin/venues', label: 'nav.venues', icon: ['M12 2C8.134 2 5 5.134 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.866-3.134-7-7-7zm0 9a2 2 0 110-4 2 2 0 010 4z'] },
      { to: '/admin/organizations', label: 'nav.organizations', icon: ['M3 21h18M5 21V7l7-4 7 4v14M9 21v-4h6v4M9 10h.01M15 10h.01M9 14h.01M15 14h.01'] },
      { to: '/admin/municipalities', label: 'nav.municipalities', icon: ['M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'] },
      { to: '/admin/vstupenky', label: 'nav.tickets', icon: ['M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z'] },
      { to: '/admin/files', label: 'nav.files', icon: ['M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z'] },
    ],
  },
  {
    // Prevzatia a návrhy štítkov viedli len z e-mailu a denníka (resp.
    // z ničoho) — admin o nich bez priamej adresy nevedel.
    key: 'moderation',
    label: 'nav.groupModeration',
    items: [
      { to: '/admin/prevzatia', label: 'nav.canalClaims', icon: ['M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z'] },
      { to: '/admin/navrhy-stitkov', label: 'nav.tagSuggestions', icon: ['M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z'] },
      { to: '/admin/otazky', label: 'nav.questions', icon: ['M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'] },
      { to: '/admin/oznamy', label: 'nav.announcements', icon: ['M3 11v2a1 1 0 001 1h2l4 4V6L6 10H4a1 1 0 00-1 1zM16 9a4 4 0 010 6M18.5 6.5a8 8 0 010 11'] },
    ],
  },
  {
    key: 'system',
    label: 'nav.groupSystem',
    items: [
      { to: '/admin/ai-spotreba', label: 'nav.aiUsage', icon: ['M12 3l1.8 4.9L19 9.7l-5.2 1.8L12 16.4l-1.8-4.9L5 9.7l5.2-1.8zM19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8z'] },
      { to: '/admin/dennik', label: 'nav.systemLog', icon: ['M6 3h9l4 4v14H6zM14 3v5h5M9 12h7M9 15.5h7M9 19h4'] },
      { to: '/admin/tools', label: 'nav.tools', icon: [GEAR, 'M14.121 14.121L19 19M10 7a3 3 0 100 6 3 3 0 000-6z'] },
      { to: '/admin/settings', label: 'nav.settings', icon: [GEAR, 'M15 12a3 3 0 11-6 0 3 3 0 016 0z'] },
    ],
  },
]
const groups = sections.filter(section => section.label)

function sectionActive(section: NavSection): boolean {
  return section.items.some(item => inSection(item.to))
}
const groupActive = computed(() => groups.some(sectionActive))

// Otvorené skupiny si panel pamätá medzi návštevami.
const GROUPS_KEY = 'admin-sidebar-groups'
const openGroups = ref<string[]>(readOpenGroups())

function readOpenGroups(): string[] {
  try {
    const parsed = JSON.parse(localStorage.getItem(GROUPS_KEY) ?? '[]')
    return Array.isArray(parsed) ? parsed.filter((key): key is string => typeof key === 'string') : []
  } catch {
    return []
  }
}

function saveOpenGroups() {
  try {
    localStorage.setItem(GROUPS_KEY, JSON.stringify(openGroups.value))
  } catch {
    // súkromné okno a pod. — stav skupín je len pohodlie
  }
}

function isOpen(key: string): boolean {
  return openGroups.value.includes(key)
}

function toggleGroup(key: string) {
  openGroups.value = isOpen(key) ? openGroups.value.filter(k => k !== key) : [...openGroups.value, key]
  saveOpenGroups()
}

// Skupina s aktuálnou stránkou sa otvorí, aby bolo vidieť, kde človek je.
watch(() => route.path, () => {
  const current = groups.find(sectionActive)
  if (current && !isOpen(current.key)) {
    openGroups.value = [...openGroups.value, current.key]
    saveOpenGroups()
  }
}, { immediate: true })

const MUN_RESOURCES = ['events', 'canals', 'venues']
const munResource = computed(() => {
  const segs = route.path.split('/').filter(Boolean)
  // only show on index pages: /admin/<resource>
  if (segs.length !== 2) return null
  return MUN_RESOURCES.includes(segs[1]) ? segs[1] : null
})
</script>

<style scoped>
@reference "tailwindcss";

.layout-shell {
  @apply grid min-h-screen bg-slate-100;
  transition: grid-template-columns 0.22s ease;
}
.content-shell {
  @apply grid min-h-screen min-w-0 grid-rows-[auto_1fr_auto];
}
.header {
  @apply relative z-30 flex items-center justify-between px-5 py-4 shadow-sm;
  background: linear-gradient(135deg, rgb(68 64 60) 0%, rgb(120 83 31) 100%);
  @apply border-b border-amber-300/35 text-amber-50;
}
.brand { @apply font-bold tracking-wide text-amber-50 no-underline; }
.header-nav { @apply ml-auto flex items-center gap-3; }

/* Aside */
.aside {
  @apply sticky top-0 z-40 flex flex-col border-r border-amber-900/40 p-3 text-white;
  overflow-x: hidden;
  background: linear-gradient(180deg, rgb(68 64 60) 0%, rgb(92 64 51) 100%);
  transition: width 0.22s ease;
}
.aside-brand {
  @apply flex shrink-0 items-center gap-3 rounded-lg px-2 py-2 text-white no-underline hover:bg-amber-200/12 overflow-hidden;
}
.brand-mark { @apply size-10 shrink-0 rounded-lg shadow-sm ring-1 ring-white/20; }
.brand-copy { @apply min-w-0 overflow-hidden transition-all duration-200; }
.brand-copy strong { @apply block truncate text-sm leading-tight text-white; }
.brand-copy small { @apply mt-0.5 block text-xs text-amber-100/70; }

.aside-nav { @apply mt-3 flex flex-col gap-0.5; }
.nav-links { @apply flex flex-col gap-0.5; }

/* Nadpis skupiny: malé verzálky so šípkou, ako sekcie v moderných admin paneloch. */
.group-toggle {
  @apply mt-3 flex w-full cursor-pointer items-center gap-2 rounded-md border-0 bg-transparent px-2.5 py-1.5 text-left text-[0.68rem] font-bold uppercase tracking-wider text-amber-100/55 hover:bg-amber-200/10 hover:text-amber-50;
}
.group-label { @apply flex-1 truncate; }
.group-dot { @apply size-1.5 shrink-0 rounded-full bg-amber-300; }
.group-chevron { @apply size-3.5 shrink-0 transition-transform duration-200; }
.group-toggle.open .group-chevron { transform: rotate(90deg); }
/* Podpoložky jemne odsadené s vodiacou linkou vľavo. */
.group-links { @apply ml-3 border-l border-amber-200/15 pl-1.5; }
.nav-icon { @apply h-5 w-5 shrink-0; }
.nav-label { @apply overflow-hidden whitespace-nowrap transition-all duration-200; }
.aside-link {
  @apply flex items-center gap-3 rounded-lg px-2.5 py-2 text-sm font-semibold text-amber-50/80 no-underline hover:bg-amber-200/12 hover:text-white overflow-hidden;
}
.aside-link.active { @apply bg-amber-300 text-amber-950; }
.nav-badge {
  @apply ml-auto shrink-0 rounded-full bg-red-500 px-1.5 py-0.5 text-[0.65rem] font-bold leading-none text-white;
}
/* V zbalenom paneli nie je miesto na číslo — ostane len bodka pri ikone. */
.collapsed .nav-badge { @apply ml-0 size-2 p-0 text-transparent; }

/* Toggle button */
.toggle-btn {
  @apply mt-auto flex items-center justify-center rounded-lg p-2 text-amber-100/60 hover:bg-amber-200/12 hover:text-white cursor-pointer border-0 bg-transparent;
}
.toggle-icon { @apply h-4 w-4 transition-transform duration-200; }

/* Collapsed state */
.collapsed .brand-copy { @apply w-0 opacity-0; }
.collapsed .nav-label { @apply w-0 opacity-0; }
.collapsed .aside-link { @apply justify-center px-2; }
.collapsed .toggle-btn { @apply justify-center; }
/* Zbalený panel: skupiny ostanú rozložené, nadpis nahradí tenký oddeľovač. */
.collapsed .group-toggle { @apply pointer-events-none mx-2 my-2 h-px w-auto p-0; background: rgb(253 230 138 / 0.18); }
.collapsed .group-toggle > * { display: none; }
.collapsed .group-links { @apply ml-0 border-l-0 pl-0; }

.more-panel { display: none; }

/* clip, nie auto: overflow-auto robil z <main> scroll kontajner, ktorý nikdy
   nescrolloval (výška rastie s obsahom, scrolluje okno) — a sticky vnútri
   administrácie (panel formulára, lišta Uložiť) sa preto nikdy neprilepil.
   Široké tabuľky majú vlastný overflow-x-auto. */
.body { @apply min-w-0 overflow-x-clip p-4; }
.body-inner { @apply mx-auto flex min-h-full w-full max-w-[1300px] gap-4; }
.page-content { @apply min-w-0 flex-1; }
.right-aside { @apply hidden xl:block w-72 shrink-0 self-stretch border-l border-slate-200; }
.footer { @apply border-t border-slate-200 bg-white px-5 py-4 text-slate-600; }

@media (min-width: 768px) {
  .more-btn { display: none; }
  .layout-shell { grid-template-columns: 210px minmax(0, 1fr); }
  .layout-shell.collapsed { grid-template-columns: 60px minmax(0, 1fr); }
  .aside {
    height: 100vh;
    overflow-y: auto;
  }
}

/* Mobile: aside becomes a compact bottom tab bar instead of a tall block */
@media (max-width: 767px) {
  .aside {
    @apply fixed inset-x-0 bottom-0 top-auto z-50 h-auto flex-row items-stretch justify-around gap-1 border-r-0 border-t border-amber-300/35 p-1;
    overflow-x: auto;
  }
  .aside-brand,
  .toggle-btn { @apply hidden; }
  .aside-nav { @apply mt-0 flex-1 flex-row justify-around gap-0.5; }
  .aside-link { @apply flex-1 flex-col items-center gap-1 rounded-md px-1 py-1.5 text-[0.62rem] font-medium; }
  .nav-label { @apply w-auto text-[0.62rem] leading-none opacity-100; }
  /* Lišta: štyri hlavné sekcie + Menu. Skupiny sa otvárajú v paneli nad lištou. */
  .aside { overflow: visible; }
  .group-toggle,
  .group-links { display: none !important; }
  .primary-links { display: contents; }
  .more-panel {
    @apply absolute inset-x-2 bottom-full mb-2 grid max-h-[70vh] gap-3 overflow-y-auto rounded-xl border border-amber-300/25 p-3 shadow-2xl;
    background: rgb(68 64 60);
  }
  .more-heading { @apply mb-1.5 px-1 text-[0.68rem] font-bold uppercase tracking-wider text-amber-100/60; }
  .more-grid { @apply grid grid-cols-4 gap-1; }
  .more-link {
    @apply flex flex-col items-center gap-1 rounded-md px-1 py-2 text-center text-[0.62rem] font-medium leading-tight text-amber-50/85 no-underline hover:bg-amber-200/12;
  }
  .more-link.active { @apply bg-amber-300 text-amber-950; }
  /* Keep footer/content clear of the fixed bar */
  .content-shell { @apply pb-16; }
}
</style>
