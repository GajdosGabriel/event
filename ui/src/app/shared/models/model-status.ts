export const MODEL_STATUS = {
  Draft: 'draft',
  PendingReview: 'pending_review',
  Rejected: 'rejected',
  Scheduled: 'scheduled',
  Published: 'published',
  Archived: 'archived',
  Blocked: 'blocked'
} as const;

export type ModelStatus = (typeof MODEL_STATUS)[keyof typeof MODEL_STATUS];

const MODEL_STATUS_VALUES = Object.values(MODEL_STATUS) as ModelStatus[];

const MODEL_STATUS_LABELS: Record<ModelStatus, string> = {
  [MODEL_STATUS.Draft]: 'Koncept',
  [MODEL_STATUS.PendingReview]: 'Čaká na schválenie',
  [MODEL_STATUS.Rejected]: 'Zamietnuté',
  [MODEL_STATUS.Scheduled]: 'Naplánované',
  [MODEL_STATUS.Published]: 'Publikované',
  [MODEL_STATUS.Archived]: 'Archivované',
  [MODEL_STATUS.Blocked]: 'Blokované'
};

export const MODEL_STATUS_OPTIONS = MODEL_STATUS_VALUES.map((value) => ({
  id: value,
  name: MODEL_STATUS_LABELS[value]
}));

export interface AllowedStatusOption {
  id: string;
  name: string;
}

export function sanitizeAllowedStatuses(raw: unknown): AllowedStatusOption[] {
  if (!Array.isArray(raw)) {
    return [];
  }
  return raw
    .filter(
      (item): item is { value: string; label: string } =>
        typeof item === 'object' &&
        item !== null &&
        typeof item['value'] === 'string' &&
        typeof item['label'] === 'string'
    )
    .map((item) => ({ id: item.value, name: item.label }));
}

export function sanitizeModelStatus(value: unknown): ModelStatus {
  if (typeof value === 'string' && MODEL_STATUS_VALUES.includes(value as ModelStatus)) {
    return value as ModelStatus;
  }

  return MODEL_STATUS.Draft;
}

export function getModelStatusLabel(value: unknown): string {
  return MODEL_STATUS_LABELS[sanitizeModelStatus(value)];
}

export function isPublishedModelStatus(value: unknown): boolean {
  return sanitizeModelStatus(value) === MODEL_STATUS.Published;
}

export function togglePublishedModelStatus(value: unknown): ModelStatus {
  return isPublishedModelStatus(value) ? MODEL_STATUS.Draft : MODEL_STATUS.Published;
}
