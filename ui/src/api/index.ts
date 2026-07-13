import axios from 'axios'

// App base path (Vite `base`, e.g. '/' in dev, '/sub/event/' on prod). Always
// ends with a slash. All backend paths are derived from it so the SPA works
// whether it is served from the domain root or a subfolder.
const APP_BASE = import.meta.env.BASE_URL || '/'

export const BASE_URL = `${APP_BASE}api`
export const SANCTUM_URL = `${APP_BASE}sanctum/csrf-cookie`

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
    return Promise.reject(error)
  },
)

export default http
