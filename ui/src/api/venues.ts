import http from './index'
import type { VenueItem, FilterParams, PaginatedResponse, MunicipalityOverviewItem } from '@/types'

type Scope = 'dashboard' | 'admin'

function baseUrl(scope: Scope) {
  return scope === 'admin' ? '/admin/venues' : '/dashboard/venues'
}

function mapVenue(raw: Record<string, unknown>): VenueItem {
  return {
    id: raw['id'] as number,
    canalId: (raw['canal_id'] as number) ?? null,
    villageId: (raw['village_id'] as number) ?? null,
    name: raw['name'] as string,
    slug: (raw['slug'] as string) ?? '',
    street: (raw['street'] as string) ?? null,
    postcode: (raw['postcode'] as string) ?? null,
    body: (raw['body'] as string) ?? null,
    website: (raw['website'] as string) ?? null,
    email: (raw['email'] as string) ?? null,
    phone: (raw['phone'] as string) ?? null,
    country: (raw['country'] as string) ?? null,
    latitude: (raw['latitude'] as number) ?? null,
    longitude: (raw['longitude'] as number) ?? null,
    capacity: (raw['capacity'] as number) ?? null,
    openingHours: (raw['opening_hours'] as string) ?? null,
    category: (raw['category'] as string) ?? null,
    imageUrl: (raw['image_url'] as string) ?? ((raw['primary_image'] as Record<string,string>)?.['thumb']) ?? (raw['thumb_image'] as string) ?? null,
    status: (raw['status'] as VenueItem['status']) ?? 'draft',
    deletedAt: (raw['deleted_at'] as string) ?? null,
    createdAt: (raw['created_at'] as string) ?? '',
    updatedAt: (raw['updated_at'] as string) ?? '',
    uploadedFiles: (raw['uploaded_files'] as VenueItem['uploadedFiles']) ?? [],
    permissions: (raw['permissions'] as VenueItem['permissions']) ?? { view: true, update: false, delete: false, restore: false },
    allowedStatuses: (raw['allowed_statuses'] as VenueItem['allowedStatuses']) ?? [],
    municipality: raw['municipality'] ? { id: (raw['municipality'] as Record<string,unknown>)['id'] as number, name: (raw['municipality'] as Record<string,unknown>)['name'] as string } : null,
    canalsList: ((raw['canals_list'] as Record<string,unknown>[]) ?? []).map(c => ({ id: c['id'] as number, name: c['name'] as string, isOwner: c['is_owner'] as boolean })),
    contactable: Boolean(raw['contactable']),
  }
}

export async function indexVenues(scope: Scope, params?: FilterParams & { page?: number }): Promise<PaginatedResponse<VenueItem>> {
  const { data } = await http.get(baseUrl(scope), { params })
  const items = (data.data ?? data) as Record<string, unknown>[]
  return {
    data: items.map(mapVenue),
    meta: data.meta ?? { current_page: 1, last_page: 1, per_page: 15, total: items.length },
  }
}

export async function showVenue(scope: Scope, id: number): Promise<VenueItem> {
  const { data } = await http.get(`${baseUrl(scope)}/${id}`)
  return mapVenue((data.data ?? data) as Record<string, unknown>)
}

export async function showVenuePublic(id: number): Promise<VenueItem> {
  const { data } = await http.get(`/venues/${id}`)
  return mapVenue((data.data ?? data) as Record<string, unknown>)
}

export async function createVenue(payload: FormData | Record<string, unknown>): Promise<VenueItem> {
  const { data } = await http.post(baseUrl('dashboard'), payload)
  return mapVenue((data.data ?? data) as Record<string, unknown>)
}

export async function updateVenue(id: number, payload: FormData | Record<string, unknown>): Promise<VenueItem> {
  const isForm = payload instanceof FormData
  if (isForm) payload.append('_method', 'PUT')
  const { data } = isForm
    ? await http.post(`${baseUrl('dashboard')}/${id}`, payload)
    : await http.put(`${baseUrl('dashboard')}/${id}`, payload)
  return mapVenue((data.data ?? data) as Record<string, unknown>)
}

export async function deleteVenue(id: number): Promise<void> {
  await http.delete(`${baseUrl('dashboard')}/${id}`)
}

export async function restoreVenue(id: number): Promise<void> {
  await http.post(`${baseUrl('dashboard')}/${id}/restore`)
}

export async function detectVenue(
  name: string,
  city: string,
  country?: string,
): Promise<Record<string, unknown>> {
  const { data } = await http.post('/dashboard/venues/detect', { name, city, country })
  return data as Record<string, unknown>
}

export interface VenueEventItem {
  id: number
  name: string
  startAt: string | null
  endAt: string | null
  status: string
  canalName: string | null
  imageUrl: string | null
}

function mapVenueEvent(r: Record<string, unknown>): VenueEventItem {
  return {
    id: r['id'] as number,
    name: r['name'] as string,
    startAt: (r['start_at'] as string) ?? null,
    endAt: (r['end_at'] as string) ?? null,
    status: (r['status'] as string) ?? 'draft',
    canalName: (r['canal_name'] as string) ?? null,
    imageUrl: (r['image_url'] as string) ?? null,
  }
}

export async function listVenueEvents(scope: Scope | 'public', venueId: number): Promise<VenueEventItem[]> {
  const url = scope === 'public' ? `/venues/${venueId}/events` : `${baseUrl(scope as Scope)}/${venueId}/events`
  const { data } = await http.get(url)
  return ((data.data ?? data) as Record<string, unknown>[]).map(mapVenueEvent)
}

export async function venuesMunicipalitiesOverview(scope: Scope): Promise<MunicipalityOverviewItem[]> {
  const url = `/${scope === 'admin' ? 'admin' : 'dashboard'}/venues/municipalities-overview`
  const { data } = await http.get(url)
  return (data.data ?? data) as MunicipalityOverviewItem[]
}
