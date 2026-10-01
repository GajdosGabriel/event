import { describe, it, expect, beforeEach } from 'vitest'
import type { AxiosAdapter } from 'axios'
import http, { isPublicAuthRequest } from '@/api'

function reply401(): AxiosAdapter {
  return (config) =>
    Promise.reject({ config, response: { status: 401, data: {}, config } })
}

describe('401 interceptor', () => {
  beforeEach(() => localStorage.clear())

  it.each(['/login', '/register', '/register/resend', '/password/forgot', '/login/google'])(
    '401 z %s nemaže uloženú reláciu',
    async (url) => {
      localStorage.setItem('auth_token', 'abc')
      await expect(http.post(url, {}, { adapter: reply401() })).rejects.toBeDefined()
      expect(localStorage.getItem('auth_token')).toBe('abc')
    },
  )

  it('401 z chráneného endpointu token zmaže', async () => {
    localStorage.setItem('auth_token', 'abc')
    await expect(http.get('/user', { adapter: reply401() })).rejects.toBeDefined()
    expect(localStorage.getItem('auth_token')).toBeNull()
  })

  it('rozpozná verejné prihlasovacie cesty', () => {
    expect(isPublicAuthRequest('/login')).toBe(true)
    expect(isPublicAuthRequest('/api/register')).toBe(true)
    expect(isPublicAuthRequest('/dashboard/loginlog')).toBe(false)
    expect(isPublicAuthRequest(undefined)).toBe(false)
  })
})
