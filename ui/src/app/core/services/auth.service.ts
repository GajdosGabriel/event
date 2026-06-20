import { HttpClient, HttpContext, HttpErrorResponse } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { BehaviorSubject, Observable, catchError, defer, finalize, map, switchMap, tap, throwError } from 'rxjs';
import { API_ENDPOINTS } from '../../constants/api.constants';
import { AuthCanalContextActive, AuthIdentity } from '../../models/auth-identity.model';
import { SKIP_GLOBAL_API_ERROR_TOAST } from '../interceptors/api-error.interceptor';

export interface LoginPayload {
  email: string;
  password: string;
}

export interface RegisterPayload {
  display_name: string;
  email: string;
  password: string;
  password_confirmation?: string;
}

type AuthIdentityEnvelope =
  | AuthIdentity
  | { user: AuthIdentity }
  | { data: AuthIdentity }
  | { data: unknown };

@Injectable({ providedIn: 'root' })
export class AuthService {
  private readonly http = inject(HttpClient);
  private readonly identitySubject = new BehaviorSubject<AuthIdentity | null>(null);

  readonly currentIdentity$ = this.identitySubject.asObservable();

  isAuthenticated(): boolean {
    return this.identitySubject.value !== null || !!this.token;
  }

  private get token(): string | null {
    if (typeof window === 'undefined') {
      return null;
    }
    return localStorage.getItem('auth_token');
  }

  private set token(value: string | null) {
    if (typeof window === 'undefined') {
      return;
    }
    if (value) {
      localStorage.setItem('auth_token', value);
    } else {
      localStorage.removeItem('auth_token');
    }
  }

  login(payload: LoginPayload): Observable<AuthIdentity> {
    const silentContext = new HttpContext().set(SKIP_GLOBAL_API_ERROR_TOAST, true);

    return this.ensureCsrfCookie().pipe(
      switchMap(() =>
        this.http.post<{
          user?: unknown;
          data?: { user?: unknown } | unknown;
          access_token?: string;
          token?: string;
          auth_token?: string;
          token_type?: string;
        }>(API_ENDPOINTS.authLogin, payload, { context: silentContext })
      ),
      tap((response) => {
        const token = response.access_token ?? response.token ?? response.auth_token;
        if (!token) {
          throw new Error('No auth token returned from login response');
        }
        this.token = token;

        const identity = this.unwrapIdentity(response);
        if (!identity) {
          throw new Error('No auth identity returned from login response');
        }
        this.identitySubject.next(identity);
      }),
      map((response) => {
        const identity = this.unwrapIdentity(response);
        if (!identity) {
          throw new Error('No auth identity returned from login response');
        }
        return identity;
      })
    );
  }

  register(payload: RegisterPayload): Observable<void> {
    const silentContext = new HttpContext().set(SKIP_GLOBAL_API_ERROR_TOAST, true);

    return this.ensureCsrfCookie().pipe(
      switchMap(() =>
        this.http.post<void>(API_ENDPOINTS.authRegister, payload, { context: silentContext })
      )
    );
  }

  logout(): Observable<void> {
    return defer(() => {
      const tokenForLogout = this.token;
      this.clearAuthState();
      const options = tokenForLogout
        ? {
            headers: {
              Authorization: `Bearer ${tokenForLogout}`
            }
          }
        : undefined;

      return this.http.post<void>(API_ENDPOINTS.authLogout, {}, options).pipe(
        catchError(() =>
          this.ensureCsrfCookie().pipe(
            switchMap(() => this.http.post<void>(API_ENDPOINTS.authLogout, {}, options))
          )
        ),
        finalize(() => this.clearAuthState())
      );
    });
  }

  clearAuthState(): void {
    this.identitySubject.next(null);
    this.token = null;
  }

  fetchCurrentIdentity(): Observable<AuthIdentity | null> {
    const silentContext = new HttpContext().set(SKIP_GLOBAL_API_ERROR_TOAST, true);

    return this.ensureCsrfCookie().pipe(
      switchMap(() =>
        this.http.get<AuthIdentityEnvelope>(API_ENDPOINTS.authMe, { context: silentContext })
      ),
      map((response) => this.unwrapIdentity(response)),
      tap((identity) => this.identitySubject.next(identity)),
      catchError((error) => {
        if (error instanceof HttpErrorResponse && error.status === 401) {
          this.clearAuthState();
        }

        return throwError(() => error);
      })
    );
  }

  startSocialLogin(provider: 'google' | 'facebook'): void {
    if (typeof window === 'undefined') {
      return;
    }

    const target =
      provider === 'google'
        ? API_ENDPOINTS.authGoogleRedirect
        : API_ENDPOINTS.authFacebookRedirect;

    window.location.assign(target);
  }

  private ensureCsrfCookie(): Observable<void> {
    return this.http.get<void>(API_ENDPOINTS.authCsrfCookie);
  }

  resendVerificationEmail(email: string): Observable<void> {
    const silentContext = new HttpContext().set(SKIP_GLOBAL_API_ERROR_TOAST, true);

    return this.ensureCsrfCookie().pipe(
      switchMap(() =>
        this.http.post<void>(API_ENDPOINTS.authRegisterResend, { email }, { context: silentContext })
      )
    );
  }

  setActiveCanal(canalId: number): Observable<AuthIdentity | null> {
    return this.http.post<void>(API_ENDPOINTS.authActiveCanal, { canal_id: canalId }).pipe(
      switchMap(() => this.fetchCurrentIdentity())
    );
  }

  private unwrapIdentity(response: unknown): AuthIdentity | null {
    const candidates: unknown[] = [];

    if (response && typeof response === 'object') {
      if ('data' in response) {
        const data = (response as { data: unknown }).data;
        candidates.push(data);

        if (data && typeof data === 'object' && 'user' in data) {
          candidates.push((data as { user: unknown }).user);
        }
      }

      if ('user' in response) {
        candidates.push((response as { user: unknown }).user);
      }
    }

    candidates.push(response);

    for (const candidate of candidates) {
      const parsed = this.parseIdentity(candidate);
      if (parsed) {
        return parsed;
      }
    }

    return null;
  }

  private parseIdentity(value: unknown): AuthIdentity | null {
    if (!value || typeof value !== 'object') {
      return null;
    }

    const candidate = value as Record<string, unknown>;
    const idRaw = candidate['id'];
    const id =
      typeof idRaw === 'number'
        ? idRaw
        : typeof idRaw === 'string' && idRaw.trim() && !Number.isNaN(Number(idRaw))
          ? Number(idRaw)
          : null;

    if (id === null) {
      return null;
    }

    const canalContext = this.parseCanalContext(candidate['canal_context']);

    const canalIdRaw = candidate['canal_id'] ?? canalContext?.active?.id;
    const canal_id =
      typeof canalIdRaw === 'number'
        ? canalIdRaw
        : typeof canalIdRaw === 'string' && canalIdRaw.trim() && !Number.isNaN(Number(canalIdRaw))
          ? Number(canalIdRaw)
          : null;

    const canalRaw =
      typeof candidate['canal'] === 'string' && candidate['canal'].trim()
        ? candidate['canal']
        : canalContext?.active?.name ?? '';
    const canal = typeof canalRaw === 'string' ? canalRaw.trim() : '';

    const roles = Array.isArray(candidate['roles'])
      ? candidate['roles'].filter((role): role is string => typeof role === 'string')
      : undefined;

    const permissions = this.parsePermissions(candidate['permissions']);

    return {
      ...candidate,
      id,
      canal_id,
      canal,
      roles,
      canal_context: canalContext,
      permissions
    } as AuthIdentity;
  }

  private parseCanalContext(value: unknown): AuthIdentity['canal_context'] {
    if (!value || typeof value !== 'object') {
      return null;
    }

    const source = value as Record<string, unknown>;
    const activeSource = source['active'];
    const active = this.parseCanalActive(activeSource);

    const isOwnerRaw = source['is_owner'];
    const is_owner = typeof isOwnerRaw === 'boolean' ? isOwnerRaw : false;

    return {
      active,
      is_owner
    };
  }

  private parseCanalActive(value: unknown): AuthCanalContextActive | null {
    if (!value || typeof value !== 'object') {
      return null;
    }

    const source = value as Record<string, unknown>;
    const idRaw = source['id'];
    const id =
      typeof idRaw === 'number'
        ? idRaw
        : typeof idRaw === 'string' && idRaw.trim() && !Number.isNaN(Number(idRaw))
          ? Number(idRaw)
          : null;

    const name = typeof source['name'] === 'string' ? source['name'].trim() : '';

    if (id === null || !name) {
      return null;
    }

    return { id, name };
  }

  private parsePermissions(value: unknown): Record<string, boolean> | undefined {
    if (!value || typeof value !== 'object' || Array.isArray(value)) {
      return undefined;
    }

    const source = value as Record<string, unknown>;
    const entries = Object.entries(source).filter(([, permission]) => typeof permission === 'boolean');
    if (entries.length === 0) {
      return undefined;
    }

    return Object.fromEntries(entries) as Record<string, boolean>;
  }
}
