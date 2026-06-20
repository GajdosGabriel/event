import { Injectable, inject } from '@angular/core';
import { Router } from '@angular/router';
import { BehaviorSubject, EMPTY, Observable, catchError, finalize, of, tap } from 'rxjs';
import { AuthService } from './auth.service';
import { ToastService } from './toast.service';

@Injectable({ providedIn: 'root' })
export class AuthUiService {
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);
  private readonly toast = inject(ToastService);
  private readonly loggingOutSubject = new BehaviorSubject(false);

  readonly isLoggingOut$ = this.loggingOutSubject.asObservable();

  logoutAndNavigate(redirectTo = '/login'): Observable<void> {
    if (this.loggingOutSubject.value) {
      return EMPTY;
    }

    this.loggingOutSubject.next(true);

    return this.auth.logout().pipe(
      tap(() => this.toast.success('Boli ste odhlaseni.')),
      catchError(() => {
        this.toast.info('Boli ste odhlaseni lokalne. Odhlasenie na serveri zlyhalo.');
        return of(void 0);
      }),
      finalize(() => this.loggingOutSubject.next(false)),
      tap(() => {
        void this.router.navigate([redirectTo]);
      })
    );
  }
}
