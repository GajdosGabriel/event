import { Routes } from '@angular/router';
import { DashboardLayoutComponent } from './layout/components/dashboard-layout/dashboard-layout.component';
import { AdminLayoutComponent } from './layout/components/admin-layout/admin-layout.component';
import { PublicLayoutComponent } from './layout/components/public-layout/public-layout.component';
import {
  authGuard,
  authGuardChild,
  superAdminGuard,
  superAdminGuardChild
} from './core/guards/auth.guard';

export const routes: Routes = [
  {
    path: 'events/:id',
    component: PublicLayoutComponent,
    children: [
      {
        path: '',
        pathMatch: 'full',
        loadComponent: () =>
          import('./features/events/pages/event-show.page').then((m) => m.EventShowPage),
      },
    ],
  },
  {
    path: 'events/:id',
    redirectTo: 'event/:id',
  },
  {
    path: '',
    component: PublicLayoutComponent,
    children: [
      {
        path: '',
        pathMatch: 'full',
        loadComponent: () => import('./features/home/pages/home.page').then((m) => m.HomePage),
      },
      {
        path: 'login',
        loadComponent: () => import('./features/auth/pages/login.page').then((m) => m.LoginPage),
      },
      {
        path: 'register',
        loadComponent: () =>
          import('./features/auth/pages/register.page').then((m) => m.RegisterPage),
      },
      {
        path: 'verify-email',
        loadComponent: () =>
          import('./features/auth/pages/verify-email.page').then((m) => m.VerifyEmailPage),
      },
    ],
  },
  {
    path: 'dashboard',
    component: DashboardLayoutComponent,
    canActivate: [authGuard],
    canActivateChild: [authGuardChild],
    children: [
      {
        path: '',
        pathMatch: 'full',
        loadComponent: () =>
          import('./features/dashboard/pages/dashboard.page').then((m) => m.DashboardPage),
      },
      {
        path: 'events',
        children: [
          {
            path: '',
            pathMatch: 'full',
            loadComponent: () =>
              import('./features/events/pages/event-index.page').then((m) => m.EventIndexPage),
          },
        ],
      },
      {
        path: 'events/create',
        children: [
          {
            path: '',
            pathMatch: 'full',
            loadComponent: () =>
              import('./features/events/pages/event-edit.page').then((m) => m.EventEditPage),
          },
        ],
      },
      {
        path: 'events/:id',
        children: [
          {
            path: '',
            pathMatch: 'full',
            loadComponent: () =>
              import('./features/events/pages/event-show.page').then((m) => m.EventShowPage),
          },
        ],
      },
      {
        path: 'events/:id/edit',
        children: [
          {
            path: '',
            pathMatch: 'full',
            loadComponent: () =>
              import('./features/events/pages/event-edit.page').then((m) => m.EventEditPage),
          },
        ],
      },
      {
        path: 'canals',
        children: [
          {
            path: '',
            pathMatch: 'full',
            loadComponent: () =>
              import('./features/canals/pages/canal-index.page').then((m) => m.CanalIndexPage),
          },
          {
            path: 'create',
            pathMatch: 'full',
            loadComponent: () =>
              import('./features/canals/pages/canal-edit.page').then((m) => m.CanalEditPage),
          },
          {
            path: ':id/edit',
            pathMatch: 'full',
            loadComponent: () =>
              import('./features/canals/pages/canal-edit.page').then((m) => m.CanalEditPage),
          },
          {
            path: ':id',
            pathMatch: 'full',
            loadComponent: () =>
              import('./features/canals/pages/canal-show.page').then((m) => m.CanalShowPage),
          },
        ],
      },
      {
        path: 'venues',
        children: [
          {
            path: '',
            pathMatch: 'full',
            loadComponent: () =>
              import('./features/venue/pages/venue-index.page').then((m) => m.VenueIndexPage),
          },
          {
            path: 'create',
            pathMatch: 'full',
            loadComponent: () =>
              import('./features/venue/pages/venue-edit.page').then((m) => m.VenueEditPage),
          },
          {
            path: ':id/edit',
            pathMatch: 'full',
            loadComponent: () =>
              import('./features/venue/pages/venue-edit.page').then((m) => m.VenueEditPage),
          },
          {
            path: ':id',
            pathMatch: 'full',
            loadComponent: () =>
              import('./features/venue/pages/venue-show.page').then((m) => m.VenueShowPage),
          },
        ],
      },
    ],
  },
  {
    path: 'admin',
    component: AdminLayoutComponent,
    canActivate: [authGuard, superAdminGuard],
    canActivateChild: [authGuardChild, superAdminGuardChild],
    children: [
      {
        path: '',
        pathMatch: 'full',
        loadComponent: () =>
          import('./features/admin/pages/admin-index.page').then((m) => m.AdminIndexPage),
      },
      {
        path: 'events',
        children: [
          {
            path: '',
            pathMatch: 'full',
            loadComponent: () =>
              import('./features/events/pages/event-index.page').then((m) => m.EventIndexPage),
          },
        ],
      },
      {
        path: 'events/create',
        children: [
          {
            path: '',
            pathMatch: 'full',
            loadComponent: () =>
              import('./features/events/pages/event-edit.page').then((m) => m.EventEditPage),
          },
        ],
      },
      {
        path: 'events/:id',
        children: [
          {
            path: '',
            pathMatch: 'full',
            loadComponent: () =>
              import('./features/events/pages/event-show.page').then((m) => m.EventShowPage),
          },
        ],
      },
      {
        path: 'events/:id/edit',
        children: [
          {
            path: '',
            pathMatch: 'full',
            loadComponent: () =>
              import('./features/events/pages/event-edit.page').then((m) => m.EventEditPage),
          },
        ],
      },
      {
        path: 'venues',
        children: [
          {
            path: '',
            pathMatch: 'full',
            loadComponent: () =>
              import('./features/venue/pages/venue-index.page').then((m) => m.VenueIndexPage),
          },
          {
            path: 'create',
            pathMatch: 'full',
            loadComponent: () =>
              import('./features/venue/pages/venue-edit.page').then((m) => m.VenueEditPage),
          },
          {
            path: ':id/edit',
            pathMatch: 'full',
            loadComponent: () =>
              import('./features/venue/pages/venue-edit.page').then((m) => m.VenueEditPage),
          },
          {
            path: ':id',
            pathMatch: 'full',
            loadComponent: () =>
              import('./features/venue/pages/venue-show.page').then((m) => m.VenueShowPage),
          },
        ],
      },
      {
        path: 'canals',
        children: [
          {
            path: '',
            pathMatch: 'full',
            loadComponent: () =>
              import('./features/canals/pages/canal-index.page').then((m) => m.CanalIndexPage),
          },
          {
            path: 'create',
            pathMatch: 'full',
            loadComponent: () =>
              import('./features/canals/pages/canal-edit.page').then((m) => m.CanalEditPage),
          },
          {
            path: ':id/edit',
            pathMatch: 'full',
            loadComponent: () =>
              import('./features/canals/pages/canal-edit.page').then((m) => m.CanalEditPage),
          },
          {
            path: ':id',
            pathMatch: 'full',
            loadComponent: () =>
              import('./features/canals/pages/canal-show.page').then((m) => m.CanalShowPage),
          },
        ],
      },
      {
        path: 'users',
        pathMatch: 'full',
        loadComponent: () =>
          import('./features/admin/pages/admin-users.page').then((m) => m.AdminUsersPage),
      },
      {
        path: 'settings',
        pathMatch: 'full',
        loadComponent: () =>
          import('./features/admin/pages/admin-settings.page').then((m) => m.AdminSettingsPage),
      },
      {
        path: 'files',
        pathMatch: 'full',
        loadComponent: () =>
          import('./features/admin/pages/admin-files.page').then((m) => m.AdminFilesPage),
      },
    ],
  },
  { path: '**', redirectTo: '' },
];
