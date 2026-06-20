import { AbstractControl, FormGroup } from '@angular/forms';

export type ServerFieldMap<TControlName extends string> = Partial<Record<string, TControlName[]>>;

interface ServerErrorPayload {
  message?: unknown;
  errors?: Record<string, unknown>;
}

interface ApplyServerErrorsParams<TControlName extends string> {
  form: FormGroup;
  payload: unknown;
  fieldMap: ServerFieldMap<TControlName>;
  fallbackMessage: string;
}

export interface ApplyServerErrorsResult {
  summary: string[];
  mappedAny: boolean;
}

export function clearServerValidationErrors(form: FormGroup): void {
  for (const control of Object.values(form.controls)) {
    const errors = control.errors;
    if (!errors || !errors['server']) {
      continue;
    }

    const nextErrors = { ...errors };
    delete nextErrors['server'];
    control.setErrors(Object.keys(nextErrors).length > 0 ? nextErrors : null);
  }
}

export function applyServerValidationErrors<TControlName extends string>(
  params: ApplyServerErrorsParams<TControlName>
): ApplyServerErrorsResult {
  const { form, payload, fieldMap, fallbackMessage } = params;
  const summarySet = new Set<string>();
  let mappedAny = false;

  const serverPayload = payload as ServerErrorPayload | undefined;
  if (serverPayload && typeof serverPayload === 'object') {
    if (typeof serverPayload.message === 'string' && serverPayload.message.trim()) {
      summarySet.add(serverPayload.message.trim());
    }

    if (serverPayload.errors && typeof serverPayload.errors === 'object') {
      for (const [apiField, rawMessages] of Object.entries(serverPayload.errors)) {
        const message = extractFirstErrorMessage(rawMessages);
        if (!message) {
          continue;
        }

        const normalizedField = normalizeApiFieldName(apiField);
        const targets = fieldMap[normalizedField];

        if (targets && targets.length > 0) {
          for (const controlName of targets) {
            const control = getControl(form, controlName);
            if (!control) {
              summarySet.add(message);
              continue;
            }

            control.setErrors({
              ...(control.errors ?? {}),
              server: message
            });
            control.markAsTouched();
          }
          mappedAny = true;
        } else {
          summarySet.add(message);
        }
      }
    }
  }

  if (summarySet.size === 0) {
    summarySet.add(fallbackMessage);
  }

  return {
    summary: [...summarySet],
    mappedAny
  };
}

function getControl(form: FormGroup, controlName: string): AbstractControl | undefined {
  return (form.controls as Record<string, AbstractControl | undefined>)[controlName];
}

function normalizeApiFieldName(value: string): string {
  const normalized = value.trim().toLowerCase();
  if (!normalized) {
    return normalized;
  }

  return normalized.replace(/[^a-z0-9]+/g, '_').replace(/_+/g, '_').replace(/^_|_$/g, '');
}

function extractFirstErrorMessage(rawMessages: unknown): string {
  if (Array.isArray(rawMessages)) {
    const firstText = rawMessages.find((item) => typeof item === 'string' && item.trim().length > 0);
    return typeof firstText === 'string' ? firstText.trim() : '';
  }

  if (typeof rawMessages === 'string') {
    return rawMessages.trim();
  }

  return '';
}
