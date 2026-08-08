/** Formátovanie čísel pre prehľadovú štatistiku. */

import { currentLocale } from '@/i18n'

// Formátovač sa nedá vytvoriť raz do konštanty — jazyk sa prepína za behu
// a s ním aj oddeľovače (1 234,5 vs. 1,234.5). Inštancie sú drahé, tak si ich
// držíme v keši podľa jazyka.
const NUMBER_FORMATS = new Map<string, Intl.NumberFormat>()

function number(): Intl.NumberFormat {
  const locale = currentLocale()
  let format = NUMBER_FORMATS.get(locale)

  if (!format) {
    format = new Intl.NumberFormat(locale)
    NUMBER_FORMATS.set(locale, format)
  }

  return format
}

export function fmtCount(value: number): string {
  return number().format(value)
}

/** Sumy chodia z API v centoch — tu sa z nich stáva čitateľná mena. */
export function fmtMoney(cents: number, currency = 'EUR'): string {
  return new Intl.NumberFormat(currentLocale(), {
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
  return `${rounded > 0 ? '+' : rounded < 0 ? '−' : ''}${number().format(Math.abs(rounded))} %`
}

export function fmtPercent(rate: number | null): string {
  return rate === null ? '—' : `${number().format(Math.round(rate * 10) / 10)} %`
}

/** Krátky dátum pre osi grafov: „24. 7." */
export function fmtShortDate(date: string): string {
  return new Date(date).toLocaleDateString(currentLocale(), { day: 'numeric', month: 'numeric' })
}

/** Hodina a minúta — pri čase zverejnenia štatistiky aj v najbližšom programe. */
export function fmtTime(date: Date): string {
  return date.toLocaleTimeString(currentLocale(), { hour: '2-digit', minute: '2-digit' })
}

/** Deň a mesiac bez roka — v najbližšom programe je rok nadbytočný. */
export function fmtDayMonth(date: Date): string {
  return date.toLocaleDateString(currentLocale(), { day: 'numeric', month: 'numeric' })
}
