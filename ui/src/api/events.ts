import http from './index'
import type { EventItem, FilterParams, PaginatedResponse, MunicipalityOverviewItem } from '@/types'

type Scope = 'public' | 'dashboard' | 'admin'

function baseUrl(scope: Scope) {
  if (scope === 'public') return '/events'
  if (scope === 'admin') return '/admin/events'
  return '/dashboard/events'
}

function mapEvent(raw: Record<string, unknown>): EventItem {
  return {
    id: raw['id'] as number,
    canalId: (raw['canal_id'] as number) ?? null,
    canalName: (raw['canal_name'] as string) ?? '',
    municipalityId: (raw['municipality_id'] as number) ?? null,
    venueId: (raw['venue_id'] as number) ?? null,
    name: raw['name'] as string,
    slug: (raw['slug'] as string) ?? '',
    body: (raw['body'] as string) ?? null,
    status: (raw['status'] as EventItem['status']) ?? 'draft',
    startAt: (raw['start_at'] as string) ?? null,
    endAt: (raw['end_at'] as string) ?? null,
    dateRangeLabel: (raw['date_range_label'] as string) ?? null,
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
    imageUrl: (raw['image_url'] as string) ?? (raw['thumb'] as string) ?? null,
    uploadedFiles: (raw['uploaded_files'] as EventItem['uploadedFiles']) ?? [],
    permissions: (raw['permissions'] as EventItem['permissions']) ?? { view: true, update: false, delete: false, restore: false },
    allowedStatuses: (raw['allowed_statuses'] as EventItem['allowedStatuses']) ?? [],
    municipality: (raw['municipality'] as EventItem['municipality']) ?? null,
    canal: (raw['canal'] as EventItem['canal']) ?? null,
    venue: (raw['venue'] as EventItem['venue']) ?? null,
  }
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

export async function municipalitiesOverview(scope: Scope): Promise<MunicipalityOverviewItem[]> {
  const url = scope === 'public'
    ? '/events/municipalities-overview'
    : `/${scope === 'admin' ? 'admin' : 'dashboard'}/events/municipalities-overview`
  const { data } = await http.get(url)
  return (data.data ?? data) as MunicipalityOverviewItem[]
}
