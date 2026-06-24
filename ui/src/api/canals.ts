import http from './index'
import type { CanalItem, FilterParams, PaginatedResponse, MunicipalityOverviewItem } from '@/types'

type Scope = 'dashboard' | 'admin'

function baseUrl(scope: Scope) {
  return scope === 'admin' ? '/admin/canals' : '/dashboard/canals'
}

function mapCanal(raw: Record<string, unknown>): CanalItem {
  return {
    id: raw['id'] as number,
    municipalityId: (raw['municipality_id'] as number) ?? null,
    venueId: (raw['venue_id'] as number) ?? null,
    identityMode: (raw['identity_mode'] as CanalItem['identityMode']) ?? 'personal',
    name: raw['name'] as string,
    slug: (raw['slug'] as string) ?? '',
    titlePrefix: (raw['title_prefix'] as string) ?? null,
    titleSuffix: (raw['title_suffix'] as string) ?? null,
    email: (raw['email'] as string) ?? null,
    phone: (raw['phone'] as string) ?? null,
    body: (raw['body'] as string) ?? null,
    imageUrl: (raw['image_url'] as string) ?? ((raw['primary_image'] as Record<string,string>)?.['thumb']) ?? (raw['thumb_image'] as string) ?? null,
    publishedAt: (raw['published_at'] as string) ?? null,
    status: (raw['status'] as CanalItem['status']) ?? 'draft',
    website: (raw['website'] as string) ?? null,
    deletedAt: (raw['deleted_at'] as string) ?? null,
    createdAt: (raw['created_at'] as string) ?? '',
    updatedAt: (raw['updated_at'] as string) ?? '',
    uploadedFiles: (raw['uploaded_files'] as CanalItem['uploadedFiles']) ?? [],
    permissions: (raw['permissions'] as CanalItem['permissions']) ?? { view: true, update: false, delete: false, restore: false },
    allowedStatuses: (raw['allowed_statuses'] as CanalItem['allowedStatuses']) ?? [],
    municipality: raw['municipality'] ? { id: (raw['municipality'] as Record<string,unknown>)['id'] as number, name: (raw['municipality'] as Record<string,unknown>)['name'] as string } : null,
    venuesList: ((raw['venues_list'] as Record<string,unknown>[]) ?? []).map(v => ({ id: v['id'] as number, name: v['name'] as string, isOwner: v['is_owner'] as boolean })),
    membersList: ((raw['members_list'] as Record<string,unknown>[]) ?? []).map(u => ({ id: u['id'] as number, name: u['name'] as string, isOwner: u['is_owner'] as boolean })),
  }
}

export interface CanalEventItem {
  id: number
  name: string
  startAt: string | null
  endAt: string | null
  status: string
}

export async function listCanalEvents(scope: Scope, canalId: number): Promise<CanalEventItem[]> {
  const { data } = await http.get(`${baseUrl(scope)}/${canalId}/events`)
  return ((data.data ?? data) as Record<string, unknown>[]).map(r => ({
    id: r['id'] as number,
    name: r['name'] as string,
    startAt: (r['start_at'] as string) ?? null,
    endAt: (r['end_at'] as string) ?? null,
    status: (r['status'] as string) ?? 'draft',
  }))
}

export async function indexCanals(scope: Scope, params?: FilterParams & { page?: number }): Promise<PaginatedResponse<CanalItem>> {
  const { data } = await http.get(baseUrl(scope), { params })
  const items = (data.data ?? data) as Record<string, unknown>[]
  return {
    data: items.map(mapCanal),
    meta: data.meta ?? { current_page: 1, last_page: 1, per_page: 15, total: items.length },
  }
}

export async function showCanal(scope: Scope, id: number): Promise<CanalItem> {
  const { data } = await http.get(`${baseUrl(scope)}/${id}`)
  return mapCanal((data.data ?? data) as Record<string, unknown>)
}

export async function showCanalPublic(id: number): Promise<CanalItem> {
  const { data } = await http.get(`/canals/${id}`)
  return mapCanal((data.data ?? data) as Record<string, unknown>)
}

export async function createCanal(payload: FormData | Record<string, unknown>): Promise<CanalItem> {
  const { data } = await http.post(baseUrl('dashboard'), payload)
  return mapCanal((data.data ?? data) as Record<string, unknown>)
}

export async function updateCanal(id: number, payload: FormData | Record<string, unknown>): Promise<CanalItem> {
  const isForm = payload instanceof FormData
  if (isForm) payload.append('_method', 'PUT')
  const { data } = isForm
    ? await http.post(`${baseUrl('dashboard')}/${id}`, payload)
    : await http.put(`${baseUrl('dashboard')}/${id}`, payload)
  return mapCanal((data.data ?? data) as Record<string, unknown>)
}

export async function deleteCanal(id: number): Promise<void> {
  await http.delete(`${baseUrl('dashboard')}/${id}`)
}

export async function restoreCanal(id: number): Promise<void> {
  await http.post(`${baseUrl('dashboard')}/${id}/restore`)
}

export async function publishCanal(id: number, published: boolean): Promise<void> {
  await http.post(`${baseUrl('dashboard')}/${id}/publish`, { published })
}

export async function canalsMunicipalitiesOverview(scope: Scope): Promise<MunicipalityOverviewItem[]> {
  const url = `/${scope === 'admin' ? 'admin' : 'dashboard'}/canals/municipalities-overview`
  const { data } = await http.get(url)
  return (data.data ?? data) as MunicipalityOverviewItem[]
}
