import http, { BASE_URL } from './index'
import { mapEvent } from './events'
import type {
  AdmissionItem,
  AttendeeSummary,
  CheckinStats,
  PaginatedResponse,
  TicketCheckinResult,
  TicketItem,
} from '@/types'

export function mapAdmission(raw: Record<string, unknown>): AdmissionItem {
  const checkedInBy = raw['checked_in_by'] as { id: number; name?: string } | null
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
    confirmationStatus: (raw['confirmation_status'] as AdmissionItem['confirmationStatus']) ?? null,
    confirmationStatusLabel: (raw['confirmation_status_label'] as string) ?? null,
    confirmationDeadlineAt: (raw['confirmation_deadline_at'] as string) ?? null,
    isCheckedIn: Boolean(raw['is_checked_in']),
    checkedInAt: (raw['checked_in_at'] as string) ?? null,
    checkedInBy: checkedInBy ?? null,
    qrUrl: admissionQrImageUrl(raw['uuid'] as string),
    ticketType: ticketType ?? null,
    holderName: (raw['holder_name'] as string) ?? null,
    event: event ?? null,
  }
}

export function mapTicket(raw: Record<string, unknown>): TicketItem {
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
    // Cez mapEvent, nie pretypovaním: raw prichádza v snake_case, takže
    // priame pretypovanie nechalo `dateRangeLabel` a ďalšie camelCase polia
    // prázdne — na detaile vstupenky tak chýbal termín podujatia.
    event: raw['event'] ? mapEvent(raw['event'] as Record<string, unknown>) : undefined,
    permissions: permissions
      ? { update: Boolean(permissions['update']), checkin: Boolean(permissions['checkin']) }
      : undefined,
  }
}

export interface TicketRequestItem {
  ticket_type_id: number
  quantity: number
  attendees?: { name?: string | null; email?: string | null }[]
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

/** Samoobslužné zrušenie vlastnej registrácie prihláseného používateľa na podujatie. */
export async function cancelOwnRegistration(eventId: number): Promise<void> {
  await http.delete(`/events/${eventId}/registration`)
}

export async function showTicket(uuid: string): Promise<TicketItem> {
  const { data } = await http.get(`/tickets/${uuid}`)
  return mapTicket((data.data ?? data) as Record<string, unknown>)
}

export function admissionQrImageUrl(uuid: string): string {
  return `${BASE_URL}/admissions/${uuid}/qr`
}

export interface AttendeeFilters {
  search?: string
  status?: string
  payment?: string
  ticket_type_id?: number
  checkin?: string
  sort?: string
  page?: number
  per_page?: number
}

export async function indexEventTickets(
  eventId: number,
  params?: AttendeeFilters,
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

/**
 * `scannedAt` posiela skener pri prehrávaní offline fronty — bez neho by sa
 * všetkým, čo prišli počas výpadku, zapísal čas, keď sa vrátil signál.
 */
export async function checkinTicket(qrToken: string, scannedAt?: string): Promise<TicketCheckinResult> {
  const { data } = await http.post('/dashboard/tickets/checkin', {
    qr_token: qrToken,
    ...(scannedAt ? { scanned_at: scannedAt } : {}),
  })
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

/** Prehľad do bočného panela zoznamu prihlásených. */
export async function attendeeStats(eventId: number): Promise<AttendeeSummary> {
  const { data } = await http.get(`/dashboard/events/${eventId}/attendee-stats`)
  const payments = (data.payments ?? {}) as Record<string, unknown>

  return {
    admissions: data.admissions as AttendeeSummary['admissions'],
    orders: data.orders as AttendeeSummary['orders'],
    payments: {
      currency: (payments['currency'] as string) ?? 'EUR',
      paidAmount: Number(payments['paid_amount'] ?? 0),
      pendingAmount: Number(payments['pending_amount'] ?? 0),
      pendingCount: Number(payments['pending_count'] ?? 0),
    },
    types: (data.types ?? []) as AttendeeSummary['types'],
  }
}

export async function cancelTicket(id: number): Promise<TicketItem> {
  const { data } = await http.post(`/dashboard/tickets/${id}`)
  return mapTicket((data.data ?? data) as Record<string, unknown>)
}

export async function cancelAdmission(admissionId: number): Promise<AdmissionItem> {
  const { data } = await http.post(`/dashboard/admissions/${admissionId}/cancel`)
  return mapAdmission((data.data ?? data) as Record<string, unknown>)
}

/** Obnovenie zrušenej objednávky — objednávateľ dostane vstupenky e-mailom znova. */
export async function restoreTicket(id: number): Promise<TicketItem> {
  const { data } = await http.post(`/dashboard/tickets/${id}/restore`)
  return mapTicket((data.data ?? data) as Record<string, unknown>)
}

/** Zmazanie zrušenej objednávky zo zoznamu (bez e-mailu). */
export async function deleteTicket(id: number): Promise<void> {
  await http.delete(`/dashboard/tickets/${id}`)
}

/** Potvrdenie rezervácie organizátorom. */
export async function confirmTicket(id: number): Promise<TicketItem> {
  const { data } = await http.post(`/dashboard/tickets/${id}/confirm`)
  return mapTicket((data.data ?? data) as Record<string, unknown>)
}

/** Ručné označenie platby ako uhradenej. */
export async function markTicketPaid(id: number): Promise<TicketItem> {
  const { data } = await http.post(`/dashboard/tickets/${id}/paid`)
  return mapTicket((data.data ?? data) as Record<string, unknown>)
}

/** Obnovenie jednej zrušenej vstupenky. */
export async function restoreAdmission(admissionId: number): Promise<AdmissionItem> {
  const { data } = await http.post(`/dashboard/admissions/${admissionId}/restore`)
  return mapAdmission((data.data ?? data) as Record<string, unknown>)
}

/** Zmazanie jednej zrušenej vstupenky zo zoznamu (bez e-mailu). */
export async function deleteAdmission(admissionId: number): Promise<void> {
  await http.delete(`/dashboard/admissions/${admissionId}`)
}

export async function resendTicket(id: number): Promise<void> {
  await http.post(`/dashboard/tickets/${id}/resend`)
}

/**
 * CSV so zoznamom prihlásených. Sťahuje sa cez axios (blob), nie ako obyčajný
 * odkaz — inak by request išiel bez Authorization hlavičky a skončil na 401.
 */
export async function exportAttendees(eventId: number): Promise<void> {
  const response = await http.get(`/dashboard/events/${eventId}/attendees/export`, {
    responseType: 'blob',
  })

  const disposition = String(response.headers['content-disposition'] ?? '')
  const name = /filename="?([^";]+)"?/.exec(disposition)?.[1] ?? `ucastnici-${eventId}.csv`

  const url = URL.createObjectURL(response.data as Blob)
  const link = document.createElement('a')
  link.href = url
  link.download = name
  link.click()
  URL.revokeObjectURL(url)
}

export async function attendeeRecipientCount(eventId: number): Promise<number> {
  const { data } = await http.get(`/dashboard/events/${eventId}/attendees/recipients`)
  return Number(data.recipients ?? 0)
}

export async function emailAttendees(
  eventId: number,
  payload: { subject: string; body: string },
): Promise<number> {
  const { data } = await http.post(`/dashboard/events/${eventId}/attendees/email`, payload)
  return Number(data.recipients ?? 0)
}
