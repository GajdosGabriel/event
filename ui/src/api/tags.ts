import http from './index'
import type { TagGroupItem } from '@/types'

/**
 * Číselník obsahových štítkov zoskupený podľa facetu (druh / téma / pre koho /
 * charakter). Zakladá sa v seedri na backende, front ho iba číta.
 */
export async function indexTags(options: { onlyUsed?: boolean } = {}): Promise<TagGroupItem[]> {
  const { data } = await http.get('/tags', {
    params: options.onlyUsed ? { only_used: 1 } : {},
  })

  const groups = (data.data ?? data) as Record<string, unknown>[]

  return groups.map((group) => ({
    group: group['group'] as string,
    label: group['label'] as string,
    tags: ((group['tags'] ?? []) as Record<string, unknown>[]).map((tag) => ({
      id: tag['id'] as number,
      slug: tag['slug'] as string,
      name: tag['name'] as string,
      group: tag['group'] as string,
      emoji: (tag['emoji'] as string) ?? null,
      eventsCount: (tag['events_count'] as number) ?? 0,
    })),
  }))
}
