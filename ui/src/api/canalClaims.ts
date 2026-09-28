import http from './index'

/** Prevzatie kanála (App\Services\Canals\CanalClaims, docs/canal-ownership.md). */
export type CanalClaimMethod = 'invitation' | 'contact_email' | 'admin_review'
export type CanalClaimStatus = 'pending' | 'completed' | 'rejected' | 'contested' | 'reverted' | 'expired'

/** Čo vidí držiteľ odkazu z e-mailu (potvrdenie alebo námietka). */
export interface CanalClaimDetail {
  canalId: number | null
  canalName: string | null
  requesterName: string | null
  /** Maskovaná — odkaz sa dá preposlať. */
  requesterEmail: string | null
  method: CanalClaimMethod
  message: string | null
  status: CanalClaimStatus
  expiresAt: string | null
  contestUntil: string | null
  contestable: boolean
}

function mapDetail(payload: Record<string, unknown>): CanalClaimDetail {
  const data = (payload['data'] ?? {}) as Record<string, unknown>
  const canal = (data['canal'] ?? {}) as Record<string, unknown>
  const requester = (data['requester'] ?? {}) as Record<string, unknown>

  return {
    canalId: (canal['id'] as number) ?? null,
    canalName: (canal['name'] as string) ?? null,
    requesterName: (requester['name'] as string) ?? null,
    requesterEmail: (requester['email'] as string) ?? null,
    method: data['method'] as CanalClaimMethod,
    message: (data['message'] as string) ?? null,
    status: data['status'] as CanalClaimStatus,
    expiresAt: (data['expires_at'] as string) ?? null,
    contestUntil: (data['contest_until'] as string) ?? null,
    contestable: Boolean(data['contestable']),
  }
}

export async function requestCanalClaim(canalId: number, method: 'contact_email' | 'admin_review', message: string): Promise<void> {
  await http.post(`/canals/${canalId}/claims`, { method, message: message || null })
}

export async function showCanalClaim(token: string): Promise<CanalClaimDetail> {
  const { data } = await http.get(`/canal-claims/${token}`)
  return mapDetail(data)
}

export async function confirmCanalClaim(token: string): Promise<void> {
  await http.post(`/canal-claims/${token}/confirm`)
}

export async function showCanalClaimContest(token: string): Promise<CanalClaimDetail> {
  const { data } = await http.get(`/canal-claims/contest/${token}`)
  return mapDetail(data)
}

export async function contestCanalClaim(token: string, note: string): Promise<void> {
  await http.post(`/canal-claims/contest/${token}`, { note: note || null })
}

/** Riadok admin fronty. */
export interface AdminCanalClaim {
  id: number
  canal: { id: number; name: string; email: string | null } | null
  user: { id: number; email: string; name: string } | null
  method: CanalClaimMethod
  status: CanalClaimStatus
  contactEmail: string | null
  message: string | null
  contestNote: string | null
  decisionNote: string | null
  decidedBy: string | null
  createdAt: string | null
  expiresAt: string | null
  completedAt: string | null
  contestedAt: string | null
}

export async function fetchAdminCanalClaims(status?: CanalClaimStatus): Promise<AdminCanalClaim[]> {
  const { data } = await http.get('/admin/canal-claims', { params: status ? { status } : {} })
  return ((data['data'] as Record<string, unknown>[]) ?? []).map(row => ({
    id: row['id'] as number,
    canal: (row['canal'] as AdminCanalClaim['canal']) ?? null,
    user: (row['user'] as AdminCanalClaim['user']) ?? null,
    method: row['method'] as CanalClaimMethod,
    status: row['status'] as CanalClaimStatus,
    contactEmail: (row['contact_email'] as string) ?? null,
    message: (row['message'] as string) ?? null,
    contestNote: (row['contest_note'] as string) ?? null,
    decisionNote: (row['decision_note'] as string) ?? null,
    decidedBy: (row['decided_by'] as string) ?? null,
    createdAt: (row['created_at'] as string) ?? null,
    expiresAt: (row['expires_at'] as string) ?? null,
    completedAt: (row['completed_at'] as string) ?? null,
    contestedAt: (row['contested_at'] as string) ?? null,
  }))
}

export async function approveCanalClaim(id: number, note: string): Promise<void> {
  await http.post(`/admin/canal-claims/${id}/approve`, { note: note || null })
}

export async function rejectCanalClaim(id: number, note: string): Promise<void> {
  await http.post(`/admin/canal-claims/${id}/reject`, { note: note || null })
}

export async function resolveCanalClaim(id: number, revert: boolean, note: string): Promise<void> {
  await http.post(`/admin/canal-claims/${id}/resolve`, { revert, note: note || null })
}
