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
  const canalName = computed(() => identity.value?.canal ?? '')
  const canalId = computed(() => identity.value?.canal_id ?? null)

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

  return { identity, loading, isAuthenticated, isSuperAdmin, displayName, canalName, canalId, fetchIdentity, login, logout, setActiveCanal, clear }
})
