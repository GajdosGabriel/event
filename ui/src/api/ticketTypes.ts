import http from './index'
import type { TicketTypeItem } from '@/types'

export function mapTicketType(raw: Record<string, unknown>): TicketTypeItem {
  return {
    id: (raw['id'] as number) ?? undefined,
    eventId: (raw['event_id'] as number) ?? undefined,
    name: raw['name'] as string,
    kind: (raw['kind'] as 'ticket' | 'workshop') ?? 'ticket',
    description: (raw['description'] as string) ?? null,
    startsAt: (raw['starts_at'] as string) ?? null,
    endsAt: (raw['ends_at'] as string) ?? null,
    priceAmount: (raw['price_amount'] as number) ?? null,
    priceCurrency: (raw['price_currency'] as string) ?? 'EUR',
    capacity: (raw['capacity'] as number) ?? null,
    maxPerOrder: (raw['max_per_order'] as number) ?? 10,
    minPerOrder: (raw['min_per_order'] as number) ?? 1,
    requiresAttendeeName: Boolean(raw['requires_attendee_name']),
    openToPublic: Boolean(raw['open_to_public']),
    saleStartsAt: (raw['sale_starts_at'] as string) ?? null,
    saleEndsAt: (raw['sale_ends_at'] as string) ?? null,
    isActive: Boolean(raw['is_active']),
    sortOrder: (raw['sort_order'] as number) ?? 0,
    soldCount: (raw['sold_count'] as number) ?? 0,
    remainingCapacity: (raw['remaining_capacity'] as number) ?? null,
    onSale: Boolean(raw['on_sale']),
    viewerJoined: Boolean(raw['viewer_joined']),
    viewerWaitlisted: Boolean(raw['viewer_waitlisted']),
    viewerWaitlistPosition: (raw['viewer_waitlist_position'] as number) ?? null,
    waitlistCount: (raw['waitlist_count'] as number) ?? 0,
    createdAt: (raw['created_at'] as string) ?? undefined,
  }
}

export interface TicketTypePayload {
  name: string
  kind?: 'ticket' | 'workshop'
  description?: string | null
  starts_at?: string | null
  ends_at?: string | null
  price_amount?: number | null
  price_currency?: string
  capacity?: number | null
  max_per_order?: number
  min_per_order?: number
  requires_attendee_name?: boolean
  open_to_public?: boolean
  sale_starts_at?: string | null
  sale_ends_at?: string | null
  is_active?: boolean
  sort_order?: number
}

/** Verejný zoznam aktívnych typov lístkov (registračný formulár). */
export async function publicTicketTypes(
  eventId: number,
): Promise<{ types: TicketTypeItem[]; viewerRegistered: boolean; workshopChangesLocked: boolean }> {
  const { data } = await http.get(`/events/${eventId}/ticket-types`)
  const items = (data.data ?? data) as Record<string, unknown>[]
  return {
    types: items.map(mapTicketType),
    viewerRegistered: Boolean(data.meta?.viewer_registered),
    workshopChangesLocked: Boolean(data.meta?.workshop_changes_locked),
  }
}

/** Prihlásenie prihláseného používateľa na workshop (jeden klik). */
export async function joinWorkshop(eventId: number, typeId: number): Promise<void> {
  await http.post(`/events/${eventId}/workshops/${typeId}`)
}

/** Odhlásenie z workshopu. */
export async function leaveWorkshop(eventId: number, typeId: number): Promise<void> {
  await http.delete(`/events/${eventId}/workshops/${typeId}`)
}

/** Zoznam typov lístkov v dashboarde (vrátane neaktívnych). */
export async function indexTicketTypes(eventId: number): Promise<TicketTypeItem[]> {
  const { data } = await http.get(`/dashboard/events/${eventId}/ticket-types`)
  const items = (data.data ?? data) as Record<string, unknown>[]
  return items.map(mapTicketType)
}

export async function createTicketType(eventId: number, payload: TicketTypePayload): Promise<TicketTypeItem> {
  const { data } = await http.post(`/dashboard/events/${eventId}/ticket-types`, payload)
  return mapTicketType((data.data ?? data) as Record<string, unknown>)
}

export async function updateTicketType(
  eventId: number,
  typeId: number,
  payload: Partial<TicketTypePayload>,
): Promise<TicketTypeItem> {
  const { data } = await http.put(`/dashboard/events/${eventId}/ticket-types/${typeId}`, payload)
  return mapTicketType((data.data ?? data) as Record<string, unknown>)
}

export async function deleteTicketType(eventId: number, typeId: number): Promise<void> {
  await http.delete(`/dashboard/events/${eventId}/ticket-types/${typeId}`)
}

export interface TicketingSettingsPayload {
  workshop_lock_on_start?: boolean
}

export async function updateTicketingSettings(
  eventId: number,
  payload: TicketingSettingsPayload,
): Promise<void> {
  await http.put(`/dashboard/events/${eventId}/ticketing`, payload)
}
