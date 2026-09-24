import { describe, it, expect } from 'vitest'
import { evaluateReadiness } from './publishReadiness'
import type { ReadinessRule } from '@/api/ai'
const rules: ReadinessRule[] = [
  { key: 'name', rule: 'filled', fields: ['name'] },
  { key: 'contact', rule: 'any_of', fields: ['email', 'phone'] },
  { key: 'body', rule: 'min_chars', fields: ['body'], value: 4 },
]
describe('shared publish readiness', () => {
  it('counts visible text and missing conditions', () => {
    expect(evaluateReadiness(rules, { name: 'Event', body: '<p>&nbsp;</p>' })).toMatchObject({ missing: ['contact', 'body'], percent: 33, ready: false })
    expect(evaluateReadiness(rules, { name: 'Event', phone: '123', body: '<p>Hello</p>' })).toMatchObject({ percent: 100, ready: true })
  })
  it('does not label absent rules as ready', () => { expect(evaluateReadiness([], {})).toMatchObject({ percent: 0, ready: false }) })
})
