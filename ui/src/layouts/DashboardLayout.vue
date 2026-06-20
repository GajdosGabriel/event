<template>
  <div class="layout-shell dashboard-layout">
    <aside class="aside">
      <RouterLink to="/dashboard" class="aside-brand">
        <span class="brand-mark">E</span>
        <span class="brand-copy">
          <strong>Event</strong>
          <small>{{ auth.canalName || 'Dashboard' }}</small>
        </span>
      </RouterLink>

      <nav class="aside-nav">
        <RouterLink to="/dashboard/events" class="aside-link" active-class="active">Eventy</RouterLink>
        <RouterLink to="/dashboard/canals" class="aside-link" active-class="active">Kanály</RouterLink>
        <RouterLink to="/dashboard/venues" class="aside-link" active-class="active">Miesta</RouterLink>
      </nav>
    </aside>

    <div class="content-shell">
      <header class="header">
        <RouterLink to="/dashboard" class="brand">Dashboard</RouterLink>
        <nav class="header-nav">
          <UserDropdown variant="teal" logout-to="login" />
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
import UserDropdown from '@/components/UserDropdown.vue'

const auth = useAuthStore()
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
  @apply relative z-30 flex items-center justify-between px-5 py-4 text-white;
  background: linear-gradient(135deg, rgb(30 64 70) 0%, rgb(17 94 89) 100%);
  @apply border-b border-teal-300/30 text-teal-50 shadow-sm;
}
.brand { @apply font-bold tracking-wide text-white no-underline; }
.header-nav { @apply ml-auto flex items-center gap-3; }
.header-link { @apply rounded-md px-2 py-1 text-teal-50/80 no-underline hover:bg-teal-200/15 hover:text-white; }
.aside {
  @apply relative z-40 flex flex-row items-center gap-3 overflow-auto p-3 text-white md:min-h-screen md:flex-col md:items-stretch md:overflow-visible md:border-r md:border-slate-800;
  background: linear-gradient(180deg, rgb(30 64 70) 0%, rgb(19 78 74) 100%);
}
.aside-brand { @apply flex shrink-0 items-center gap-3 rounded-lg px-2 py-2 text-white no-underline hover:bg-teal-200/12; }
.brand-mark { @apply grid size-10 shrink-0 place-items-center rounded-lg bg-teal-300 text-sm font-black text-teal-950 shadow-sm; }
.brand-copy { @apply hidden min-w-0 md:grid; }
.brand-copy strong { @apply truncate text-sm leading-tight text-white; }
.brand-copy small { @apply mt-0.5 text-xs text-teal-100/70; }
.aside-nav { @apply flex min-w-0 flex-1 flex-row gap-1 md:mt-3 md:flex-col; }
.aside-link { @apply whitespace-nowrap rounded-lg px-3 py-2 text-sm font-semibold text-teal-50/80 no-underline hover:bg-teal-200/12 hover:text-white; }
.aside-link.active { @apply bg-teal-300 text-teal-950 font-bold; }
.body { @apply min-w-0 overflow-auto p-4; }
.footer { @apply border-t border-slate-200 bg-white px-5 py-4 text-slate-600; }

@media (min-width: 768px) {
  .layout-shell { grid-template-columns: 260px minmax(0, 1fr); }
}
</style>
