import http from './index'
import type { AccessRole, AccessPermission } from '@/types'

export type ACScope = 'dashboard' | 'admin'

export async function getRoles(scope: ACScope = 'dashboard'): Promise<AccessRole[]> {
  const { data } = await http.get(`/${scope}/roles`)
  return (data.data ?? data) as AccessRole[]
}

export async function getPermissions(scope: ACScope = 'dashboard'): Promise<AccessPermission[]> {
  const { data } = await http.get(`/${scope}/permissions`)
  return (data.data ?? data) as AccessPermission[]
}

export async function updateUserRoles(userId: number, roles: string[], scope: ACScope = 'dashboard'): Promise<void> {
  await http.put(`/${scope}/users/${userId}/roles`, { roles })
}

// Výpis používateľov filtruje a triedi na klientovi, preto potrebuje všetky
// záznamy — API ich však stránkuje. Stiahnu sa po stránkach so 100 záznamami.
export async function listUsers(scope: ACScope = 'dashboard'): Promise<Record<string, unknown>[]> {
  const users: Record<string, unknown>[] = []
  for (let page = 1; ; page++) {
    const { data } = await http.get(`/${scope}/users`, { params: { page, per_page: 100 } })
    users.push(...((data.data ?? data) as Record<string, unknown>[]))
    if (!Array.isArray(data.data) || page >= Number(data.meta?.last_page ?? 1)) return users
  }
}

export async function showUser(userId: number, scope: ACScope = 'dashboard'): Promise<Record<string, unknown>> {
  const { data } = await http.get(`/${scope}/users/${userId}`)
  return (data.data ?? data) as Record<string, unknown>
}

export async function restoreUser(userId: number, scope: ACScope = 'dashboard'): Promise<void> {
  await http.post(`/${scope}/users/${userId}/restore`, {})
}

/**
 * Čo všetko sa dá poslať na `PUT /{scope}/users/{id}`.
 *
 * Všetko je nepovinné a server berie len to, čo naozaj prišlo — ten istý
 * endpoint obsluhuje celý admin formulár aj samotné (od)blokovanie z detailu.
 */
export interface UpdateUserPayload {
  email?: string
  status?: string
  /** Potvrdenie/zrušenie overenia e-mailu adminom. */
  email_verified?: boolean
  /** Prázdne alebo vynechané = heslo sa nemení. */
  password?: string
  /** Osobný kanál používateľa (users.canal_id). */
  canal_id?: number | null
  blocked?: boolean
  blocked_until?: string | null
  blocked_reason?: string | null
}

export async function updateUser(
  userId: number,
  payload: UpdateUserPayload,
  scope: ACScope = 'dashboard',
): Promise<Record<string, unknown>> {
  const { data } = await http.put(`/${scope}/users/${userId}`, payload)
  return (data.data ?? data) as Record<string, unknown>
}

export async function deleteUser(userId: number, scope: ACScope = 'dashboard'): Promise<void> {
  await http.delete(`/${scope}/users/${userId}`)
}
