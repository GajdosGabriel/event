import axios from 'axios'
import { useToast } from '@/composables/useToast'

export const BASE_URL = '/api'

const http = axios.create({
  baseURL: BASE_URL,
  withCredentials: true,
  headers: {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
})

function getCookie(name: string): string | null {
  const entries = document.cookie.split(';')
  for (const entry of entries) {
    const [key, ...rest] = entry.trim().split('=')
    if (key === name) return rest.join('=')
  }
  return null
}

http.interceptors.request.use((config) => {
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
