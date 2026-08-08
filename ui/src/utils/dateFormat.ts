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

/** „Streda 1. 8. 2026 10:00–12:00" — termín workshopu. */
export function fmtDayTimeRange(start: string | null, end: string | null): string {
  if (!start) return ''
  const label = `${dayName(start)} ${fmtDate(start)} ${fmtTime(start)}`
  return end ? `${label}–${fmtTime(end)}` : label
}
