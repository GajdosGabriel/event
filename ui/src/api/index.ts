import axios from 'axios'
import { useToast } from '@/composables/useToast'
import { currentLocale } from '@/i18n'

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

  const token = localStorage.getItem('auth_token')
  if (token) headers['Authorization'] = `Bearer ${token}`

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

  const token = localStorage.getItem('auth_token')
  if (token) {
    config.headers['Authorization'] = `Bearer ${token}`
  }

  return config
})

http.interceptors.response.use(
  (res) => res,
  (error) => {
    if (error.response?.status === 401) {
      localStorage.removeItem('auth_token')
    }

    // Rate limit z API. Bez tohto by prekročený limit vyzeral ako tichá chyba —
    // volajúci väčšinou zobrazuje len validačné chyby (422).
    if (error.response?.status === 429) {
      useToast().error(
        error.response.data?.message ?? 'Priveľa požiadaviek. Skúste to o chvíľu znova.',
      )
    }

    return Promise.reject(error)
  },
)

export default http
