import { t, type MessageKey } from '@/i18n'

/**
 * Popisky stavov (App\Enums\ModelStatus) pre badge na detailoch.
 *
 * Preklady sú per-model, lebo rod sa v slovenčine aj češtine líši („publikovaná
 * organizácia“ vs. „publikovaný kanál“) — jeden spoločný zoznam by v polovici
 * prípadov znel zle.
 *
 * Chýba `pending_review` a `rejected`: moderačný workflow neexistuje (viď
 * ModelStatus::allowedForUser), takže by to boli kľúče bez použitia. Keby taký
 * stav predsa prišiel z historického riadku, statusLabel() vráti surovú hodnotu.
 */
const KEYS = {
  events: {
    draft: 'events.statuses.draft',
    scheduled: 'events.statuses.scheduled',
    published: 'events.statuses.published',
    archived: 'events.statuses.archived',
    blocked: 'events.statuses.blocked',
  },
  venues: {
    draft: 'venues.statuses.draft',
    published: 'venues.statuses.published',
    archived: 'venues.statuses.archived',
    blocked: 'venues.statuses.blocked',
  },
  canals: {
    draft: 'canals.statuses.draft',
    published: 'canals.statuses.published',
    archived: 'canals.statuses.archived',
    blocked: 'canals.statuses.blocked',
  },
  organizations: {
    draft: 'organizations.statuses.draft',
    published: 'organizations.statuses.published',
    archived: 'organizations.statuses.archived',
    blocked: 'organizations.statuses.blocked',
  },
} satisfies Record<string, Record<string, MessageKey>>

export type StatusModel = keyof typeof KEYS

export function statusLabel(model: StatusModel, status: string | null | undefined): string {
  if (!status) return ''

  const key = (KEYS[model] as Record<string, MessageKey>)[status]

  return key ? t(key) : status
}
