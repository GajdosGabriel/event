import { describe, it, expect } from 'vitest'
import { displayName, initials, avatarColor, pluralUsers } from './userDisplay'

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
