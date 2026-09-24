import type { ReadinessRule } from '@/api/ai'

export function textLength(value: unknown): number {
  if (typeof value !== 'string') return 0
  const el = document.createElement('div')
  el.innerHTML = value
  return (el.textContent ?? '').replace(/\s+/g, ' ').trim().length
}

function filled(value: unknown): boolean {
  if (value === null || value === undefined || value === false) return false
  if (typeof value === 'string') return value.trim() !== ''
  if (Array.isArray(value)) return value.length > 0
  return true
}

export function satisfies(rule: ReadinessRule, v: Record<string, unknown>): boolean {
  switch (rule.rule) {
    case 'filled':
      return rule.fields.every(f => filled(v[f]))
    case 'any_of':
      return rule.fields.some(f => filled(v[f]))
    case 'min_chars':
      return textLength(v[rule.fields[0] ?? '']) >= (rule.value ?? 0)
    default:
      return true
  }
}

export function evaluateReadiness(rules: ReadinessRule[], values: Record<string, unknown>) {
  const missing = rules.filter(rule => !satisfies(rule, values)).map(rule => rule.key)
  const total = rules.length
  const satisfied = total - missing.length
  return { missing, total, satisfied, ready: total > 0 && missing.length === 0, percent: total ? Math.round(satisfied / total * 100) : 0 }
}
