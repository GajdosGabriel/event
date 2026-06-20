import http from './index'
import type { AccessRole, AccessPermission } from '@/types'

export async function getRoles(): Promise<AccessRole[]> {
  const { data } = await http.get('/dashboard/roles')
  return (data.data ?? data) as AccessRole[]
}

export async function getPermissions(): Promise<AccessPermission[]> {
  const { data } = await http.get('/dashboard/permissions')
  return (data.data ?? data) as AccessPermission[]
}

export async function updateUserRoles(userId: number, roles: string[]): Promise<void> {
  await http.put(`/dashboard/users/${userId}/roles`, { roles })
}

export async function listUsers(): Promise<Record<string, unknown>[]> {
  const { data } = await http.get('/dashboard/users')
  return (data.data ?? data) as Record<string, unknown>[]
}
