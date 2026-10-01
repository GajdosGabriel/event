import axios from 'axios'
import { authToken, clearAuthToken } from './authToken'
import { useToast } from '@/composables/useToast'
import { currentLocale, t } from '@/i18n'

export const BASE_URL = '/api'

const http = axios.create({
  baseURL: BASE_URL,
  withCredentials: true,
  headers: {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
})

/**
 * Hlavičky, ktoré API čaká aj mimo axiosu.
 *
 * Potrebuje ich všetko, čo sa posiela cez `fetch` — teda to, čo musí prežiť
 * odchod zo stránky (`keepalive`), na čo axios nemá. Bez XSRF hlavičky by
 * takú požiadavku odmietol stateful Sanctum.
 */
export function apiHeaders(): Record<string, string> {
  const headers: Record<string, string> = {
    Accept: 'application/json',
    'Content-Type': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
    'X-Locale': currentLocale(),
  }

  const xsrf = getCookie('XSRF-TOKEN')
  if (xsrf) headers['X-XSRF-TOKEN'] = decodeURIComponent(xsrf)

  if (authToken.value) headers['Authorization'] = `Bearer ${authToken.value}`

  return headers
}

function getCookie(name: string): string | null {
  const entries = document.cookie.split(';')
  for (const entry of entries) {
    const [key, ...rest] = entry.trim().split('=')
    if (key === name) return rest.join('=')
  }
  return null
}

http.interceptors.request.use((config) => {
  // Jazyk sa posiela pri každom requeste — validačné hlášky, statusy aj maily
  // z API tak prídu v tom, čo má používateľ prepnuté v navigácii.
  config.headers['X-Locale'] = currentLocale()

  const xsrf = getCookie('XSRF-TOKEN')
  if (xsrf) {
    config.headers['X-XSRF-TOKEN'] = decodeURIComponent(xsrf)
  }

  if (authToken.value) {
    config.headers['Authorization'] = `Bearer ${authToken.value}`
  }

  return config
})

/**
 * Verejné prihlasovacie endpointy. 401 tu znamená „zlé údaje“, nie „vypršala
 * relácia“ — nesmie zmazať platný token, ktorý už v prehliadači je.
 */
const PUBLIC_AUTH_PATH = /^\/?(login|register|password)(\/|$)/

export function isPublicAuthRequest(url: string | undefined): boolean {
  return PUBLIC_AUTH_PATH.test((url ?? '').replace(/^\/api(?=\/)/, ''))
}

http.interceptors.response.use(
  (res) => res,
  (error) => {
    if (error.response?.status === 401 && !isPublicAuthRequest(error.config?.url)) {
      clearAuthToken()
    }

    // Rate limit z API. Bez tohto by prekročený limit vyzeral ako tichá chyba —
    // volajúci väčšinou zobrazuje len validačné chyby (422).
    if (error.response?.status === 429) {
      useToast().error(error.response.data?.message ?? t('common.tooManyRequests'))
    }

    return Promise.reject(error)
  },
)

export default http
