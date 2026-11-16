import { describe, it, expect } from 'vitest'
import { looksLikeOrganization } from './organizationName'

describe('looksLikeOrganization', () => {
  it.each([
    'CYRILOMETODIADA, o. z.',
    'Firma s.r.o.',
    'Občianske združenie Lúč',
    'Farnosť Nitra',
    'Spolok priateľov hôr',
    'Musterverein e.V.',
  ])('flags „%s"', name => {
    expect(looksLikeOrganization(name)).toBe(true)
  })

  it.each(['Jana Nováková', 'Ján Oz', 'Peter Asztalos', 'Mária Klubová', 'Zuzana Mestová', ''])('leaves a personal name „%s" alone', name => {
    expect(looksLikeOrganization(name)).toBe(false)
  })
})
