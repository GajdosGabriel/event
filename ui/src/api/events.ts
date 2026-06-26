import http from './index'
import type { EventItem, FilterParams, PaginatedResponse, MunicipalityOverviewItem } from '@/types'

type Scope = 'public' | 'dashboard' | 'admin'

function baseUrl(scope: Scope) {
  if (scope === 'public') return '/events'
  if (scope === 'admin') return '/admin/events'
  return '/dashboard/events'
}

function buildDateRangeLabel(startAt: string | null, endAt: string | null): string | null {
  if (!startAt) return null
  const fmt = (d: string) => new Date(d).toLocaleDateString('sk-SK', { day: 'numeric', month: 'numeric', year: 'numeric' })
  if (!endAt || startAt === endAt) return fmt(startAt)
  const start = new Date(startAt)
  const end = new Date(endAt)
  if (start.toDateString() === end.toDateString()) return fmt(startAt)
  return `${fmt(startAt)} – ${fmt(endAt)}`
}

function mapEvent(raw: Record<string, unknown>): EventItem {
  const canal = (raw['canal'] as { id: number; name: string } | null) ?? null
  const startAt = (raw['start_at'] as string) ?? null
  const endAt = (raw['end_at'] as string) ?? null
  const primaryImage = raw['primary_image'] as Record<string, string> | null
  return {
    id: raw['id'] as number,
    canalId: (raw['canal_id'] as number) ?? null,
    canalName: canal?.name ?? (raw['canal_name'] as string) ?? '',
    municipalityId: (raw['municipality_id'] as number) ?? null,
    venueId: (raw['venue_id'] as number) ?? null,
    name: raw['name'] as string,
    slug: (raw['slug'] as string) ?? '',
    body: (raw['body'] as string) ?? null,
    bodyAi: (raw['body_ai'] as string) ?? null,
    status: (raw['status'] as EventItem['status']) ?? 'draft',
    startAt,
    endAt,
    dateRangeLabel: (raw['date_range_label'] as string) ?? buildDateRangeLabel(startAt, endAt),
    registrationDeadlineAt: (raw['registration_deadline_at'] as string) ?? null,
    publishedAt: (raw['published_at'] as string) ?? null,
    deletedAt: (raw['deleted_at'] as string) ?? null,
    website: (raw['website'] as string) ?? null,
    locationName: (raw['location_name'] as string) ?? null,
    street: (raw['street'] as string) ?? null,
    postcode: (raw['postcode'] as string) ?? null,
    country: (raw['country'] as string) ?? null,
    latitude: (raw['latitude'] as number) ?? null,
    longitude: (raw['longitude'] as number) ?? null,
    imageUrl: (raw['image_url'] as string) ?? primaryImage?.['thumb'] ?? (raw['thumb_image'] as string) ?? null,
    uploadedFiles: (raw['uploaded_files'] as EventItem['uploadedFiles']) ?? [],
    phone: (raw['phone'] as string) ?? null,
    email: (raw['email'] as string) ?? null,
    permissions: (raw['permissions'] as EventItem['permissions']) ?? { view: true, update: false, delete: false, restore: false },
    allowedStatuses: (raw['allowed_statuses'] as EventItem['allowedStatuses']) ?? [],
    municipality: (raw['municipality'] as EventItem['municipality']) ?? null,
    canal,
    venue: (() => {
      const v = raw['venue'] as Record<string, unknown> | null
      if (!v) return null
      return {
        id: v['id'] as number,
        name: v['name'] as string,
        street: (v['street'] as string) ?? null,
        postcode: (v['postcode'] as string) ?? null,
        latitude: (v['latitude'] as string) ?? null,
        longitude: (v['longitude'] as string) ?? null,
        phone: (v['phone'] as string) ?? null,
        website: (v['website'] as string) ?? null,
        openingHours: (v['opening_hours'] as Record<string, string | null>) ?? null,
      }
    })(),
    uploadedImages: (() => {
      const files = raw['files'] as Record<string, unknown>[] | null
      if (!files?.length) {
        const pi = raw['primary_image'] as Record<string, string> | null
        if (pi?.['thumb']) return [{ thumb: pi['thumb'], large: pi['large'] ?? pi['thumb'], original: pi['original'] ?? pi['thumb'] }]
        return []
      }
      return files
        .filter((f) => (f['type'] as string) === 'image')
        .map((f) => ({
          thumb: (f['thumb_image_url'] as string) ?? (f['original_file_url'] as string) ?? '',
          large: (f['large_image_url'] as string) ?? (f['original_file_url'] as string) ?? '',
          original: (f['original_file_url'] as string) ?? '',
        }))
    })(),
  }
}

export async function showPublicEvent(id: number | string): Promise<EventItem> {
  const { data } = await http.get(`/events/${id}`)
  return mapEvent((data.data ?? data) as Record<string, unknown>)
}

export async function indexEvents(scope: Scope, params?: FilterParams & { page?: number }): Promise<PaginatedResponse<EventItem>> {
  const { data } = await http.get(baseUrl(scope), { params })
  const items = (data.data ?? data) as Record<string, unknown>[]
  return {
    data: items.map(mapEvent),
    meta: data.meta ?? { current_page: 1, last_page: 1, per_page: 15, total: items.length },
  }
}

export async function showEvent(scope: Exclude<Scope, 'public'>, id: number): Promise<EventItem> {
  const { data } = await http.get(`${baseUrl(scope)}/${id}`)
  return mapEvent((data.data ?? data) as Record<string, unknown>)
}

export async function createEvent(payload: FormData | Record<string, unknown>): Promise<EventItem> {
  const { data } = await http.post(baseUrl('dashboard'), payload)
  return mapEvent((data.data ?? data) as Record<string, unknown>)
}

export async function updateEvent(id: number, payload: FormData | Record<string, unknown>): Promise<EventItem> {
  const isForm = payload instanceof FormData
  if (isForm) payload.append('_method', 'PUT')
  const { data } = isForm
    ? await http.post(`${baseUrl('dashboard')}/${id}`, payload)
    : await http.put(`${baseUrl('dashboard')}/${id}`, payload)
  return mapEvent((data.data ?? data) as Record<string, unknown>)
}

export async function deleteEvent(id: number): Promise<void> {
  await http.delete(`${baseUrl('dashboard')}/${id}`)
}

export async function restoreEvent(id: number): Promise<void> {
  await http.post(`${baseUrl('dashboard')}/${id}/restore`)
}

export async function publishEvent(id: number, published: boolean): Promise<void> {
  await http.post(`${baseUrl('dashboard')}/${id}/publish`, { published })
}

export async function detectEventFromText(text: string): Promise<Record<string, unknown>> {
  const { data } = await http.post('/dashboard/events/detect-from-text', { text })
  return data as Record<string, unknown>
}

export type ImproveMode = 'grammar' | 'style' | 'expand' | 'html'

export async function improveEventText(scope: 'dashboard' | 'admin', text: string, modes: ImproveMode[]): Promise<{ success: boolean; improved_text?: string; changes_summary?: string; error?: string }> {
  const url = scope === 'admin' ? '/admin/events/improve-text' : '/dashboard/events/improve-text'
  const { data } = await http.post(url, { text, modes })
  return data as { success: boolean; improved_text?: string; changes_summary?: string; error?: string }
}

export async function runAdminTool(tool: 'import-events' | 'ai-detector' | 'archive-events', options?: Record<string, unknown>): Promise<{ success: boolean; output: string }> {
  const { data } = await http.post(`/admin/tools/${tool}`, options ?? {})
  return data as { success: boolean; output: string }
}

export async function municipalitiesOverview(scope: Scope): Promise<MunicipalityOverviewItem[]> {
  const url = scope === 'public'
    ? '/events/municipalities-overview'
    : `/${scope === 'admin' ? 'admin' : 'dashboard'}/events/municipalities-overview`
  const { data } = await http.get(url)
  return (data.data ?? data) as MunicipalityOverviewItem[]
}
