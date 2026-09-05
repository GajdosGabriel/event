import { ref } from 'vue'

const STORAGE_KEY = 'project_settings'

export type PublicEventsView = 'agenda' | 'grid' | 'map'

export interface AppSettings {
  eventsPerPage: number
  venuesPerPage: number
  canalsPerPage: number
  publicEventsPerPage: number
  publicEventsView: PublicEventsView
  homeOngoingOpen: boolean
  /** Okruh pre „V mojom okolí" v kilometroch. Samotná poloha sa neukladá. */
  nearbyRadiusKm: number
}

export const PER_PAGE_OPTIONS = [10, 15, 25, 50, 100]

/** Okruhy pre „V mojom okolí“ — od pešej vzdialenosti po dojazd autom. */
export const NEARBY_RADIUS_OPTIONS = [5, 10, 25, 50, 100]

const DEFAULTS: AppSettings = {
  eventsPerPage: 25,
  venuesPerPage: 25,
  canalsPerPage: 25,
  publicEventsPerPage: 12,
  publicEventsView: 'agenda',
  homeOngoingOpen: false,
  nearbyRadiusKm: 25,
}

function loadFromStorage(): AppSettings {
  try {
    const raw = localStorage.getItem(STORAGE_KEY)
    if (raw) return { ...DEFAULTS, ...JSON.parse(raw) }
  } catch {
    // Poškodený alebo nedostupný localStorage (napr. privátny režim) nie je
    // chyba, ktorú by malo zmysel hlásiť — vraciame defaulty.
  }
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
