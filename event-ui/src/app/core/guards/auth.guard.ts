import { CanActivateChildFn, CanActivateFn } from '@angular/router';
import { inject } from '@angular/core';
import { Router } from '@angular/router';
import { catchError, map, of, switchMap, take } from 'rxjs';
import { AuthService } from '../services/auth.service';

function hasAuthToken(): boolean {
  if (typeof window === 'undefined') {
    return false;
  }

  return Boolean(localStorage.getItem('auth_token'));
}

const checkAuth: CanActivateFn = (_route, state) => {
  const auth = inject(AuthService);
  const router = inject(Router);

  return auth.currentIdentity$.pipe(
    take(1),
    map((identity) =>
      identity || hasAuthToken()
        ? true
        : router.createUrlTree(['/login'], {
            queryParams: { redirect: state.url }
          })
    )
  );
};

export const authGuard: CanActivateFn = (route, state) => checkAuth(route, state);

export const authGuardChild: CanActivateChildFn = (route, state) => checkAuth(route, state);

const checkSuperAdmin: CanActivateFn = (_route, state) => {
  const auth = inject(AuthService);
  const router = inject(Router);

  return auth.currentIdentity$.pipe(
    take(1),
    switchMap((identity) => {
      if (identity) {
        return of(identity);
      }

      if (!hasAuthToken()) {
        return of(null);
      }

      return auth.fetchCurrentIdentity().pipe(catchError(() => of(null)));
    }),
    map((identity) => {
      if (!identity) {
        return router.createUrlTree(['/login'], {
          queryParams: { redirect: state.url }
        });
      }

      return identity.roles?.includes('super-admin')
        ? true
        : router.createUrlTree(['/dashboard']);
    })
  );
};

export const superAdminGuard: CanActivateFn = (route, state) => checkSuperAdmin(route, state);

export const superAdminGuardChild: CanActivateChildFn = (route, state) =>
  checkSuperAdmin(route, state);
