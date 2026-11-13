import http from './index'
import { mapCheckinResult } from './tickets'
import type { CheckinStats, TicketCheckinResult } from '@/types'

/**
 * Odkaz na skener pre dobrovoľníkov pri vchode (`/vstup/{token}`).
 * Organizátor ich spravuje v dashboarde, dobrovoľník ich otvára bez účtu.
 */
export interface CheckinLinkItem {
  id: number
  label: string | null
  url: string
  expiresAt: string
  lastUsedAt: string | null
  createdAt: string | null
}

/** Čo vidí dobrovoľník po otvorení odkazu. */
export interface CheckinLinkSession {
  label: string | null
  expiresAt: string
  event: { id: number; name: string; startAt: string | null; endAt: string | null; venue: string | null }
  hasTypes: boolean
  stats: CheckinStats
}

function mapLink(raw: Record<string, unknown>): CheckinLinkItem {
  return {
    id: raw['id'] as number,
    label: (raw['label'] as string) ?? null,
    url: raw['url'] as string,
    expiresAt: raw['expires_at'] as string,
    lastUsedAt: (raw['last_used_at'] as string) ?? null,
    createdAt: (raw['created_at'] as string) ?? null,
  }
}

export async function listCheckinLinks(eventId: number): Promise<CheckinLinkItem[]> {
  const { data } = await http.get(`/dashboard/events/${eventId}/checkin-links`)
  return ((data.data ?? []) as Record<string, unknown>[]).map(mapLink)
}

export async function createCheckinLink(eventId: number, label: string): Promise<CheckinLinkItem> {
  const { data } = await http.post(`/dashboard/events/${eventId}/checkin-links`, { label: label || null })
  return mapLink(data.data as Record<string, unknown>)
}

export async function revokeCheckinLink(eventId: number, linkId: number): Promise<void> {
  await http.delete(`/dashboard/events/${eventId}/checkin-links/${linkId}`)
}

export async function openCheckinLink(token: string): Promise<CheckinLinkSession> {
  const { data } = await http.get(`/checkin/${encodeURIComponent(token)}`)
  const event = data.event as Record<string, unknown>

  return {
    label: (data.label as string) ?? null,
    expiresAt: data.expires_at as string,
    event: {
      id: event['id'] as number,
      name: event['name'] as string,
      startAt: (event['start_at'] as string) ?? null,
      endAt: (event['end_at'] as string) ?? null,
      venue: (event['venue'] as string) ?? null,
    },
    hasTypes: Boolean(data.has_types),
    stats: data.stats as CheckinStats,
  }
}

export async function scanViaLink(token: string, qrToken: string, scannedAt?: string): Promise<TicketCheckinResult> {
  const { data } = await http.post(`/checkin/${encodeURIComponent(token)}/scan`, {
    qr_token: qrToken,
    ...(scannedAt ? { scanned_at: scannedAt } : {}),
  })
  return mapCheckinResult(data)
}

export async function checkinLinkStats(token: string): Promise<CheckinStats> {
  const { data } = await http.get(`/checkin/${encodeURIComponent(token)}/stats`)
  return data as CheckinStats
}
