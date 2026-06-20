import { HttpContextToken, HttpErrorResponse, HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { catchError, throwError } from 'rxjs';
import { ToastService } from '../services/toast.service';
import { resolveApiErrorMessage } from '../../shared/utils/api-error.utils';

export const SKIP_GLOBAL_API_ERROR_TOAST = new HttpContextToken<boolean>(() => false);

export const apiErrorInterceptor: HttpInterceptorFn = (req, next) => {
  if (req.context.get(SKIP_GLOBAL_API_ERROR_TOAST)) {
    return next(req);
  }

  const toast = inject(ToastService);

  return next(req).pipe(
    catchError((error: unknown) => {
      if (!(error instanceof HttpErrorResponse)) {
        return throwError(() => error);
      }

      switch (error.status) {
        case 401:
          toast.error('Používateľ nie je prihlásený.');
          break;
        case 403:
          toast.error('Nemáte oprávnenie alebo rolu na túto akciu.');
          break;
        case 422:
          toast.error(resolveApiErrorMessage(error, 'Validačná chyba payloadu.'));
          break;
      }

      return throwError(() => error);
    })
  );
};
