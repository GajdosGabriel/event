import { HttpInterceptorFn } from '@angular/common/http';
import { API_BASE_URL } from '../../constants/api.constants';

const XSRF_COOKIE_NAME = 'XSRF-TOKEN';

export const apiAuthInterceptor: HttpInterceptorFn = (req, next) => {
  if (!isApiRequest(req.url)) {
    return next(req);
  }

  let modified = req.clone({
    withCredentials: true,
    setHeaders: {
      'Accept': 'application/json',
      'X-Requested-With': 'XMLHttpRequest'
    }
  });

  const xsrfToken = readCookie(XSRF_COOKIE_NAME);
  if (xsrfToken && !modified.headers.has('X-XSRF-TOKEN')) {
    modified = modified.clone({
      setHeaders: {
        'X-XSRF-TOKEN': decodeURIComponent(xsrfToken)
      }
    });
  }

  const bearerToken = readBearerToken();
  if (bearerToken && !modified.headers.has('Authorization')) {
    modified = modified.clone({
      setHeaders: {
        'Authorization': `Bearer ${bearerToken}`
      }
    });
  }

  return next(modified);
};

function isApiRequest(url: string): boolean {
  return url.startsWith(API_BASE_URL) || url.startsWith('/sanctum/');
}

function readCookie(name: string): string | null {
  if (typeof document === 'undefined') {
    return null;
  }

  const entries = document.cookie.split(';');
  for (const entry of entries) {
    const [key, ...valueParts] = entry.trim().split('=');
    if (key === name) {
      return valueParts.join('=');
    }
  }

  return null;
}

function readBearerToken(): string | null {
  if (typeof window === 'undefined') {
    return null;
  }

  return localStorage.getItem('auth_token');
}
