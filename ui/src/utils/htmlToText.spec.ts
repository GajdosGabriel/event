import { describe, it, expect } from 'vitest'
import { stripLeadingTitle } from './htmlToText'

describe('stripLeadingTitle', () => {
  it('odreže zopakovaný názov aj oddeľovač', () => {
    expect(stripLeadingTitle('Koncert | Štvrtok o 17:00', 'Koncert')).toBe('Štvrtok o 17:00')
    expect(stripLeadingTitle('koncert: v sále', 'Koncert')).toBe('v sále')
  })

  it('odreže aj čiastočne zopakovaný názov z importu', () => {
    expect(stripLeadingTitle(
      'Stretnutie s otcom Andrejom Branickým Štvrtok 15.10.2026 | Hosť: Norbert',
      'Stretnutie s otcom Andrejom Branickým | Hosť: Norbert Daniš | 15. október o 17.00',
    )).toBe('Štvrtok 15.10.2026 | Hosť: Norbert')
  })

  it('krátku zhodu na začiatku vety nechá tak', () => {
    expect(stripLeadingTitle('Svätá omša v kostole sv. Martina', 'Svätá omša za zosnulých')).toBe('Svätá omša v kostole sv. Martina')
  })

  it('iný začiatok nechá tak', () => {
    expect(stripLeadingTitle('Pozývame na koncert', 'Koncert')).toBe('Pozývame na koncert')
    expect(stripLeadingTitle('Text', '')).toBe('Text')
  })
})