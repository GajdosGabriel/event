import http, { BASE_URL, SANCTUM_URL } from './index'
import type { AuthIdentity, LoginPayload, RegisterPayload } from '@/types'

async function csrf() {
  await http.get(SANCTUM_URL, { baseURL: '' })
}

function unwrapIdentity(data: unknown): AuthIdentity | null {
  const candidates: unknown[] = []
  if (data && typeof data === 'object') {
    if ('data' in data) {
      const d = (data as Record<string, unknown>)['data']
      candidates.push(d)
      if (d && typeof d === 'object' && 'user' in d) {
        candidates.push((d as Record<string, unknown>)['user'])
      }
    }
    if ('user' in data) candidates.push((data as Record<string, unknown>)['user'])
  }
  candidates.push(data)

  for (const c of candidates) {
    if (!c || typeof c !== 'object') continue
    const obj = c as Record<string, unknown>
    const id = typeof obj['id'] === 'number' ? obj['id'] : null
    if (id === null) continue
    return obj as unknown as AuthIdentity
  }
  return null
}

export async function login(payload: LoginPayload): Promise<AuthIdentity> {
  await csrf()
  const { data } = await http.post('/login', payload)
  const token = data.access_token ?? data.token ?? data.auth_token
  if (token) localStorage.setItem('auth_token', token)
  const identity = unwrapIdentity(data)
  if (!identity) throw new Error('No identity in login response')
  return identity
}

export async function register(payload: RegisterPayload): Promise<void> {
  await csrf()
  await http.post('/register', payload)
}

export async function logout(): Promise<void> {
  await http.post('/logout', {})
  localStorage.removeItem('auth_token')
}

export async function fetchMe(): Promise<AuthIdentity | null> {
  await csrf()
  const { data } = await http.get('/user')
  return unwrapIdentity(data)
}

export async function resendVerification(email: string): Promise<void> {
  await csrf()
  await http.post('/register/resend', { email })
}

export async function verifyEmail(payload: { id: string; hash: string; expires: string; signature: string }): Promise<void> {
  await csrf()
  await http.post('/register/verify', payload)
}

export async function setActiveCanal(canalId: number): Promise<AuthIdentity | null> {
  await http.post('/dashboard/users/active-canal', { canal_id: canalId })
  const { data } = await http.get('/user')
  return unwrapIdentity(data)
}

export async function verifyRegistrationLink(token: string): Promise<{ message: string }> {
  const { data } = await http.get(`/register/verify/${token}`)
  return data as { message: string }
}

export function startSocialLogin(provider: 'google' | 'facebook') {
  window.location.assign(`${BASE_URL}/auth/${provider}/redirect`)
}
