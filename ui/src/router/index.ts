import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const ResourceIndex = () => import('@/pages/ResourceIndexPage.vue')

const router = createRouter({
  history: createWebHistory(),
  routes: [
    // Public
    {
      path: '/',
      component: () => import('@/layouts/PublicLayout.vue'),
      children: [
        { path: '', name: 'home', component: () => import('@/pages/home/HomePage.vue') },
        { path: 'login', name: 'login', component: () => import('@/pages/auth/LoginPage.vue') },
        { path: 'register', name: 'register', component: () => import('@/pages/auth/RegisterPage.vue') },
        { path: 'verify-email', name: 'verify-email', component: () => import('@/pages/auth/VerifyEmailPage.vue') },
        { path: 'verify-email/:token', name: 'verify-email-link', component: () => import('@/pages/auth/VerifyEmailLinkPage.vue') },
        { path: 'events/:id', name: 'event-public-show', component: () => import('@/pages/events/EventPublicShowPage.vue') },
        { path: 'venues/:id', name: 'venue-public-show', component: () => import('@/pages/venues/VenuePublicShowPage.vue') },
        { path: 'canals/:id', name: 'canal-public-show', component: () => import('@/pages/canals/CanalPublicShowPage.vue') },
      ],
    },

    // Dashboard
    {
      path: '/dashboard',
      component: () => import('@/layouts/DashboardLayout.vue'),
      meta: { requiresAuth: true },
      children: [
        { path: '', name: 'dashboard', component: () => import('@/pages/dashboard/DashboardPage.vue') },
        { path: 'events', name: 'dashboard-events', component: ResourceIndex, props: { resource: 'event' } },
        { path: 'events/create', name: 'dashboard-events-create', component: () => import('@/pages/events/EventEditPage.vue') },
        { path: 'events/:id', name: 'dashboard-events-show', component: () => import('@/pages/events/EventShowPage.vue') },
        { path: 'events/:id/edit', name: 'dashboard-events-edit', component: () => import('@/pages/events/EventEditPage.vue') },
        { path: 'canals', name: 'dashboard-canals', component: ResourceIndex, props: { resource: 'canal' } },
        { path: 'canals/create', name: 'dashboard-canals-create', component: () => import('@/pages/canals/CanalEditPage.vue') },
        { path: 'canals/:id', name: 'dashboard-canals-show', component: () => import('@/pages/canals/CanalShowPage.vue') },
        { path: 'canals/:id/edit', name: 'dashboard-canals-edit', component: () => import('@/pages/canals/CanalEditPage.vue') },
        { path: 'venues', name: 'dashboard-venues', component: ResourceIndex, props: { resource: 'venue' } },
        { path: 'venues/create', name: 'dashboard-venues-create', component: () => import('@/pages/venues/VenueEditPage.vue') },
        { path: 'venues/:id', name: 'dashboard-venues-show', component: () => import('@/pages/venues/VenueShowPage.vue') },
        { path: 'venues/:id/edit', name: 'dashboard-venues-edit', component: () => import('@/pages/venues/VenueEditPage.vue') },
        { path: 'municipalities', name: 'dashboard-municipalities', component: () => import('@/pages/dashboard/DashboardMunicipalitiesPage.vue') },
      ],
    },

    // Admin
    {
      path: '/admin',
      component: () => import('@/layouts/AdminLayout.vue'),
      meta: { requiresAuth: true, requiresSuperAdmin: true },
      children: [
        { path: '', name: 'admin', component: () => import('@/pages/admin/AdminIndexPage.vue') },
        { path: 'events', name: 'admin-events', component: ResourceIndex, props: { resource: 'event', scope: 'admin' } },
        { path: 'events/create', name: 'admin-events-create', component: () => import('@/pages/events/EventEditPage.vue'), props: { scope: 'admin' } },
        { path: 'events/:id', name: 'admin-events-show', component: () => import('@/pages/events/EventShowPage.vue'), props: { scope: 'admin' } },
        { path: 'events/:id/edit', name: 'admin-events-edit', component: () => import('@/pages/events/EventEditPage.vue'), props: { scope: 'admin' } },
        { path: 'canals', name: 'admin-canals', component: ResourceIndex, props: { resource: 'canal', scope: 'admin' } },
        { path: 'canals/create', name: 'admin-canals-create', component: () => import('@/pages/canals/CanalEditPage.vue'), props: { scope: 'admin' } },
        { path: 'canals/:id', name: 'admin-canals-show', component: () => import('@/pages/canals/CanalShowPage.vue'), props: { scope: 'admin' } },
        { path: 'canals/:id/edit', name: 'admin-canals-edit', component: () => import('@/pages/canals/CanalEditPage.vue'), props: { scope: 'admin' } },
        { path: 'venues', name: 'admin-venues', component: ResourceIndex, props: { resource: 'venue', scope: 'admin' } },
        { path: 'venues/create', name: 'admin-venues-create', component: () => import('@/pages/venues/VenueEditPage.vue'), props: { scope: 'admin' } },
        { path: 'venues/:id', name: 'admin-venues-show', component: () => import('@/pages/venues/VenueShowPage.vue'), props: { scope: 'admin' } },
        { path: 'venues/:id/edit', name: 'admin-venues-edit', component: () => import('@/pages/venues/VenueEditPage.vue'), props: { scope: 'admin' } },
        { path: 'municipalities', name: 'admin-municipalities', component: () => import('@/pages/admin/AdminMunicipalitiesPage.vue') },
        { path: 'users', name: 'admin-users', component: () => import('@/pages/admin/AdminUsersPage.vue') },
        { path: 'settings', name: 'admin-settings', component: () => import('@/pages/admin/AdminSettingsPage.vue') },
        { path: 'files', name: 'admin-files', component: () => import('@/pages/admin/AdminFilesPage.vue') },
      ],
    },
  ],
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }

  if (to.meta.requiresAuth && auth.isAuthenticated && !auth.identity) {
    await auth.fetchIdentity()
  }

  if (to.meta.requiresSuperAdmin && !auth.isSuperAdmin) {
    return { name: 'dashboard' }
  }
})

export default router
