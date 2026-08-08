import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const ResourceIndex = () => import('@/pages/ResourceIndexPage.vue')
const EventListPage = () => import('@/pages/events/EventListPage.vue')

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
        // Verejný katalóg. Statické segmenty (`tento-vikend`, `mesto`, `tema`)
        // sú landing stránky s vlastným title a popisom — bez nich existoval
        // zoznam podujatí len ako homepage s query parametrami, ktorú nemá
        // vyhľadávač ako indexovať.
        { path: 'podujatia', name: 'events-public-index', component: EventListPage },
        { path: 'podujatia/tento-vikend', name: 'events-public-weekend', component: EventListPage, props: { variant: 'weekend' } },
        {
          path: 'podujatia/mesto/:slug',
          name: 'events-public-municipality',
          component: EventListPage,
          props: (route) => ({ variant: 'municipality', slug: route.params.slug }),
        },
        {
          path: 'podujatia/tema/:slug',
          name: 'events-public-tag',
          component: EventListPage,
          props: (route) => ({ variant: 'tag', slug: route.params.slug }),
        },
        // `:slugId` je `{slug}-{id}`; routuje sa len id za poslednou pomlčkou,
        // takže odkaz prežije premenovanie aj holé číslo zo starej adresy.
        { path: 'podujatia/:slugId', name: 'event-public-show', component: () => import('@/pages/events/EventPublicShowPage.vue') },
        { path: 'miesta/:slugId', name: 'venue-public-show', component: () => import('@/pages/venues/VenuePublicShowPage.vue') },
        { path: 'organizatori/:slugId', name: 'canal-public-show', component: () => import('@/pages/canals/CanalPublicShowPage.vue') },

        // Pôvodné číselné adresy. Sú rozposlané v e-mailoch a zdieľané, takže
        // musia ostať funkčné; kanonickú podobu z nich urobí presmerovanie
        // (na produkcii navyše 301 v .htaccess — pozri deploy/htaccess.md).
        { path: 'events/:id', redirect: (to) => `/podujatia/${to.params.id}` },
        { path: 'venues/:id', redirect: (to) => `/miesta/${to.params.id}` },
        { path: 'canals/:id', redirect: (to) => `/organizatori/${to.params.id}` },
        // „Nahrajte plagát, o všetko ostatné sa postaráme." Zámerne verejné —
        // analýza beží bez účtu, registráciu si sprievodca vypýta až na konci.
        { path: 'nahrat-plagat', name: 'poster-upload', component: () => import('@/pages/posters/PosterUploadPage.vue') },
        // Návrat k rozpracovanému plagátu z odkazu v e-maile (token je v query).
        { path: 'nahrat-plagat/:id', name: 'poster-upload-draft', component: () => import('@/pages/posters/PosterUploadPage.vue') },
        { path: 'tickets/:uuid', name: 'ticket-public-show', component: () => import('@/pages/tickets/TicketPublicShowPage.vue') },
        { path: 'rsvp/:token', name: 'rsvp', component: () => import('@/pages/rsvp/RsvpPage.vue') },
        // Pozvánka do tímu kanála z e-mailu. Zámerne bez requiresAuth — detail
        // ukáže aj neprihlásenému, prijatie si prihlásenie vypýta samo.
        { path: 'pozvanka/:token', name: 'canal-invitation', component: () => import('@/pages/canals/CanalInvitationPage.vue') },
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
        { path: 'events/:id/tickets', name: 'dashboard-events-tickets', component: () => import('@/pages/events/EventTicketsSettingsPage.vue') },
        { path: 'events/:id/tickets/create', name: 'dashboard-events-tickets-create', component: () => import('@/pages/events/EventTicketTypeEditPage.vue') },
        { path: 'events/:id/tickets/:typeId/edit', name: 'dashboard-events-tickets-edit', component: () => import('@/pages/events/EventTicketTypeEditPage.vue') },
        { path: 'events/:id/attendees', name: 'dashboard-events-attendees', component: () => import('@/pages/events/EventAttendeesPage.vue') },
        { path: 'events/:id/checkin', name: 'dashboard-events-checkin', component: () => import('@/pages/events/EventCheckinScannerPage.vue') },
        { path: 'canals', name: 'dashboard-canals', component: ResourceIndex, props: { resource: 'canal' } },
        { path: 'canals/create', name: 'dashboard-canals-create', component: () => import('@/pages/canals/CanalEditPage.vue') },
        { path: 'canals/:id', name: 'dashboard-canals-show', component: () => import('@/pages/canals/CanalShowPage.vue') },
        { path: 'canals/:id/edit', name: 'dashboard-canals-edit', component: () => import('@/pages/canals/CanalEditPage.vue') },
        { path: 'venues', name: 'dashboard-venues', component: ResourceIndex, props: { resource: 'venue' } },
        { path: 'venues/create', name: 'dashboard-venues-create', component: () => import('@/pages/venues/VenueEditPage.vue') },
        { path: 'venues/:id', name: 'dashboard-venues-show', component: () => import('@/pages/venues/VenueShowPage.vue') },
        { path: 'venues/:id/edit', name: 'dashboard-venues-edit', component: () => import('@/pages/venues/VenueEditPage.vue') },
        { path: 'organizations', name: 'dashboard-organizations', component: () => import('@/pages/organizations/OrganizationListPage.vue') },
        { path: 'organizations/create', name: 'dashboard-organizations-create', component: () => import('@/pages/organizations/OrganizationEditPage.vue') },
        { path: 'organizations/:id/edit', name: 'dashboard-organizations-edit', component: () => import('@/pages/organizations/OrganizationEditPage.vue') },
        { path: 'municipalities', name: 'dashboard-municipalities', component: () => import('@/pages/dashboard/DashboardMunicipalitiesPage.vue') },
        // Inbox prijatých správ. Slovenská cesta zámerne — odkazuje naň e-mail
        // s odpoveďou aj dlaždica „Neprečítané správy" v štatistikách.
        { path: 'spravy', name: 'dashboard-messages', component: () => import('@/pages/dashboard/DashboardMessagesPage.vue') },
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
        { path: 'organizations', name: 'admin-organizations', component: () => import('@/pages/organizations/OrganizationListPage.vue'), props: { scope: 'admin' } },
        { path: 'organizations/create', name: 'admin-organizations-create', component: () => import('@/pages/organizations/OrganizationEditPage.vue'), props: { scope: 'admin' } },
        { path: 'organizations/:id/edit', name: 'admin-organizations-edit', component: () => import('@/pages/organizations/OrganizationEditPage.vue'), props: { scope: 'admin' } },
        { path: 'municipalities', name: 'admin-municipalities', component: () => import('@/pages/admin/AdminMunicipalitiesPage.vue') },
        // Oznamy a bannery verejného layoutu. Slovenská cesta zámerne — rovnako
        // ako `spravy` v dashboarde.
        { path: 'oznamy', name: 'admin-announcements', component: () => import('@/pages/admin/AdminAnnouncementsPage.vue') },
        { path: 'users', name: 'admin-users', component: () => import('@/pages/admin/AdminUsersPage.vue') },
        { path: 'users/:id', name: 'admin-users-show', component: () => import('@/pages/admin/AdminUserShowPage.vue') },
        { path: 'settings', name: 'admin-settings', component: () => import('@/pages/admin/AdminSettingsPage.vue') },
        { path: 'files', name: 'admin-files', component: () => import('@/pages/admin/AdminFilesPage.vue') },
        { path: 'tools', name: 'admin-tools', component: () => import('@/pages/admin/AdminToolsPage.vue') },
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
