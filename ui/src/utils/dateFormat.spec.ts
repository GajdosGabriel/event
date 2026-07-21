import { describe, it, expect } from 'vitest'
import { dayName, fmtDate, fmtTime, fmtDayTimeRange } from './dateFormat'

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
