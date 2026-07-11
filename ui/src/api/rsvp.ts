import http from './index'
import type { RsvpInfo } from '@/types'

function mapRsvp(raw: Record<string, unknown>): RsvpInfo {
  const event = raw['event'] as Record<string, unknown> | null
  const seats = (raw['seats'] as Record<string, unknown>[] | undefined) ?? []
  return {
    status: (raw['status'] as RsvpInfo['status']) ?? null,
    statusLabel: (raw['status_label'] as string) ?? null,
    attendeeName: (raw['attendee_name'] as string) ?? null,
    holderName: (raw['holder_name'] as string) ?? null,
    isPaid: Boolean(raw['is_paid']),
    deadlineAt: (raw['deadline_at'] as string) ?? null,
    event: event
      ? {
          id: event['id'] as number,
          name: event['name'] as string,
          dateRangeLabel: (event['date_range_label'] as string) ?? null,
        }
      : null,
    seats: seats.map((s) => ({
      label: (s['label'] as string) ?? 'Vstupenka',
      type: (s['type'] as string) ?? null,
    })),
  }
}

export async function showRsvp(token: string): Promise<RsvpInfo> {
  const { data } = await http.get(`/rsvp/${token}`)
  return mapRsvp((data.data ?? data) as Record<string, unknown>)
}

export async function confirmRsvp(token: string): Promise<RsvpInfo> {
  const { data } = await http.post(`/rsvp/${token}/confirm`)
  return mapRsvp((data.data ?? data) as Record<string, unknown>)
}

export async function declineRsvp(token: string): Promise<RsvpInfo> {
  const { data } = await http.post(`/rsvp/${token}/decline`)
  return mapRsvp((data.data ?? data) as Record<string, unknown>)
}
