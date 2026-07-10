import http, { BASE_URL } from './index'
import type {
  AdmissionItem,
  CheckinStats,
  EventItem,
  PaginatedResponse,
  TicketCheckinResult,
  TicketItem,
} from '@/types'

export function mapAdmission(raw: Record<string, unknown>): AdmissionItem {
  const checkedInBy = raw['checked_in_by'] as { id: number } | null
  const rawType = raw['ticket_type'] as Record<string, unknown> | null
  const ticketType: AdmissionItem['ticketType'] = rawType
    ? {
        id: rawType['id'] as number,
        name: rawType['name'] as string,
        kind: (rawType['kind'] as 'ticket' | 'workshop') ?? 'ticket',
        startsAt: (rawType['starts_at'] as string) ?? null,
      }
    : null
  const event = raw['event'] as { id: number; name: string } | null

  return {
    id: (raw['id'] as number) ?? undefined,
    uuid: raw['uuid'] as string,
    ticketId: (raw['ticket_id'] as number) ?? undefined,
    eventId: (raw['event_id'] as number) ?? undefined,
    attendeeName: (raw['attendee_name'] as string) ?? null,
    status: raw['status'] as AdmissionItem['status'],
    statusLabel: (raw['status_label'] as string) ?? '',
    isCheckedIn: Boolean(raw['is_checked_in']),
    checkedInAt: (raw['checked_in_at'] as string) ?? null,
    checkedInBy: checkedInBy ?? null,
    qrUrl: admissionQrImageUrl(raw['uuid'] as string),
    ticketType: ticketType ?? null,
    holderName: (raw['holder_name'] as string) ?? null,
    event: event ?? null,
  }
}

function mapTicket(raw: Record<string, unknown>): TicketItem {
  const permissions = raw['permissions'] as Record<string, boolean> | undefined
  const admissions = (raw['admissions'] as Record<string, unknown>[] | undefined) ?? []

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
    checkedInCount: (raw['checked_in_count'] as number) ?? 0,
    admissionsTotal: (raw['admissions_total'] as number) ?? admissions.length,
    admissions: admissions.map(mapAdmission),
    createdAt: raw['created_at'] as string,
    deletedAt: (raw['deleted_at'] as string) ?? null,
    event: raw['event'] ? (raw['event'] as unknown as EventItem) : undefined,
    permissions: permissions
      ? { update: Boolean(permissions['update']), checkin: Boolean(permissions['checkin']) }
      : undefined,
  }
}

export interface TicketRequestItem {
  ticket_type_id: number
  quantity: number
  attendees?: { name?: string | null }[]
}

export interface TicketRequestPayload {
  holder_name?: string
  holder_email?: string
  holder_phone?: string
  items?: TicketRequestItem[]
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

export function admissionQrImageUrl(uuid: string): string {
  return `${BASE_URL}/admissions/${uuid}/qr`
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

function mapCheckinResult(data: Record<string, unknown>): TicketCheckinResult {
  return {
    status: data['status'] as TicketCheckinResult['status'],
    reason: (data['reason'] as TicketCheckinResult['reason']) ?? null,
    admission: data['admission'] ? mapAdmission(data['admission'] as Record<string, unknown>) : null,
  }
}

export async function checkinTicket(qrToken: string): Promise<TicketCheckinResult> {
  const { data } = await http.post('/dashboard/tickets/checkin', { qr_token: qrToken })
  return mapCheckinResult(data)
}

export async function checkinAdmissionManual(admissionId: number): Promise<TicketCheckinResult> {
  const { data } = await http.post('/dashboard/tickets/checkin/manual', { admission_id: admissionId })
  return mapCheckinResult(data)
}

export async function undoCheckin(admissionId: number): Promise<TicketCheckinResult> {
  const { data } = await http.post('/dashboard/tickets/checkin/undo', { admission_id: admissionId })
  return mapCheckinResult(data)
}

export async function checkinStats(eventId: number): Promise<CheckinStats> {
  const { data } = await http.get(`/dashboard/events/${eventId}/checkin-stats`)
  return data as CheckinStats
}

export async function cancelTicket(id: number): Promise<TicketItem> {
  const { data } = await http.post(`/dashboard/tickets/${id}`)
  return mapTicket((data.data ?? data) as Record<string, unknown>)
}

export async function cancelAdmission(admissionId: number): Promise<AdmissionItem> {
  const { data } = await http.post(`/dashboard/admissions/${admissionId}/cancel`)
  return mapAdmission((data.data ?? data) as Record<string, unknown>)
}

export async function resendTicket(id: number): Promise<void> {
  await http.post(`/dashboard/tickets/${id}/resend`)
}
