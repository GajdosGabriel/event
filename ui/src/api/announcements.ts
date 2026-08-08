import http from './index'
import type { PaginatedResponse } from '@/types'

export type AnnouncementPlacement = 'top' | 'bottom'

export interface AnnouncementOption {
  value: string
  label: string
}

export interface AnnouncementItem {
  id: number
  placement: AnnouncementPlacement
  title: string
  body: string | null
  variant: string
  sortOrder: number
  /** Tvar `YYYY-MM-DDTHH:mm` — priamo do `<input type="datetime-local">`. */
  publishedFrom: string | null
  publishedUntil: string | null
  status: string
  statusLabel: string
}

/** Číselníky pre formulár chodia v `meta` každej odpovede admin endpointu. */
export interface AnnouncementFormOptions {
  statuses: AnnouncementOption[]
  placements: AnnouncementOption[]
  variants: AnnouncementOption[]
}

export interface AnnouncementPayload {
  placement: AnnouncementPlacement
  title: string
  body: string | null
  variant: string
  sort_order: number
  published_from: string | null
  published_until: string | null
  status: string
}

const ADMIN_URL = '/admin/announcements'

function mapAnnouncement(raw: Record<string, unknown>): AnnouncementItem {
  return {
    id: raw['id'] as number,
    placement: raw['placement'] as AnnouncementPlacement,
    title: raw['title'] as string,
    body: (raw['body'] as string | null) ?? null,
    variant: (raw['variant'] as string) ?? 'blue',
    sortOrder: (raw['sort_order'] as number) ?? 0,
    publishedFrom: (raw['published_from'] as string | null) ?? null,
    publishedUntil: (raw['published_until'] as string | null) ?? null,
    status: raw['status'] as string,
    statusLabel: (raw['status_label'] as string) ?? '',
  }
}

function mapFormOptions(meta: Record<string, unknown> | undefined): AnnouncementFormOptions {
  return {
    statuses: (meta?.['allowed_statuses'] as AnnouncementOption[]) ?? [],
    placements: (meta?.['placements'] as AnnouncementOption[]) ?? [],
    variants: (meta?.['variants'] as AnnouncementOption[]) ?? [],
  }
}

/**
 * Aktívne oznamy pre verejný layout. Bez prihlásenia a bez stránkovania —
 * vracia len to, čo sa práve zobrazuje.
 */
export async function listActiveAnnouncements(placement?: AnnouncementPlacement): Promise<AnnouncementItem[]> {
  const { data } = await http.get('/announcements', { params: placement ? { placement } : undefined })
  return ((data.data ?? data) as Record<string, unknown>[]).map(mapAnnouncement)
}

export async function indexAnnouncements(
  params?: Record<string, unknown>,
): Promise<PaginatedResponse<AnnouncementItem> & { options: AnnouncementFormOptions }> {
  const { data } = await http.get(ADMIN_URL, { params })
  const items = ((data.data ?? data) as Record<string, unknown>[]).map(mapAnnouncement)

  return {
    data: items,
    meta: data.meta ?? { current_page: 1, last_page: 1, per_page: items.length, total: items.length },
    options: mapFormOptions(data.meta),
  }
}

export async function createAnnouncement(payload: AnnouncementPayload): Promise<AnnouncementItem> {
  const { data } = await http.post(ADMIN_URL, payload)
  return mapAnnouncement((data.data ?? data) as Record<string, unknown>)
}

export async function updateAnnouncement(id: number, payload: AnnouncementPayload): Promise<AnnouncementItem> {
  const { data } = await http.put(`${ADMIN_URL}/${id}`, payload)
  return mapAnnouncement((data.data ?? data) as Record<string, unknown>)
}

export async function deleteAnnouncement(id: number): Promise<void> {
  await http.delete(`${ADMIN_URL}/${id}`)
}

/** Payload z položky zoznamu — pre rýchle prepnutie stavu bez otvorenia formulára. */
export function toPayload(item: AnnouncementItem, overrides: Partial<AnnouncementPayload> = {}): AnnouncementPayload {
  return {
    placement: item.placement,
    title: item.title,
    body: item.body,
    variant: item.variant,
    sort_order: item.sortOrder,
    published_from: item.publishedFrom || null,
    published_until: item.publishedUntil || null,
    status: item.status,
    ...overrides,
  }
}
