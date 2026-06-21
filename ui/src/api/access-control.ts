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

export async function listUsers(scope: ACScope = 'dashboard'): Promise<Record<string, unknown>[]> {
  const { data } = await http.get(`/${scope}/users`)
  return (data.data ?? data) as Record<string, unknown>[]
}

export async function restoreUser(userId: number, scope: ACScope = 'dashboard'): Promise<void> {
  await http.post(`/${scope}/users/${userId}/restore`, {})
}
