<template>
  <div class="layout-shell" :class="{ collapsed }">
    <aside class="aside">
      <RouterLink to="/dashboard" class="aside-brand">
        <span class="brand-mark">E</span>
        <span class="brand-copy">
          <strong>Event</strong>
          <small>{{ auth.canalName || 'Dashboard' }}</small>
        </span>
      </RouterLink>

      <nav class="aside-nav">
        <RouterLink to="/dashboard/events" class="aside-link" active-class="active" :title="collapsed ? 'Eventy' : undefined">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path stroke-linecap="round" d="M16 2v4M8 2v4M3 10h18"/></svg>
          <span class="nav-label">Eventy</span>
        </RouterLink>
        <RouterLink to="/dashboard/canals" class="aside-link" active-class="active" :title="collapsed ? 'Kanály' : undefined">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0"/></svg>
          <span class="nav-label">Kanály</span>
        </RouterLink>
        <RouterLink to="/dashboard/venues" class="aside-link" active-class="active" :title="collapsed ? 'Miesta' : undefined">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 2C8.134 2 5 5.134 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.866-3.134-7-7-7zm0 9a2 2 0 110-4 2 2 0 010 4z"/></svg>
          <span class="nav-label">Miesta</span>
        </RouterLink>
      </nav>

      <button class="toggle-btn" @click="toggle" :title="collapsed ? 'Rozbaliť' : 'Zbaliť'">
        <svg class="toggle-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" :d="collapsed ? 'M9 5l7 7-7 7' : 'M15 19l-7-7 7-7'" />
        </svg>
        <span class="nav-label">Zbaliť</span>
      </button>
    </aside>

    <div class="content-shell">
      <header class="header">
        <RouterLink to="/dashboard" class="brand">Dashboard</RouterLink>
        <nav class="header-nav">
          <UserDropdown variant="teal" logout-to="login" />
        </nav>
      </header>

      <main class="body">
        <div class="body-inner">
          <div class="page-content">
            <RouterView />
          </div>

          <aside class="right-aside">
            <MunicipalityAside v-if="munResource" scope="dashboard" :resource="munResource" />
          </aside>
        </div>
      </main>

      <footer class="footer text-sm">© {{ new Date().getFullYear() }} Event</footer>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import UserDropdown from '@/components/UserDropdown.vue'
import MunicipalityAside from '@/components/MunicipalityAside.vue'

const auth = useAuthStore()
const route = useRoute()

const STORAGE_KEY = 'dashboard-sidebar-collapsed'
const collapsed = ref(localStorage.getItem(STORAGE_KEY) === '1')

function toggle() {
  collapsed.value = !collapsed.value
  localStorage.setItem(STORAGE_KEY, collapsed.value ? '1' : '0')
}

const MUN_RESOURCES = ['events', 'canals', 'venues']
const munResource = computed(() => {
  const segs = route.path.split('/').filter(Boolean)
  // only show on index pages: /dashboard/<resource>
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
  @apply relative z-30 flex items-center justify-between px-5 py-4 text-white;
  background: linear-gradient(135deg, rgb(30 64 70) 0%, rgb(17 94 89) 100%);
  @apply border-b border-teal-300/30 text-teal-50 shadow-sm;
}
.brand { @apply font-bold tracking-wide text-white no-underline; }
.header-nav { @apply ml-auto flex items-center gap-3; }

/* Aside */
.aside {
  @apply sticky top-0 z-40 flex flex-col border-r border-slate-800 p-3 text-white;
  overflow-x: hidden;
  background: linear-gradient(180deg, rgb(30 64 70) 0%, rgb(19 78 74) 100%);
  transition: width 0.22s ease;
}
.aside-brand {
  @apply flex shrink-0 items-center gap-3 rounded-lg px-2 py-2 text-white no-underline hover:bg-teal-200/12 overflow-hidden;
}
.brand-mark { @apply grid size-10 shrink-0 place-items-center rounded-lg bg-teal-300 text-sm font-black text-teal-950 shadow-sm; }
.brand-copy { @apply min-w-0 overflow-hidden transition-all duration-200; }
.brand-copy strong { @apply block truncate text-sm leading-tight text-white; }
.brand-copy small { @apply mt-0.5 block text-xs text-teal-100/70; }

.aside-nav { @apply mt-3 flex flex-col gap-0.5; }
.nav-icon { @apply h-5 w-5 shrink-0; }
.nav-label { @apply overflow-hidden whitespace-nowrap transition-all duration-200; }
.aside-link {
  @apply flex items-center gap-3 rounded-lg px-2.5 py-2 text-sm font-semibold text-teal-50/80 no-underline hover:bg-teal-200/12 hover:text-white overflow-hidden;
}
.aside-link.active { @apply bg-teal-300 text-teal-950; }

/* Toggle button */
.toggle-btn {
  @apply mt-auto flex items-center justify-center rounded-lg p-2 text-teal-100/60 hover:bg-teal-200/12 hover:text-white cursor-pointer border-0 bg-transparent;
}
.toggle-icon { @apply h-4 w-4 transition-transform duration-200; }

/* Collapsed state */
.collapsed .brand-copy { @apply w-0 opacity-0; }
.collapsed .nav-label { @apply w-0 opacity-0; }
.collapsed .aside-link { @apply justify-center px-2; }
.collapsed .toggle-btn { @apply justify-center; }

.body { @apply min-w-0 overflow-auto p-4; }
.body-inner { @apply flex min-h-full gap-4; }
.page-content { @apply min-w-0 flex-1; }
.right-aside { @apply hidden xl:block w-72 shrink-0 self-stretch border-l border-slate-200; }
.footer { @apply border-t border-slate-200 bg-white px-5 py-4 text-slate-600; }

@media (min-width: 768px) {
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
    @apply fixed inset-x-0 bottom-0 top-auto z-50 h-auto flex-row items-stretch justify-around gap-1 border-r-0 border-t border-teal-300/30 p-1;
  }
  .aside-brand,
  .toggle-btn { @apply hidden; }
  .aside-nav { @apply mt-0 flex-1 flex-row justify-around gap-0.5; }
  .aside-link { @apply flex-1 flex-col items-center gap-1 rounded-md px-1 py-1.5 text-[0.62rem] font-medium; }
  .nav-label { @apply w-auto text-[0.62rem] leading-none opacity-100; }
  /* Keep footer/content clear of the fixed bar */
  .content-shell { @apply pb-16; }
}
</style>
