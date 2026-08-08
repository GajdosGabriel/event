import { describe, it, expect } from 'vitest'
import { dayName, fmtDate, fmtTime, fmtDayTimeRange, fmtDateLong, isSameDay, daysUntil } from './dateFormat'

describe('dayName', () => {
  it('vráti slovenský názov dňa', () => {
    // 2026-07-22 je streda
    expect(dayName('2026-07-22T10:00:00')).toBe('Streda')
    expect(dayName('2026-07-26T10:00:00')).toBe('Nedeľa')
  })

  it('vráti prázdny reťazec pre neplatný dátum', () => {
    expect(dayName('nezmysel')).toBe('')
  })
})

describe('fmtDate', () => {
  it('formátuje dátum v slovenskom tvare', () => {
    expect(fmtDate('2026-07-22T10:00:00')).toBe('22. 7. 2026')
  })
})

describe('fmtTime', () => {
  it('formátuje čas na hodiny a minúty', () => {
    expect(fmtTime('2026-07-22T10:05:00')).toBe('10:05')
  })
})

describe('fmtDayTimeRange', () => {
  it('spojí deň, dátum a rozsah času', () => {
    expect(fmtDayTimeRange('2026-07-22T10:00:00', '2026-07-22T12:00:00'))
      .toBe('Streda 22. 7. 2026 10:00–12:00')
  })

  it('bez konca vypíše len začiatok', () => {
    expect(fmtDayTimeRange('2026-07-22T10:00:00', null))
      .toBe('Streda 22. 7. 2026 10:00')
  })

  it('bez začiatku vráti prázdny reťazec', () => {
    expect(fmtDayTimeRange(null, '2026-07-22T12:00:00')).toBe('')
  })
})

describe('fmtDateLong', () => {
  it('vypíše mesiac slovom', () => {
    expect(fmtDateLong('2026-07-22T10:00:00')).toBe('22. júla 2026')
  })
})

describe('isSameDay', () => {
  it('rozlíši ten istý deň od nasledujúceho', () => {
    expect(isSameDay('2026-07-22T01:00:00', '2026-07-22T23:00:00')).toBe(true)
    expect(isSameDay('2026-07-22T23:00:00', '2026-07-23T01:00:00')).toBe(false)
  })
})

describe('daysUntil', () => {
  const now = new Date('2026-07-22T10:00:00')

  it('počíta celé dni do termínu', () => {
    expect(daysUntil('2026-07-25T10:00:00', now)).toBe(3)
  })

  it('neúplný deň zaokrúhli nahor', () => {
    expect(daysUntil('2026-07-22T18:00:00', now)).toBe(1)
  })

  it('minulý termín nikdy nevráti záporne', () => {
    expect(daysUntil('2026-07-01T10:00:00', now)).toBe(0)
  })

  it('neplatný dátum vráti nulu', () => {
    expect(daysUntil('nezmysel', now)).toBe(0)
  })
})
