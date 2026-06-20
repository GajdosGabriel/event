import { HttpErrorResponse } from '@angular/common/http';
import { getApiErrorPayload, getHttpErrorCode, getHttpStatus, resolveApiErrorMessage } from './api-error.utils';

describe('api-error utils', () => {
  it('should resolve first field validation message from payload errors', () => {
    const error = new HttpErrorResponse({
      status: 422,
      error: {
        message: 'Validation failed.',
        errors: {
          start_at: ['Pole start at musi byt datum v buducnosti.']
        }
      }
    });

    expect(resolveApiErrorMessage(error, 'Fallback error')).toBe('Pole start at musi byt datum v buducnosti.');
  });

  it('should resolve payload.message when no field errors are available', () => {
    const error = new HttpErrorResponse({
      status: 422,
      error: {
        message: 'General validation error.'
      }
    });

    expect(resolveApiErrorMessage(error, 'Fallback error')).toBe('General validation error.');
  });

  it('should fallback when error payload has no usable message', () => {
    const error = new HttpErrorResponse({
      status: 500,
      error: {
        errors: {
          start_at: [null]
        }
      }
    });

    expect(resolveApiErrorMessage(error, 'Fallback error')).toBe('Fallback error');
  });

  it('should return http status and code when present', () => {
    const error = new HttpErrorResponse({
      status: 409,
      error: {
        code: 'already_verified',
        message: 'Already verified.'
      }
    });

    expect(getHttpStatus(error)).toBe(409);
    expect(getHttpErrorCode(error)).toBe('already_verified');
  });

  it('should expose payload for nested and plain error objects', () => {
    expect(getApiErrorPayload({ error: { message: 'Nested' } })).toEqual({ message: 'Nested' });
    expect(getApiErrorPayload({ message: 'Plain' })).toEqual({ message: 'Plain' });
  });
});
