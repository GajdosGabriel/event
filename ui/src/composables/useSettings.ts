import { ref } from 'vue'

const STORAGE_KEY = 'project_settings'

export interface AppSettings {
  eventsPerPage: number
  venuesPerPage: number
  canalsPerPage: number
  publicEventsPerPage: number
}

export const PER_PAGE_OPTIONS = [10, 15, 25, 50, 100]

const DEFAULTS: AppSettings = {
  eventsPerPage: 25,
  venuesPerPage: 25,
  canalsPerPage: 25,
  publicEventsPerPage: 12,
}

function loadFromStorage(): AppSettings {
  try {
    const raw = localStorage.getItem(STORAGE_KEY)
    if (raw) return { ...DEFAULTS, ...JSON.parse(raw) }
  } catch {}
  return { ...DEFAULTS }
}

const settings = ref<AppSettings>(loadFromStorage())

export function useSettings() {
  function save() {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(settings.value))
  }

  function reset() {
    settings.value = { ...DEFAULTS }
    localStorage.removeItem(STORAGE_KEY)
  }

  return { settings, save, reset, PER_PAGE_OPTIONS }
}
