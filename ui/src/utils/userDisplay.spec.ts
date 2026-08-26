import { describe, it, expect, beforeEach } from 'vitest'
import { setLocale } from '@/i18n'
import { displayName, initials, avatarColor, pluralUsers, surnameFirst } from './userDisplay'

// Popisky idú cez slovník, testy overujú slovenské tvary.
beforeEach(() => setLocale('sk'))

describe('displayName', () => {
  it('uprednostní display_name pred e-mailom', () => {
    expect(displayName({ display_name: 'Jana Nováková', email: 'jana@test.sk' }))
      .toBe('Jana Nováková')
  })

  it('použije e-mail, keď display_name chýba', () => {
    expect(displayName({ email: 'jana@test.sk' })).toBe('jana@test.sk')
  })

  it('má zrozumiteľný fallback', () => {
    expect(displayName({})).toBe('Neznámy')
  })
})

describe('surnameFirst', () => {
  it('dá priezvisko dopredu', () => {
    expect(surnameFirst('Gabriel Gajdoš')).toBe('Gajdoš Gabriel')
  })

  it('viacslovné meno nechá pokope za priezviskom', () => {
    expect(surnameFirst('Ing. Gabriel Gajdoš')).toBe('Gajdoš Ing. Gabriel')
  })

  it('jednoslovné meno nechá tak, ako je', () => {
    expect(surnameFirst('gajdosgabo')).toBe('gajdosgabo')
  })

  it('zvládne prázdnu hodnotu', () => {
    expect(surnameFirst(null)).toBe('')
  })
})

describe('initials', () => {
  it('vezme prvé písmeno mena a priezviska', () => {
    expect(initials('Jana Nováková')).toBe('JN')
  })

  it('z jedného slova vezme prvé dve písmená', () => {
    expect(initials('Jana')).toBe('JA')
  })

  it('ignoruje interpunkciu a viacnásobné medzery', () => {
    expect(initials('  Jana   "Janka"  Nováková ')).toBe('JN')
  })

  it('zvládne diakritiku', () => {
    expect(initials('Ľubomír Šťastný')).toBe('ĽŠ')
  })

  it('pri prázdnom vstupe vráti otáznik', () => {
    expect(initials('')).toBe('?')
    expect(initials('!!!')).toBe('?')
  })
})

describe('avatarColor', () => {
  it('je deterministická — rovnaký vstup dá rovnakú farbu', () => {
    expect(avatarColor('jana@test.sk')).toBe(avatarColor('jana@test.sk'))
  })

  it('vždy vráti triedu zo zoznamu', () => {
    for (const seed of ['a', 'jana@test.sk', 'Ľubomír', '']) {
      expect(avatarColor(seed)).toMatch(/^bg-[a-z]+-500$/)
    }
  })
})

describe('pluralUsers', () => {
  it('skloňuje podľa slovenských pravidiel', () => {
    expect(pluralUsers(1)).toBe('používateľ')
    expect(pluralUsers(2)).toBe('používatelia')
    expect(pluralUsers(4)).toBe('používatelia')
    expect(pluralUsers(5)).toBe('používateľov')
    expect(pluralUsers(0)).toBe('používateľov')
  })
})
