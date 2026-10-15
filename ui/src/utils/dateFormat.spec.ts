import { describe, it, expect, beforeEach } from 'vitest'
import { setLocale } from '@/i18n'
import { dayName, weekdayLabel, fmtDate, fmtTime, fmtDayTimeRange, fmtDateLong, isSameDay, daysUntil, fmtRowDateRange, eventTimeState, relativeDayName, isAllDay, fmtEventRange, fmtAgendaTime } from './dateFormat'

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

describe('relativeDayName', () => {
  const now = new Date('2026-10-15T09:00:00')

  it('vráti Dnes a Zajtra, inak null', () => {
    expect(relativeDayName('2026-10-15T20:00:00', now)).toBe('Dnes')
    expect(relativeDayName('2026-10-16T00:30:00', now)).toBe('Zajtra')
    expect(relativeDayName('2026-10-17T10:00:00', now)).toBeNull()
    expect(relativeDayName('2026-10-14T10:00:00', now)).toBeNull()
  })
})

describe('isAllDay', () => {
  it('rozozná 00:00 – 23:59 aj koniec o polnoci', () => {
    expect(isAllDay('2026-10-16T00:00:00', '2026-10-16T23:59:00')).toBe(true)
    expect(isAllDay('2026-10-16T00:00:00', '2026-10-17T00:00:00')).toBe(true)
    expect(isAllDay('2026-10-16T17:00:00', '2026-10-16T23:59:00')).toBe(false)
    expect(isAllDay('2026-10-16T00:00:00', null)).toBe(false)
  })
})

describe('fmtEventRange', () => {
  it('ukáže čas pri jednodňovom podujatí', () => {
    expect(fmtEventRange('2026-10-15T17:00:00', '2026-10-15T19:00:00')).toBe('15. 10. 2026, 17:00 – 19:00')
  })

  it('celodenné podujatie bez časov', () => {
    expect(fmtEventRange('2026-10-16T00:00:00', '2026-10-16T23:59:00')).toBe('16. 10. 2026')
    expect(fmtEventRange('2026-10-16T00:00:00', '2026-10-17T00:00:00')).toBe('16. 10. 2026')
    expect(fmtEventRange('2026-10-16T00:00:00', '2026-10-24T23:59:00')).toBe('16. 10. 2026 – 24. 10. 2026')
  })

  it('viacdňové s časmi', () => {
    expect(fmtEventRange('2026-10-16T18:00:00', '2026-10-18T12:00:00')).toBe('16. 10. 2026, 18:00 – 18. 10. 2026, 12:00')
  })
})

describe('fmtAgendaTime', () => {
  const labels = { allDay: 'Celý deň', until: (date: string) => `do ${date}` }

  it('v rámci dňa len časy', () => {
    expect(fmtAgendaTime('2026-10-15T17:00:00', '2026-10-15T19:00:00', labels)).toBe('17:00 – 19:00')
    expect(fmtAgendaTime('2026-10-15T17:00:00', null, labels)).toBe('17:00')
  })

  it('celodenné a viacdňové', () => {
    expect(fmtAgendaTime('2026-10-16T00:00:00', '2026-10-16T23:59:00', labels)).toBe('Celý deň')
    expect(fmtAgendaTime('2026-10-16T00:00:00', '2026-10-24T23:59:00', labels)).toBe('Celý deň · do 24. 10.')
    expect(fmtAgendaTime('2026-10-16T18:00:00', '2026-10-18T12:00:00', labels)).toBe('18:00 · do 18. 10.')
  })
})
