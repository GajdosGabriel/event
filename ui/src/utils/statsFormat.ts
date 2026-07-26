/** Formátovanie čísel pre prehľadovú štatistiku. */

const NUMBER = new Intl.NumberFormat('sk-SK')

export function fmtCount(value: number): string {
  return NUMBER.format(value)
}

/** Sumy chodia z API v centoch — tu sa z nich stáva čitateľná mena. */
export function fmtMoney(cents: number, currency = 'EUR'): string {
  return new Intl.NumberFormat('sk-SK', {
    style: 'currency',
    currency,
    maximumFractionDigits: cents % 100 === 0 ? 0 : 2,
  }).format(cents / 100)
}

export function fmtMetric(value: number, format: 'number' | 'money'): string {
  return format === 'money' ? fmtMoney(value) : fmtCount(value)
}

/** „+12,5 %" / „−8 %" — znamienko nesie smer, farbu rieši volajúci. */
export function fmtChange(change: number | null): string | null {
  if (change === null) return null
  const rounded = Math.round(change * 10) / 10
  return `${rounded > 0 ? '+' : rounded < 0 ? '−' : ''}${NUMBER.format(Math.abs(rounded))} %`
}

export function fmtPercent(rate: number | null): string {
  return rate === null ? '—' : `${NUMBER.format(Math.round(rate * 10) / 10)} %`
}

/** Krátky dátum pre osi grafov: „24. 7." */
export function fmtShortDate(date: string): string {
  return new Date(date).toLocaleDateString('sk-SK', { day: 'numeric', month: 'numeric' })
}
