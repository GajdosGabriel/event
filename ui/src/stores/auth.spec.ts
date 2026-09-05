import { describe, it, expect, vi, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import type { AuthIdentity } from '@/types'

/**
 * Prihlásený stav v navigácii.
 *
 * Testuje sa práve to miesto, kde sa to raz pokazilo: token v `localStorage`
 * sám o sebe neznamená, že vieme, kto je prihlásený. Kým sa `/user` neozve,
 * appka nesmie tvrdiť ani jedno, a keď sa neozve vôbec, musí sa vrátiť do
 * odhláseného stavu — nie ostať visieť v menu s prázdnym menom.
 */
const fetchMe = vi.fn()

vi.mock('@/api/auth', () => ({
  fetchMe: (...args: unknown[]) => fetchMe(...args),
  login: vi.fn(),
  logout: vi.fn(),
  socialLogin: vi.fn(),
  setActiveCanal: vi.fn(),
}))

const IDENTITY = { id: 1, display_name: 'Jana Nováková' } as unknown as AuthIdentity

async function store() {
  const { useAuthStore } = await import('./auth')
  return useAuthStore()
}

beforeEach(() => {
  vi.resetModules()
  fetchMe.mockReset()
  localStorage.clear()
  setActivePinia(createPinia())
})

describe('auth store', () => {
  it('bez tokenu je stav známy hneď a nikto nie je prihlásený', async () => {
    const auth = await store()

    expect(auth.ready).toBe(true)
    expect(auth.isAuthenticated).toBe(false)
  })

  it('s tokenom čaká na identitu — hlási prihlásenie, ale nie hotový stav', async () => {
    localStorage.setItem('auth_token', 'abc')
    const auth = await store()

    expect(auth.isAuthenticated).toBe(true)
    expect(auth.ready).toBe(false)
    expect(auth.displayName).toBe('')
  })

  it('po načítaní identity je stav hotový aj s menom', async () => {
    localStorage.setItem('auth_token', 'abc')
    fetchMe.mockResolvedValue(IDENTITY)
    const auth = await store()

    await auth.fetchIdentity()

    expect(auth.ready).toBe(true)
    expect(auth.displayName).toBe('Jana Nováková')
  })

  it('neplatný token po zlyhaní odhlási namiesto zaseknutia v prihlásenom stave', async () => {
    localStorage.setItem('auth_token', 'expired')
    fetchMe.mockRejectedValue(new Error('401'))
    const auth = await store()

    await auth.fetchIdentity()

    expect(auth.ready).toBe(true)
    expect(auth.isAuthenticated).toBe(false)
    expect(localStorage.getItem('auth_token')).toBeNull()
  })

  it('odpoveď bez použiteľného používateľa sa berie ako odhlásenie', async () => {
    localStorage.setItem('auth_token', 'abc')
    fetchMe.mockResolvedValue(null)
    const auth = await store()

    await auth.fetchIdentity()

    expect(auth.isAuthenticated).toBe(false)
  })

  it('dve súbežné volania načítajú identitu len raz', async () => {
    localStorage.setItem('auth_token', 'abc')
    fetchMe.mockResolvedValue(IDENTITY)
    const auth = await store()

    await Promise.all([auth.fetchIdentity(), auth.fetchIdentity()])

    expect(fetchMe).toHaveBeenCalledTimes(1)
  })
})
