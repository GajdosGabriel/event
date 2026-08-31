import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import type { AuthIdentity } from '@/types'
import * as authApi from '@/api/auth'

export const useAuthStore = defineStore('auth', () => {
  const identity = ref<AuthIdentity | null>(null)
  const loading = ref(false)

  const isAuthenticated = computed(() => identity.value !== null || !!localStorage.getItem('auth_token'))
  const isSuperAdmin = computed(() => identity.value?.roles?.includes('super-admin') ?? false)
  const displayName = computed(() => identity.value?.display_name ?? '')
  /** E-mail prihláseného návštevníka — API ho posiela len jemu samému. */
  const email = computed(() => identity.value?.email ?? '')
  const canalName = computed(() => identity.value?.canal ?? '')
  const canalId = computed(() => identity.value?.canal_id ?? identity.value?.canal_context?.active?.id ?? null)

  async function fetchIdentity() {
    try {
      identity.value = await authApi.fetchMe()
    } catch {
      identity.value = null
      localStorage.removeItem('auth_token')
    }
  }

  async function login(email: string, password: string) {
    loading.value = true
    try {
      identity.value = await authApi.login({ email, password })
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
      localStorage.removeItem('auth_token')
    }
  }

  async function setActiveCanal(canalId: number) {
    identity.value = await authApi.setActiveCanal(canalId)
  }

  function clear() {
    identity.value = null
    localStorage.removeItem('auth_token')
  }

  return { identity, loading, isAuthenticated, isSuperAdmin, displayName, email, canalName, canalId, fetchIdentity, login, socialLogin, logout, setActiveCanal, clear }
})
