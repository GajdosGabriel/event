import http from './index'
import type { SelectOption } from '@/types'

/** Rola člena v konkrétnom kanáli — zhoduje sa s App\Enums\CanalRole. */
export type CanalRole = 'owner' | 'editor' | 'checkin'

export interface CanalTeamMember {
  id: number
  name: string
  /** Adresu vidí len ten, kto tím spravuje (a každý svoju vlastnú). */
  email: string | null
  role: CanalRole
  roleLabel: string
  isOwner: boolean
  isSelf: boolean
  joinedAt: string | null
}

export interface CanalTeamInvitation {
  id: number
  email: string
  role: CanalRole
  roleLabel: string
  invitedBy: string | null
  expiresAt: string | null
  createdAt: string | null
}

export interface CanalTeam {
  members: CanalTeamMember[]
  invitations: CanalTeamInvitation[]
  roles: SelectOption[]
  canManage: boolean
}

function mapTeam(payload: Record<string, unknown>): CanalTeam {
  const data = (payload['data'] ?? {}) as Record<string, unknown>
  const meta = (payload['meta'] ?? {}) as Record<string, unknown>
  const permissions = (meta['permissions'] ?? {}) as Record<string, unknown>

  return {
    members: ((data['members'] as Record<string, unknown>[]) ?? []).map(m => ({
      id: m['id'] as number,
      name: m['name'] as string,
      email: (m['email'] as string) ?? null,
      role: m['role'] as CanalRole,
      roleLabel: m['role_label'] as string,
      isOwner: Boolean(m['is_owner']),
      isSelf: Boolean(m['is_self']),
      joinedAt: (m['joined_at'] as string) ?? null,
    })),
    invitations: ((data['invitations'] as Record<string, unknown>[]) ?? []).map(i => ({
      id: i['id'] as number,
      email: i['email'] as string,
      role: i['role'] as CanalRole,
      roleLabel: i['role_label'] as string,
      invitedBy: (i['invited_by'] as string) ?? null,
      expiresAt: (i['expires_at'] as string) ?? null,
      createdAt: (i['created_at'] as string) ?? null,
    })),
    roles: (meta['roles'] as SelectOption[]) ?? [],
    canManage: Boolean(permissions['manage']),
  }
}

function teamUrl(canalId: number): string {
  return `/dashboard/canals/${canalId}/team`
}

export async function fetchCanalTeam(canalId: number): Promise<CanalTeam> {
  const { data } = await http.get(teamUrl(canalId))
  return mapTeam(data)
}

export async function inviteCanalMember(canalId: number, email: string, role: CanalRole): Promise<CanalTeam> {
  const { data } = await http.post(`${teamUrl(canalId)}/invitations`, { email, role })
  return mapTeam(data)
}

export async function resendCanalInvitation(canalId: number, invitationId: number): Promise<CanalTeam> {
  const { data } = await http.post(`${teamUrl(canalId)}/invitations/${invitationId}/resend`)
  return mapTeam(data)
}

export async function cancelCanalInvitation(canalId: number, invitationId: number): Promise<CanalTeam> {
  const { data } = await http.delete(`${teamUrl(canalId)}/invitations/${invitationId}`)
  return mapTeam(data)
}

export async function updateCanalMemberRole(canalId: number, userId: number, role: CanalRole): Promise<CanalTeam> {
  const { data } = await http.put(`${teamUrl(canalId)}/${userId}`, { role })
  return mapTeam(data)
}

export async function removeCanalMember(canalId: number, userId: number): Promise<CanalTeam> {
  const { data } = await http.delete(`${teamUrl(canalId)}/${userId}`)
  return mapTeam(data)
}

export type InvitationStatus = 'pending' | 'accepted' | 'expired' | 'revoked'

export interface CanalInvitationDetail {
  canalId: number | null
  canalName: string | null
  role: CanalRole
  roleLabel: string
  email: string
  invitedBy: string | null
  expiresAt: string | null
  status: InvitationStatus
  /** Sedí adresa prihláseného účtu s adresou pozvánky? */
  emailMatches: boolean
}

function mapInvitation(payload: Record<string, unknown>): CanalInvitationDetail {
  const data = (payload['data'] ?? {}) as Record<string, unknown>
  const canal = (data['canal'] ?? {}) as Record<string, unknown>

  return {
    canalId: (canal['id'] as number) ?? null,
    canalName: (canal['name'] as string) ?? null,
    role: data['role'] as CanalRole,
    roleLabel: data['role_label'] as string,
    email: (data['email'] as string) ?? '',
    invitedBy: (data['invited_by'] as string) ?? null,
    expiresAt: (data['expires_at'] as string) ?? null,
    status: (data['status'] as InvitationStatus) ?? 'pending',
    emailMatches: Boolean(data['email_matches']),
  }
}

export async function showInvitation(token: string): Promise<CanalInvitationDetail> {
  const { data } = await http.get(`/invitations/${token}`)
  return mapInvitation(data)
}

export async function acceptInvitation(token: string): Promise<CanalInvitationDetail> {
  const { data } = await http.post(`/invitations/${token}/accept`)
  return mapInvitation(data)
}
