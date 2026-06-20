import http from './index'
import type { OrganizationItem, PaginatedResponse } from '@/types'

type Scope = 'dashboard' | 'admin'

function base(scope: Scope) {
  return `/${scope}/organizations`
}

function mapOrg(raw: Record<string, unknown>): OrganizationItem {
  return {
    id: raw['id'] as number,
    name: raw['name'] as string,
    slug: (raw['slug'] as string) ?? '',
    body: (raw['body'] as string) ?? null,
    website: (raw['website'] as string) ?? null,
    email: (raw['email'] as string) ?? null,
    phone: (raw['phone'] as string) ?? null,
    status: (raw['status'] as OrganizationItem['status']) ?? 'draft',
    deletedAt: (raw['deleted_at'] as string) ?? null,
    createdAt: (raw['created_at'] as string) ?? '',
    updatedAt: (raw['updated_at'] as string) ?? '',
  }
}

export async function listOrganizations(scope: Scope): Promise<PaginatedResponse<OrganizationItem>> {
  const { data } = await http.get(base(scope))
  const items = (data.data ?? data) as Record<string, unknown>[]
  return { data: items.map(mapOrg), meta: data.meta ?? { current_page: 1, last_page: 1, per_page: 15, total: items.length } }
}

export async function createOrganization(scope: Scope, payload: Record<string, unknown>): Promise<OrganizationItem> {
  const { data } = await http.post(base(scope), payload)
  return mapOrg((data.data ?? data) as Record<string, unknown>)
}

export async function updateOrganization(scope: Scope, id: number, payload: Record<string, unknown>): Promise<OrganizationItem> {
  const { data } = await http.put(`${base(scope)}/${id}`, payload)
  return mapOrg((data.data ?? data) as Record<string, unknown>)
}

export async function deleteOrganization(scope: Scope, id: number): Promise<void> {
  await http.delete(`${base(scope)}/${id}`)
}

export async function restoreOrganization(scope: Scope, id: number): Promise<void> {
  await http.post(`${base(scope)}/${id}/restore`)
}
