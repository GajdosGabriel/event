import { ref, watch, type Ref } from 'vue'

/**
 * História vyhľadávania jedného výpisu, uložená v prehliadači.
 *
 * Kľúč je per-výpis (`event`, `admin-users`, …) — hľadané výrazy jedného
 * zoznamu nemajú čo napovedať v druhom. Nič sa neposiela na server: je to
 * pohodlie tohto prehliadača, nie profil používateľa.
 */

const PREFIX = 'search_history:'
const LIMIT = 8

function read(key: string): string[] {
  try {
    const raw = JSON.parse(localStorage.getItem(PREFIX + key) ?? '[]')
    return Array.isArray(raw) ? raw.filter((v): v is string => typeof v === 'string') : []
  } catch {
    // Poškodený alebo nedostupný localStorage (privátny režim) nie je dôvod
    // zhodiť filter — história je len nadstavba.
    return []
  }
}

function write(key: string, items: string[]) {
  try {
    localStorage.setItem(PREFIX + key, JSON.stringify(items))
  } catch {
    /* prázdne: bez úložiska história jednoducho neprežije reload */
  }
}

export function useSearchHistory(key: Ref<string>, limit = LIMIT) {
  const items = ref<string[]>([])

  watch(key, k => { items.value = k ? read(k) : [] }, { immediate: true })

  /** Zapíše výraz na vrch histórie; duplicity a rozpísané tvary zahodí. */
  function add(term: string) {
    if (!key.value) return
    const value = term.trim()
    if (!value) return

    const lower = value.toLowerCase()
    // „bra“ pred „bratislava“ je ten istý pokus o hľadanie, len skorší (uložiť
    // sa stihol pri opustení poľa). V histórii má zostať len dokončený tvar.
    const rest = items.value.filter(item => {
      const l = item.toLowerCase()
      return l !== lower && !lower.startsWith(l)
    })

    items.value = [value, ...rest].slice(0, limit)
    write(key.value, items.value)
  }

  function remove(term: string) {
    items.value = items.value.filter(item => item !== term)
    write(key.value, items.value)
  }

  function clear() {
    items.value = []
    write(key.value, [])
  }

  return { items, add, remove, clear }
}
