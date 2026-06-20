import {
  ApplicationConfig,
  inject,
  provideAppInitializer,
  provideBrowserGlobalErrorListeners
} from '@angular/core';
import { provideHttpClient, withFetch, withInterceptors } from '@angular/common/http';
import { provideRouter } from '@angular/router';

import { routes } from './app.routes';
import { provideClientHydration, withEventReplay } from '@angular/platform-browser';
import { apiAuthInterceptor } from './core/interceptors/api-auth.interceptor';
import { apiErrorInterceptor } from './core/interceptors/api-error.interceptor';
import { AuthService } from './core/services/auth.service';
import { of } from 'rxjs';

export const appConfig: ApplicationConfig = {
  providers: [
    provideBrowserGlobalErrorListeners(),
    provideRouter(routes),
    provideHttpClient(withFetch(), withInterceptors([apiAuthInterceptor, apiErrorInterceptor])),
    provideClientHydration(withEventReplay()),
    provideAppInitializer(() => {
      inject(AuthService);
      return of(null);
    })
  ]
};
