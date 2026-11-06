import { beforeEach, describe, expect, it, vi } from 'vitest'
import { internalRedirect, rememberRedirect, takeRememberedRedirect } from './authRedirect'

beforeEach(() => {
  localStorage.clear()
  vi.useRealTimers()
})

describe('internalRedirect', () => {
  it('accepts only internal paths', () => {
    expect(internalRedirect('/organizatori/5')).toBe('/organizatori/5')
    expect(internalRedirect('//evil.test')).toBeNull()
    expect(internalRedirect('/\\evil.test')).toBeNull()
    expect(internalRedirect('https://evil.test')).toBeNull()
    expect(internalRedirect(['/a'])).toBeNull()
  })
})

describe('remembered redirect', () => {
  it('is returned once', () => {
    rememberRedirect('/organizatori/5')
    expect(takeRememberedRedirect()).toBe('/organizatori/5')
    expect(takeRememberedRedirect()).toBeNull()
  })

  it('ignores external paths', () => {
    rememberRedirect('//evil.test')
    expect(takeRememberedRedirect()).toBeNull()
  })

  it('expires after a week', () => {
    vi.useFakeTimers()
    rememberRedirect('/organizatori/5')
    vi.advanceTimersByTime(8 * 24 * 60 * 60 * 1000)
    expect(takeRememberedRedirect()).toBeNull()
  })
})
