<template>
  <div class="layout-shell admin-layout" :class="{ collapsed }">
    <aside class="aside">
      <RouterLink to="/admin" class="aside-brand">
        <span class="brand-mark">A</span>
        <span class="brand-copy">
          <strong>Event</strong>
          <small>Admin</small>
        </span>
      </RouterLink>

      <nav class="aside-nav">
        <RouterLink to="/admin/events" class="aside-link" active-class="active" :title="collapsed ? 'Eventy' : undefined">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path stroke-linecap="round" d="M16 2v4M8 2v4M3 10h18"/></svg>
          <span class="nav-label">Eventy</span>
        </RouterLink>
        <RouterLink to="/admin/canals" class="aside-link" active-class="active" :title="collapsed ? 'Kanály' : undefined">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0"/></svg>
          <span class="nav-label">Kanály</span>
        </RouterLink>
        <RouterLink to="/admin/venues" class="aside-link" active-class="active" :title="collapsed ? 'Miesta' : undefined">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 2C8.134 2 5 5.134 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.866-3.134-7-7-7zm0 9a2 2 0 110-4 2 2 0 010 4z"/></svg>
          <span class="nav-label">Miesta</span>
        </RouterLink>
        <RouterLink to="/admin/municipalities" class="aside-link" active-class="active" :title="collapsed ? 'Obce' : undefined">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
          <span class="nav-label">Obce</span>
        </RouterLink>
        <RouterLink to="/admin/users" class="aside-link" active-class="active" :title="collapsed ? 'Používatelia' : undefined">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
          <span class="nav-label">Používatelia</span>
        </RouterLink>
        <RouterLink to="/admin/files" class="aside-link" active-class="active" :title="collapsed ? 'Súbory' : undefined">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
          <span class="nav-label">Súbory</span>
        </RouterLink>
        <RouterLink to="/admin/tools" class="aside-link" active-class="active" :title="collapsed ? 'Nástroje' : undefined">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M14.121 14.121L19 19M10 7a3 3 0 100 6 3 3 0 000-6z"/></svg>
          <span class="nav-label">Nástroje</span>
        </RouterLink>
        <RouterLink to="/admin/settings" class="aside-link" active-class="active" :title="collapsed ? 'Nastavenia' : undefined">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><circle cx="12" cy="12" r="3"/></svg>
          <span class="nav-label">Nastavenia</span>
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
        <RouterLink to="/admin" class="brand">Admin</RouterLink>
        <nav class="header-nav">
          <UserDropdown variant="amber" logout-to="login" />
        </nav>
      </header>

      <main class="body">
        <div class="body-inner">
          <div class="page-content">
            <RouterView />
          </div>

          <aside class="right-aside">
            <MunicipalityAside v-if="munResource" scope="admin" :resource="munResource" />
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

const STORAGE_KEY = 'admin-sidebar-collapsed'
const collapsed = ref(localStorage.getItem(STORAGE_KEY) === '1')

function toggle() {
  collapsed.value = !collapsed.value
  localStorage.setItem(STORAGE_KEY, collapsed.value ? '1' : '0')
}

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
.brand-mark { @apply grid size-10 shrink-0 place-items-center rounded-lg bg-amber-300 text-sm font-black text-amber-950 shadow-sm; }
.brand-copy { @apply min-w-0 overflow-hidden transition-all duration-200; }
.brand-copy strong { @apply block truncate text-sm leading-tight text-white; }
.brand-copy small { @apply mt-0.5 block text-xs text-amber-100/70; }

.aside-nav { @apply mt-3 flex flex-col gap-0.5; }
.nav-icon { @apply h-5 w-5 shrink-0; }
.nav-label { @apply overflow-hidden whitespace-nowrap transition-all duration-200; }
.aside-link {
  @apply flex items-center gap-3 rounded-lg px-2.5 py-2 text-sm font-semibold text-amber-50/80 no-underline hover:bg-amber-200/12 hover:text-white overflow-hidden;
}
.aside-link.active { @apply bg-amber-300 text-amber-950; }

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
    @apply fixed inset-x-0 bottom-0 top-auto z-50 h-auto flex-row items-stretch justify-around gap-1 border-r-0 border-t border-amber-300/35 p-1;
    overflow-x: auto;
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
