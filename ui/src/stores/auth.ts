import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import type { AuthIdentity } from '@/types'
import * as authApi from '@/api/auth'
import { authToken, clearAuthToken } from '@/api/authToken'

export const useAuthStore = defineStore('auth', () => {
  const identity = ref<AuthIdentity | null>(null)
  const loading = ref(false)

  /**
   * Vieme už, kto je prihlásený?
   *
   * Token v úložisku znamená len „niekto tu bol prihlásený" — kým sa neozve
   * `/user`, nemáme meno ani kanály. Bez tohto príznaku navigácia v tej medzere
   * vykreslila menu prihláseného používateľa s prázdnym menom; pri pomalom
   * prvom načítaní (studený PHP server) to trvalo aj sekundy a vyzeralo to
   * rozbito. Ak token nemáme, nie je na čo čakať — stav je známy hneď.
   */
  const identityResolved = ref(authToken.value === null)
  const ready = computed(() => identityResolved.value)

  // Token je reaktívny (`api/authToken`), takže odhlásenie kdekoľvek — aj cez
  // 401 v axios interceptore — okamžite prekreslí navigáciu.
  const isAuthenticated = computed(() => identity.value !== null || authToken.value !== null)
  const isSuperAdmin = computed(() => identity.value?.roles?.includes('super-admin') ?? false)
  const displayName = computed(() => identity.value?.display_name ?? '')
  /** E-mail prihláseného návštevníka — API ho posiela len jemu samému. */
  const email = computed(() => identity.value?.email ?? '')
  const canalName = computed(() => identity.value?.canal ?? '')
  const canalId = computed(() => identity.value?.canal_id ?? identity.value?.canal_context?.active?.id ?? null)

  /**
   * Beží už načítanie identity? Na verejnej stránke ho spúšťa layout a na
   * chránenej aj navigačná stráž — bez tohto by studený server dostal dva
   * rovnaké `/user` naraz a druhý by len zdržal prvé vykreslenie.
   */
  let inFlight: Promise<void> | null = null

  function fetchIdentity(): Promise<void> {
    if (inFlight) return inFlight
    inFlight = loadIdentity().finally(() => { inFlight = null })
    return inFlight
  }

  async function loadIdentity() {
    try {
      const me = await authApi.fetchMe()
      identity.value = me
      // `fetchMe` vracia `null` aj vtedy, keď odpoveď prišla bez použiteľného
      // používateľa (200, ale prázdne telo). Taký stav je odhlásenie, nie
      // „ešte nevieme" — inak by token ostal a appka by sa točila dokola.
      if (me === null) clearAuthToken()
    } catch {
      identity.value = null
      clearAuthToken()
    } finally {
      identityResolved.value = true
    }
  }

  async function login(email: string, password: string) {
    loading.value = true
    try {
      identity.value = await authApi.login({ email, password })
      identityResolved.value = true
    } finally {
      loading.value = false
    }
  }

  async function socialLogin(
    mode: 'login' | 'register',
    provider: 'google' | 'facebook',
    payload: { id_token?: string; access_token?: string; terms_accepted?: boolean },
  ) {
    loading.value = true
    try {
      const result = await authApi.socialLogin(mode, provider, payload)
      identity.value = result.identity
      identityResolved.value = true
      return result
    } finally {
      loading.value = false
    }
  }

  async function logout() {
    try {
      await authApi.logout()
    } finally {
      identity.value = null
      identityResolved.value = true
      clearAuthToken()
    }
  }

  async function setActiveCanal(canalId: number) {
    identity.value = await authApi.setActiveCanal(canalId)
  }

  function clear() {
    identity.value = null
    identityResolved.value = true
    clearAuthToken()
  }

  return { identity, loading, ready, isAuthenticated, isSuperAdmin, displayName, email, canalName, canalId, fetchIdentity, login, socialLogin, logout, setActiveCanal, clear }
})
