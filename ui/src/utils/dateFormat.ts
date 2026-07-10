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

/** „Streda 1. 8. 2026 10:00–12:00" — termín workshopu. */
export function fmtDayTimeRange(start: string | null, end: string | null): string {
  if (!start) return ''
  const label = `${dayName(start)} ${fmtDate(start)} ${fmtTime(start)}`
  return end ? `${label}–${fmtTime(end)}` : label
}
