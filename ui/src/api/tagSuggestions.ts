import http from './index'
import type { PaginatedResponse } from '@/types'

/**
 * Výrazy, ktoré AI chcela priradiť, ale nenašla ich v číselníku štítkov.
 *
 * Samotný štítok sa tu nezakladá — číselník je pre AI uzavretý zoznam a jeho
 * rozšírenie ide cez TagSeeder spolu s preštítkovaním (viď
 * Admin\TagSuggestionController). Tu sa návrh len odloží, aby v zozname
 * neprekážal.
 */
export type TagSuggestionResolution = 'promoted' | 'rejected'

export type TagSuggestionFilter = TagSuggestionResolution | 'unresolved'

export interface TagSuggestionItem {
  id: number
  slug: string
  label: string
  occurrences: number
  lastEventId: number | null
  lastSeenAt: string | null
  resolution: TagSuggestionResolution | null
}

function mapTagSuggestion(raw: Record<string, unknown>): TagSuggestionItem {
  return {
    id: raw['id'] as number,
    slug: raw['slug'] as string,
    label: raw['label'] as string,
    occurrences: Number(raw['occurrences'] ?? 0),
    lastEventId: (raw['last_event_id'] as number | null) ?? null,
    lastSeenAt: (raw['last_seen_at'] as string | null) ?? null,
    resolution: (raw['resolution'] as TagSuggestionResolution | null) ?? null,
  }
}

export async function indexTagSuggestions(
  params?: { resolution?: TagSuggestionFilter; page?: number; per_page?: number },
): Promise<PaginatedResponse<TagSuggestionItem>> {
  const { data } = await http.get('/admin/tag-suggestions', { params })
  const items = ((data.data ?? data) as Record<string, unknown>[]).map(mapTagSuggestion)

  return {
    data: items,
    meta: data.meta ?? {
      current_page: data.current_page ?? 1,
      last_page: data.last_page ?? 1,
      per_page: data.per_page ?? items.length,
      total: data.total ?? items.length,
    },
  }
}

export async function resolveTagSuggestion(
  id: number,
  resolution: TagSuggestionResolution,
): Promise<TagSuggestionItem> {
  const { data } = await http.patch(`/admin/tag-suggestions/${id}`, { resolution })
  return mapTagSuggestion((data.data ?? data) as Record<string, unknown>)
}
