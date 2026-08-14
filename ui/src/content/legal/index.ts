/**
 * Obchodné podmienky a zásady ochrany osobných údajov.
 *
 * Zámerne mimo `src/i18n/locales` — sú to dokumenty, nie popisky rozhrania.
 * V slovníku by rozbili prehľad kľúčov a každá oprava textu by nútila
 * prepisovať štyri 1300-riadkové súbory. Tu je jeden dokument = jeden objekt.
 *
 * Záväzné je slovenské znenie; ostatné jazyky sú preklad pre návštevníka
 * (pozri poslednú vetu záverečných ustanovení).
 */
import { currentLocale, type Locale } from '@/i18n'
import type { LegalDocument, LegalDocuments, LegalKind, LegalSection } from './types'
import sk from './sk'
import cs from './cs'
import de from './de'
import en from './en'

/**
 * Verzia dokumentov. MUSÍ sedieť s `config('legal.version')` na backende —
 * tá hodnota sa ukladá ku každému súhlasu, takže po zmene textu treba
 * zvýšiť obe naraz, inak sa staré súhlasy tvária ako nové.
 */
export const LEGAL_VERSION = '2026-08-14'

/** Deň, od ktorého je aktuálne znenie účinné. */
export const LEGAL_EFFECTIVE_FROM = '14. 8. 2026'

/**
 * Identifikačné údaje prevádzkovateľa. Zákon o ochrane spotrebiteľa aj GDPR
 * vyžadujú, aby ich spotrebiteľ našiel pred uzavretím zmluvy — dokumenty sa
 * bez nich nedajú považovať za úplné.
 *
 * DOPLNIŤ pred spustením do prevádzky.
 */
export const operator = {
  site: 'vyveska.sk',
  name: '[DOPLNIŤ: obchodné meno prevádzkovateľa]',
  address: '[DOPLNIŤ: sídlo — ulica, PSČ, obec]',
  ico: '[DOPLNIŤ: IČO]',
  dic: '[DOPLNIŤ: DIČ / IČ DPH alebo „neplatiteľ DPH"]',
  registration: '[DOPLNIŤ: zápis v OR SR, oddiel a vložka / číslo živnostenského registra]',
  email: '[DOPLNIŤ: kontaktný e-mail]',
  phone: '[DOPLNIŤ: telefón]',
  /** Miestne príslušný inšpektorát SOI podľa sídla prevádzkovateľa. */
  soi: '[DOPLNIŤ: Inšpektorát SOI pre … kraj, adresa]',
} as const

export type { LegalSection, LegalDocument, LegalDocuments, LegalKind }

const documents: Record<Locale, LegalDocuments> = { sk, cs, de, en }

/** Hodnoty, ktoré sa do textov dosádzajú cez `{kľúč}` — pozri `operator`. */
const tokens: Record<string, string> = {
  ...operator,
  version: LEGAL_VERSION,
  effectiveFrom: LEGAL_EFFECTIVE_FROM,
}

function fill(text: string): string {
  return text.replace(/\{(\w+)\}/g, (match, name: string) => tokens[name] ?? match)
}

/**
 * Dokument v jazyku rozhrania. Keby v niektorom jazyku chýbal, vráti sa
 * slovenské znenie — prázdna stránka s podmienkami je horšia než cudzí jazyk.
 */
export function legalDocument(kind: LegalKind, locale: Locale = currentLocale()): LegalDocument {
  const doc = (documents[locale] ?? documents.sk)[kind]

  return {
    title: fill(doc.title),
    perex: fill(doc.perex),
    sections: doc.sections.map((section) => ({
      heading: fill(section.heading),
      paragraphs: section.paragraphs.map(fill),
    })),
  }
}
