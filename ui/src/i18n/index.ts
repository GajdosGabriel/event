import { ref, computed } from 'vue'
import sk, { type Messages } from './locales/sk'
import cs from './locales/cs'
import de from './locales/de'
import en from './locales/en'

// Zámerne bez vue-i18n. Potrebujeme lookup kľúča a prepínač jazyka, nie
// pluralizáciu ani formátovacie profily — to by bola ďalšia závislosť navyše.
// Poradie kľúčov určuje poradie v prepínači; sk je prvé, lebo je default.
// Zoznam musí sedieť s config('app.supported_locales') na backende.
const dictionaries = { sk, cs, de, en } satisfies Record<string, Messages>

export type Locale = keyof typeof dictionaries

export const SUPPORTED_LOCALES = Object.keys(dictionaries) as Locale[]

export const DEFAULT_LOCALE: Locale = 'sk'

const STORAGE_KEY = 'locale'

/** Bodková cesta ku ktorémukoľvek reťazcu v slovníku, napr. 'nav.events'. */
type Path<T> = {
  [K in keyof T & string]: T[K] extends string ? K : `${K}.${Path<T[K]>}`
}[keyof T & string]

export type MessageKey = Path<Messages>

function isSupported(value: string | null | undefined): value is Locale {
  return !!value && (SUPPORTED_LOCALES as string[]).includes(value)
}

function detect(): Locale {
  const stored = localStorage.getItem(STORAGE_KEY)
  if (isSupported(stored)) return stored

  // 'cs-CZ' → 'cs'
  for (const tag of navigator.languages ?? [navigator.language]) {
    const base = tag?.split('-')[0]?.toLowerCase()
    if (isSupported(base)) return base
  }

  return DEFAULT_LOCALE
}

const locale = ref<Locale>(detect())

/** Pre volajúcich mimo komponentu (napr. axios interceptor). */
export function currentLocale(): Locale {
  return locale.value
}

export function setLocale(next: Locale) {
  if (!isSupported(next) || next === locale.value) return

  locale.value = next
  localStorage.setItem(STORAGE_KEY, next)
  document.documentElement.lang = next
}

export function t(key: MessageKey, params?: Record<string, string | number>): string {
  const value = key
    .split('.')
    .reduce<unknown>((acc, part) => (acc as Record<string, unknown> | undefined)?.[part], dictionaries[locale.value])

  // Chýbajúci preklad radšej ukáže kľúč než prázdno — v UI je to hneď vidieť.
  if (typeof value !== 'string') return key

  if (!params) return value

  return value.replace(/\{(\w+)\}/g, (match, name) => String(params[name] ?? match))
}

export function useI18n() {
  return {
    t,
    locale: computed(() => locale.value),
    setLocale,
    locales: SUPPORTED_LOCALES,
  }
}

/** Zavolá sa raz pri štarte, aby <html lang> sedel s tým, čo sa vykresľuje. */
export function initI18n() {
  document.documentElement.lang = locale.value
}
