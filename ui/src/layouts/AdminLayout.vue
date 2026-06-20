<template>
  <div class="layout-shell admin-layout">
    <aside class="aside">
      <RouterLink to="/admin" class="aside-brand">
        <span class="brand-mark">A</span>
        <span class="brand-copy">
          <strong>Event</strong>
          <small>Admin</small>
        </span>
      </RouterLink>

      <nav class="aside-nav">
        <RouterLink to="/admin/events" class="aside-link" active-class="active">Eventy</RouterLink>
        <RouterLink to="/admin/canals" class="aside-link" active-class="active">Kanály</RouterLink>
        <RouterLink to="/admin/venues" class="aside-link" active-class="active">Miesta</RouterLink>
        <RouterLink to="/admin/users" class="aside-link" active-class="active">Používatelia</RouterLink>
        <RouterLink to="/admin/settings" class="aside-link" active-class="active">Nastavenia</RouterLink>
        <RouterLink to="/admin/files" class="aside-link" active-class="active">Súbory</RouterLink>
      </nav>
    </aside>

    <div class="content-shell">
      <header class="header">
        <RouterLink to="/admin" class="brand">Admin</RouterLink>
        <nav class="header-nav">
          <RouterLink to="/dashboard" class="header-link">Dashboard</RouterLink>
          <button class="btn btn-sm btn-secondary" @click="handleLogout">Odhlásiť</button>
        </nav>
      </header>

      <main class="body">
        <RouterView />
      </main>

      <footer class="footer text-sm">© {{ new Date().getFullYear() }} Event</footer>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useAuthStore } from '@/stores/auth'
import { useRouter } from 'vue-router'

const auth = useAuthStore()
const router = useRouter()

async function handleLogout() {
  await auth.logout()
  router.push({ name: 'login' })
}
</script>

<style scoped>
@reference "tailwindcss";

.layout-shell {
  @apply grid min-h-screen bg-slate-100;
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
.header-link { @apply rounded-md px-2 py-1 text-amber-50/80 no-underline hover:bg-amber-200/15 hover:text-white; }
.aside {
  @apply relative z-40 flex flex-row items-center gap-3 overflow-auto p-3 text-white md:min-h-screen md:flex-col md:items-stretch md:overflow-visible md:border-r md:border-amber-900/40;
  background: linear-gradient(180deg, rgb(68 64 60) 0%, rgb(92 64 51) 100%);
}
.aside-brand { @apply flex shrink-0 items-center gap-3 rounded-lg px-2 py-2 text-white no-underline hover:bg-amber-200/12; }
.brand-mark { @apply grid size-10 shrink-0 place-items-center rounded-lg bg-amber-300 text-sm font-black text-amber-950 shadow-sm; }
.brand-copy { @apply hidden min-w-0 md:grid; }
.brand-copy strong { @apply truncate text-sm leading-tight text-white; }
.brand-copy small { @apply mt-0.5 text-xs text-amber-100/70; }
.aside-nav { @apply flex min-w-0 flex-1 flex-row gap-1 md:mt-3 md:flex-col; }
.aside-link { @apply whitespace-nowrap rounded-lg px-3 py-2 text-sm font-semibold text-amber-50/80 no-underline hover:bg-amber-200/12 hover:text-white; }
.aside-link.active { @apply bg-amber-300 text-amber-950 font-bold; }
.body { @apply min-w-0 overflow-auto p-4; }
.footer { @apply border-t border-slate-200 bg-white px-5 py-4 text-slate-600; }

@media (min-width: 768px) {
  .layout-shell { grid-template-columns: 260px minmax(0, 1fr); }
}
</style>
