import { describe, it, expect, beforeEach } from 'vitest'
import { setLocale } from '@/i18n'
import { dayName, weekdayLabel, fmtDate, fmtTime, fmtDayTimeRange, fmtDateLong, isSameDay, daysUntil, fmtRowDateRange, eventTimeState } from './dateFormat'

// Formát závisí od zvoleného jazyka; testy popisujú slovenský tvar, tak si ho
// pred každým prípadom vypýtajú (v jsdom by inak vyhral jazyk prehliadača).
beforeEach(() => setLocale('sk'))

describe('dayName', () => {
  it('vráti slovenský názov dňa s veľkým začiatočným písmenom', () => {
    // 2026-07-22 je streda
    expect(dayName('2026-07-22T10:00:00')).toBe('Streda')
    expect(dayName('2026-07-26T10:00:00')).toBe('Nedeľa')
  })

  it('sleduje zvolený jazyk', () => {
    setLocale('de')
    expect(dayName('2026-07-22T10:00:00')).toBe('Mittwoch')
  })

  it('vráti prázdny reťazec pre neplatný dátum', () => {
    expect(dayName('nezmysel')).toBe('')
  })
})

describe('weekdayLabel', () => {
  it('preloží kľúč otváracích hodín', () => {
    expect(weekdayLabel('monday')).toBe('Pondelok')
    expect(weekdayLabel('sunday')).toBe('Nedeľa')
  })

  it('neznámy kľúč vráti nezmenený', () => {
    expect(weekdayLabel('someday')).toBe('someday')
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

describe('fmtRowDateRange', () => {
  it('v rámci jedného dňa zopakuje len čas konca', () => {
    expect(fmtRowDateRange('2026-08-12T18:00:00', '2026-08-12T22:00:00'))
      .toBe('12. 8. 2026, 18:00 → 22:00')
  })

  it('pri viacdňovom evente zopakuje aj dátum', () => {
    expect(fmtRowDateRange('2026-08-12T18:00:00', '2026-08-14T10:00:00'))
      .toBe('12. 8. 2026, 18:00 → 14. 8. 2026, 10:00')
  })

  it('bez konca vypíše len začiatok', () => {
    expect(fmtRowDateRange('2026-08-12T18:00:00', null)).toBe('12. 8. 2026, 18:00')
  })

  it('bez začiatku nevráti nič', () => {
    expect(fmtRowDateRange(null, '2026-08-12T22:00:00')).toBeNull()
  })
})

describe('eventTimeState', () => {
  const now = new Date('2026-08-12T20:00:00')

  it('beží počas termínu', () => {
    expect(eventTimeState('2026-08-12T18:00:00', '2026-08-12T22:00:00', now)).toBe('ongoing')
  })

  it('po konci je skončený', () => {
    expect(eventTimeState('2026-08-11T18:00:00', '2026-08-11T22:00:00', now)).toBe('past')
  })

  it('budúci termín je predvolený stav bez štítku', () => {
    expect(eventTimeState('2026-08-20T18:00:00', '2026-08-20T22:00:00', now)).toBeNull()
  })

  it('bez konca sa riadi začiatkom', () => {
    expect(eventTimeState('2026-08-12T18:00:00', null, now)).toBe('past')
    expect(eventTimeState('2026-08-13T18:00:00', null, now)).toBeNull()
  })

  it('bez termínu alebo pri nezmysle nevráti nič', () => {
    expect(eventTimeState(null, null, now)).toBeNull()
    expect(eventTimeState('nezmysel', null, now)).toBeNull()
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
