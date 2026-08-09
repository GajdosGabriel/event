const DAY_NAMES: Record<number, string> = {
  0: 'Nedeľa', 1: 'Pondelok', 2: 'Utorok', 3: 'Streda',
  4: 'Štvrtok', 5: 'Piatok', 6: 'Sobota',
}

export function dayName(d: string): string {
  return DAY_NAMES[new Date(d).getDay()] ?? ''
}

export function fmtDate(d: string): string {
  return new Date(d).toLocaleDateString('sk-SK', { day: 'numeric', month: 'numeric', year: 'numeric' })
}

export function fmtTime(d: string): string {
  return new Date(d).toLocaleTimeString('sk-SK', { hour: '2-digit', minute: '2-digit' })
}

/** „22. júla 2026" — dlhý tvar do textových viet (uzávierka registrácie). */
export function fmtDateLong(d: string): string {
  return new Date(d).toLocaleDateString('sk-SK', { day: 'numeric', month: 'long', year: 'numeric' })
}

/** Padnú oba časy do toho istého kalendárneho dňa? */
export function isSameDay(a: string, b: string): boolean {
  return new Date(a).toDateString() === new Date(b).toDateString()
}

/**
 * Celé dni do začiatku, zaokrúhlené nahor a nikdy záporné. Používa sa na
 * odpočet pri uzávierke registrácie; 0 znamená „dnes je posledný deň".
 */
export function daysUntil(d: string, now: Date = new Date()): number {
  const target = new Date(d)
  if (Number.isNaN(target.getTime())) return 0
  return Math.max(0, Math.ceil((target.getTime() - now.getTime()) / 86_400_000))
}

/**
 * „12. 8. 2026, 18:00 → 22:00" — kompaktný termín do riadku výpisu.
 * Pri viacdňovom evente sa na pravej strane zopakuje aj dátum.
 */
export function fmtRowDateRange(start: string | null, end: string | null): string | null {
  if (!start) return null
  const label = `${fmtDate(start)}, ${fmtTime(start)}`
  if (!end) return label
  return `${label} → ${isSameDay(start, end) ? fmtTime(end) : `${fmtDate(end)}, ${fmtTime(end)}`}`
}

/**
 * V akej fáze je podujatie voči „teraz". Odvodzuje sa z termínu, nie zo
 * `status` — ten hovorí len o publikovaní, takže dávno skončený a budúci
 * event vyzerajú vo výpise rovnako.
 *
 * Nadchádzajúci je predvolený stav a vracia `null` — vo výpise by bol šum.
 */
export function eventTimeState(
  start: string | null,
  end: string | null,
  now: Date = new Date(),
): 'ongoing' | 'past' | null {
  if (!start) return null
  const startsAt = new Date(start).getTime()
  const endsAt = end ? new Date(end).getTime() : startsAt
  if (Number.isNaN(startsAt) || Number.isNaN(endsAt)) return null
  const ms = now.getTime()
  if (ms > endsAt) return 'past'
  if (ms >= startsAt) return 'ongoing'
  return null
}

/** „Streda 1. 8. 2026 10:00–12:00" — termín workshopu. */
export function fmtDayTimeRange(start: string | null, end: string | null): string {
  if (!start) return ''
  const label = `${dayName(start)} ${fmtDate(start)} ${fmtTime(start)}`
  return end ? `${label}–${fmtTime(end)}` : label
}
