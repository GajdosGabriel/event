import type { AttributeIssue, AttributeIssues } from '@/types'

/**
 * Prevod `attribute_issues` z detailu modelu na tvar pre komponenty.
 *
 * API posiela len tie údaje, ktoré overenie neprešli — „zatiaľ neoverené"
 * nechodí vôbec, takže prítomnosť kľúča už sama znamená problém.
 */
export function mapAttributeIssues(raw: unknown): AttributeIssues | null {
  if (!raw || typeof raw !== 'object') return null

  const source = raw as Record<string, unknown>
  const issues: AttributeIssues = {}

  for (const key of ['website'] as const) {
    const value = source[key]
    if (!value || typeof value !== 'object') continue

    const data = value as Record<string, unknown>

    issues[key] = {
      status: 'failed',
      reason: (data['reason'] as string) ?? null,
      httpStatus: (data['http_status'] as number) ?? null,
      failures: Number(data['failures'] ?? 0),
      checkedAt: (data['checked_at'] as string) ?? null,
      notifiedAt: (data['notified_at'] as string) ?? null,
    } satisfies AttributeIssue
  }

  return Object.keys(issues).length > 0 ? issues : null
}
