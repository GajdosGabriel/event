import http from './index'
import type { LookupOption, MunicipalityItem, PaginatedResponse } from '@/types'

export type MunicipalityScope = 'dashboard' | 'admin'

function baseUrl(scope: MunicipalityScope) {
  return `/${scope}/municipalities`
}

function mapMunicipality(raw: Record<string, unknown>): MunicipalityItem {
  return {
    id: raw['id'] as number,
    name: raw['name'] as string,
    shortname: (raw['shortname'] as string | null) ?? null,
    zip: (raw['zip'] as string | null) ?? null,
    createdAt: raw['created_at'] as string,
    updatedAt: raw['updated_at'] as string,
    deletedAt: (raw['deleted_at'] as string | null) ?? null,
  }
}

export async function listMunicipalities(scope: MunicipalityScope = 'dashboard'): Promise<LookupOption[]> {
  const { data } = await http.get(`${baseUrl(scope)}/all`)
  return (data.data ?? data) as LookupOption[]
}

export async function indexMunicipalities(
  scope: MunicipalityScope,
  params?: Record<string, unknown>,
): Promise<PaginatedResponse<MunicipalityItem>> {
  const { data } = await http.get(baseUrl(scope), { params })
  const items = ((data.data ?? data) as Record<string, unknown>[]).map(mapMunicipality)
  return { data: items, meta: data.meta ?? { current_page: 1, last_page: 1, per_page: items.length, total: items.length } }
}

export async function showMunicipality(scope: MunicipalityScope, id: number): Promise<MunicipalityItem> {
  const { data } = await http.get(`${baseUrl(scope)}/${id}`)
  return mapMunicipality((data.data ?? data) as Record<string, unknown>)
}

export async function createMunicipality(
  scope: MunicipalityScope,
  payload: Record<string, unknown>,
): Promise<MunicipalityItem> {
  const { data } = await http.post(baseUrl(scope), payload)
  return mapMunicipality((data.data ?? data) as Record<string, unknown>)
}

export async function updateMunicipality(
  scope: MunicipalityScope,
  id: number,
  payload: Record<string, unknown>,
): Promise<MunicipalityItem> {
  const { data } = await http.put(`${baseUrl(scope)}/${id}`, payload)
  return mapMunicipality((data.data ?? data) as Record<string, unknown>)
}

export async function deleteMunicipality(scope: MunicipalityScope, id: number): Promise<void> {
  await http.delete(`${baseUrl(scope)}/${id}`)
}
