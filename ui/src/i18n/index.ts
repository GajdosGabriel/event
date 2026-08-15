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

// Formátovanie dátumov a čísel nechávame na Intl — potrebuje plný BCP 47 tag,
// nie holý kód jazyka. 'en-GB' zámerne: deň pred mesiacom, ako v ostatných
// troch jazykoch; 'en-US' by v tom istom rozhraní obrátil poradie.
const LOCALE_TAGS: Record<Locale, string> = {
  sk: 'sk-SK',
  cs: 'cs-CZ',
  de: 'de-DE',
  en: 'en-GB',
}

export const DEFAULT_LOCALE: Locale = 'sk'

const STORAGE_KEY = 'locale'

/** Bodková cesta ku ktorémukoľvek reťazcu v slovníku, napr. 'nav.events'. */
type Path<T> = {
  [K in keyof T & string]: T[K] extends string ? K : `${K}.${Path<T[K]>}`
}[keyof T & string]

export type MessageKey = Path<Messages>

/** Skupina tvarov jedného počítateľného slova. */
type PluralForms = { one: string; few: string; many: string }

/** Bodková cesta k takej skupine, napr. 'organizations.counts.canals'. */
type PluralPath<T> = {
  [K in keyof T & string]: T[K] extends PluralForms
    ? K
    : T[K] extends string
      ? never
      : `${K}.${PluralPath<T[K]>}`
}[keyof T & string]

export type PluralKey = PluralPath<Messages>

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

/** Tag pre Intl (`toLocaleDateString` a spol.) podľa práve zvoleného jazyka. */
export function localeTag(): string {
  return LOCALE_TAGS[locale.value]
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

/**
 * Tvar podľa počtu: „1 kanál“, „2 kanály“, „5 kanálov“. Počet sa do vety
 * dosadí ako {n}.
 *
 * Tvar `few` patrí len číslam 2–4; nula ide do `many` („0 kanálov“, nie
 * „0 kanály“). Hranica platí pre slovenčinu a češtinu; jazyky s dvoma tvarmi
 * majú v slovníku `few` rovnaké ako `many`, takže volajúci nemusí vedieť,
 * v akom jazyku práve je. Na plnú CLDR pluralizáciu (arabčina, poľština,
 * ruština) to nestačí — tie by si vyžiadali Intl.PluralRules a iný tvar
 * slovníka.
 */
export function plural(key: PluralKey, n: number): string {
  const form = n === 1 ? 'one' : n >= 2 && n <= 4 ? 'few' : 'many'

  return t(`${key}.${form}`, { n })
}

export function useI18n() {
  return {
    t,
    plural,
    locale: computed(() => locale.value),
    setLocale,
    locales: SUPPORTED_LOCALES,
  }
}

/** Zavolá sa raz pri štarte, aby <html lang> sedel s tým, čo sa vykresľuje. */
export function initI18n() {
  document.documentElement.lang = locale.value
}
