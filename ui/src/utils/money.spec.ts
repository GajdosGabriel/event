import { describe, it, expect, beforeEach } from 'vitest'
import { setLocale } from '@/i18n'
import { formatPrice, formatPriceOrFree } from './money'

// Tvar sumy aj slovo „zdarma" závisia od jazyka; testy popisujú slovenský tvar.
beforeEach(() => setLocale('sk'))

/** Intl vkladá medzi sumu a symbol nezlomiteľnú medzeru — porovnáva sa normalizovane. */
const norm = (s: string) => s.replace(/[\u00a0\u202f]/g, ' ')

describe('formatPrice', () => {
  it('prepočíta centy na eurá', () => {
    expect(norm(formatPrice(1550))).toBe('15,50 €')
  })

  it('rešpektuje menu podujatia', () => {
    expect(norm(formatPrice(1000, 'CZK'))).toBe('10,00 CZK')
  })

  it('bez meny použije euro', () => {
    expect(norm(formatPrice(500, null))).toBe('5,00 €')
  })
})

describe('formatPriceOrFree', () => {
  it('nula aj chýbajúca cena znamenajú zdarma', () => {
    expect(formatPriceOrFree(0)).toBe('Zdarma')
    expect(formatPriceOrFree(null)).toBe('Zdarma')
    expect(formatPriceOrFree(undefined)).toBe('Zdarma')
  })

  it('nenulovú cenu naformátuje', () => {
    expect(norm(formatPriceOrFree(250, 'EUR'))).toBe('2,50 €')
  })
})
