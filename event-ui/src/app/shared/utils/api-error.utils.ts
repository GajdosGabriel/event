interface ApiErrorPayload {
  message?: unknown;
  errors?: Record<string, unknown>;
  code?: unknown;
}

export function getApiErrorPayload(error: unknown): ApiErrorPayload | undefined {
  if (!error || typeof error !== 'object') {
    return undefined;
  }

  if ('error' in error) {
    const nested = (error as { error?: unknown }).error;
    if (nested && typeof nested === 'object') {
      return nested as ApiErrorPayload;
    }
  }

  return error as ApiErrorPayload;
}

export function resolveApiErrorMessage(error: unknown, fallback: string): string {
  if (typeof error === 'string' && error.trim()) {
    return error.trim();
  }

  const payload = getApiErrorPayload(error);
  if (!payload) {
    return fallback;
  }

  const fieldMessage = extractFirstFieldErrorMessage(payload.errors);
  if (fieldMessage) {
    return fieldMessage;
  }

  if (typeof payload.message === 'string' && payload.message.trim()) {
    return payload.message.trim();
  }

  return fallback;
}

export function getHttpStatus(error: unknown): number | null {
  if (error && typeof error === 'object' && 'status' in error) {
    const status = (error as { status?: unknown }).status;
    return typeof status === 'number' ? status : null;
  }

  return null;
}

export function getHttpErrorCode(error: unknown): string | null {
  const payload = getApiErrorPayload(error);
  if (!payload || !('code' in payload)) {
    return null;
  }

  const code = payload.code;
  return typeof code === 'string' ? code : null;
}

function extractFirstFieldErrorMessage(errors: Record<string, unknown> | undefined): string {
  if (!errors || typeof errors !== 'object') {
    return '';
  }

  for (const rawMessages of Object.values(errors)) {
    if (Array.isArray(rawMessages)) {
      const firstText = rawMessages.find((item) => typeof item === 'string' && item.trim().length > 0);
      if (typeof firstText === 'string') {
        return firstText.trim();
      }
      continue;
    }

    if (typeof rawMessages === 'string' && rawMessages.trim()) {
      return rawMessages.trim();
    }
  }

  return '';
}
