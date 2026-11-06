/**
 * Kam vrátiť človeka po prihlásení (`?redirect=`), aj keď cestou prejde
 * registráciou a overením e-mailu.
 *
 * Overovací odkaz príde e-mailom a otvorí sa bez pôvodnej adresy, preto
 * ju registrácia odloží do prehliadača a overenie si ju odtiaľ vezme.
 * Bez úložiska (súkromné okno) človek skončí na nástenke ako doteraz.
 */
const STORAGE_KEY = 'auth.pendingRedirect'
const MAX_AGE_MS = 7 * 24 * 60 * 60 * 1000

/** Len vnútorná cesta — inak by to bolo otvorené presmerovanie. */
export function internalRedirect(value: unknown): string | null {
  return typeof value === 'string' && value.startsWith('/') && !value.startsWith('//') && !value.startsWith('/\\')
    ? value
    : null
}

export function rememberRedirect(value: unknown): void {
  const path = internalRedirect(value)
  if (!path) return
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify({ path, at: Date.now() }))
  } catch {
    // Bez úložiska sa len nevrátime na pôvodnú stránku.
  }
}

/** Odloženú cestu vráti raz a zmaže ju. */
export function takeRememberedRedirect(): string | null {
  try {
    const raw = localStorage.getItem(STORAGE_KEY)
    localStorage.removeItem(STORAGE_KEY)
    if (!raw) return null
    const { path, at } = JSON.parse(raw) as { path?: unknown; at?: unknown }
    return typeof at === 'number' && Date.now() - at < MAX_AGE_MS ? internalRedirect(path) : null
  } catch {
    return null
  }
}
