import http, { BASE_URL } from './index'
import type { EventItem, PaginatedResponse, TicketCheckinResult, TicketItem } from '@/types'

function mapTicket(raw: Record<string, unknown>): TicketItem {
  const checkedInBy = raw['checked_in_by'] as { id: number } | null
  const permissions = raw['permissions'] as Record<string, boolean> | undefined

  return {
    id: (raw['id'] as number) ?? undefined,
    uuid: raw['uuid'] as string,
    eventId: (raw['event_id'] as number) ?? undefined,
    holderName: raw['holder_name'] as string,
    quantity: (raw['quantity'] as number) ?? 1,
    holderEmail: (raw['holder_email'] as string) ?? undefined,
    holderPhone: (raw['holder_phone'] as string) ?? null,
    status: raw['status'] as TicketItem['status'],
    statusLabel: (raw['status_label'] as string) ?? '',
    paymentStatus: raw['payment_status'] as TicketItem['paymentStatus'],
    paymentStatusLabel: (raw['payment_status_label'] as string) ?? '',
    priceAmount: (raw['price_amount'] as number) ?? null,
    priceCurrency: (raw['price_currency'] as string) ?? null,
    isCheckedIn: Boolean(raw['is_checked_in']),
    checkedInAt: (raw['checked_in_at'] as string) ?? null,
    checkedInBy: checkedInBy ?? null,
    createdAt: raw['created_at'] as string,
    deletedAt: (raw['deleted_at'] as string) ?? null,
    event: raw['event'] ? (raw['event'] as unknown as EventItem) : undefined,
    permissions: permissions
      ? { update: Boolean(permissions['update']), checkin: Boolean(permissions['checkin']) }
      : undefined,
  }
}

export interface TicketRequestPayload {
  holder_name?: string
  holder_email?: string
  holder_phone?: string
  quantity?: number
}

export async function requestTicket(eventId: number, payload: TicketRequestPayload): Promise<TicketItem> {
  const { data } = await http.post(`/events/${eventId}/tickets`, payload)
  return mapTicket((data.data ?? data) as Record<string, unknown>)
}

export async function showTicket(uuid: string): Promise<TicketItem> {
  const { data } = await http.get(`/tickets/${uuid}`)
  return mapTicket((data.data ?? data) as Record<string, unknown>)
}

export function ticketQrImageUrl(uuid: string): string {
  return `${BASE_URL}/tickets/${uuid}/qr`
}

export async function indexEventTickets(
  eventId: number,
  params?: { search?: string; page?: number; per_page?: number },
): Promise<PaginatedResponse<TicketItem>> {
  const { data } = await http.get(`/dashboard/events/${eventId}/tickets`, { params })
  const items = (data.data ?? data) as Record<string, unknown>[]
  return {
    data: items.map(mapTicket),
    meta: data.meta ?? { current_page: 1, last_page: 1, per_page: 15, total: items.length },
  }
}

export async function checkinTicket(qrToken: string): Promise<TicketCheckinResult> {
  const { data } = await http.post('/dashboard/tickets/checkin', { qr_token: qrToken })
  return {
    status: data.status,
    reason: data.reason ?? null,
    ticket: data.ticket ? mapTicket(data.ticket as Record<string, unknown>) : null,
  }
}

export async function cancelTicket(id: number): Promise<TicketItem> {
  const { data } = await http.post(`/dashboard/tickets/${id}`)
  return mapTicket((data.data ?? data) as Record<string, unknown>)
}
