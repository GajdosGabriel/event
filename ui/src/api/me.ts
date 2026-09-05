import http from './index'
import { mapTicket } from './tickets'
import type { PaginatedResponse, TicketItem } from '@/types'

/** Jeden odber „daj mi vedieť" tak, ako ho ukazuje stránka Moje lístky. */
export interface MySubscription {
  id: number
  type: 'event' | 'canal' | null
  createdAt: string | null
  target: {
    id: number
    name: string | null
    startAt: string | null
    /** Absolútna verejná adresa cieľa — skladá ju API (PublicUrl). */
    url: string | null
  } | null
}

/**
 * Vstupenky prihláseného účtu. `list` delí zoznam na to, čo ešte len bude,
 * a na históriu (uplynulé podujatia a zrušené objednávky).
 */
export async function myTickets(params?: {
  list?: 'upcoming' | 'past'
  page?: number
  per_page?: number
}): Promise<PaginatedResponse<TicketItem>> {
  const { data } = await http.get('/me/tickets', { params })
  const items = (data.data ?? data) as Record<string, unknown>[]

  return {
    data: items.map(mapTicket),
    meta: data.meta ?? { current_page: 1, last_page: 1, per_page: 20, total: items.length },
  }
}

export async function mySubscriptions(): Promise<MySubscription[]> {
  const { data } = await http.get('/me/subscriptions')
  const items = (data.data ?? []) as Record<string, unknown>[]

  return items.map((raw) => {
    const target = raw['target'] as Record<string, unknown> | null

    return {
      id: raw['id'] as number,
      type: (raw['type'] as MySubscription['type']) ?? null,
      createdAt: (raw['created_at'] as string) ?? null,
      target: target
        ? {
            id: target['id'] as number,
            name: (target['name'] as string) ?? null,
            startAt: (target['start_at'] as string) ?? null,
            url: (target['url'] as string) ?? null,
          }
        : null,
    }
  })
}

export async function cancelMySubscription(id: number): Promise<void> {
  await http.delete(`/me/subscriptions/${id}`)
}
