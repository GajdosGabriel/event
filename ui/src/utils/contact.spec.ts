import { describe, expect, it } from 'vitest'
import { isValidPhone, isValidPostcode } from './contact'

describe('isValidPhone', () => {
  it('accepts common formats and blank', () => {
    for (const v of ['+421 900 123 456', '0900-123-456', '(02) 1234 5678', '', null]) {
      expect(isValidPhone(v)).toBe(true)
    }
  })

  it('rejects letters and wrong lengths', () => {
    for (const v of ['abc-telefon', '12345', '+421 900 123 456 789 012']) {
      expect(isValidPhone(v)).toBe(false)
    }
  })
})

describe('isValidPostcode', () => {
  it('requires 5 digits for SK/CZ', () => {
    expect(isValidPostcode('811 01')).toBe(true)
    expect(isValidPostcode('81101', 'Slovensko')).toBe(true)
    expect(isValidPostcode('ABC 12')).toBe(false)
    expect(isValidPostcode('')).toBe(true)
  })

  it('is loose for other countries', () => {
    expect(isValidPostcode('SW1A 1AA', 'United Kingdom')).toBe(true)
  })
})
