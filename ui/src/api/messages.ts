import http from './index'
import type { PaginatedResponse } from '@/types'

/** Typy cieľov, ktorým sa dá poslať správa (musia byť vo whitelist na backende). */
export type MessageTargetType = 'event' | 'venue' | 'canal'

export interface SendMessagePayload {
  target_type: MessageTargetType
  target_id: number
  body: string
}

/** Posielať môžu len prihlásení a overení používatelia — hostí front vyzve na registráciu. */
export async function sendMessage(payload: SendMessagePayload): Promise<void> {
  await http.post('/messages', payload)
}

/**
 * Správa v dashboardovom inboxe. E-mail protistrany tu zámerne nie je —
 * backend ho neposiela a odpovedá sa cez `replyToMessage()`.
 */
export interface MessageItem {
  id: number
  parentMessageId: number | null
  body: string
  readAt: string | null
  createdAt: string
  outgoing: boolean
  senderName: string
  recipientName: string
  target: { type: MessageTargetType | null; id: number; name: string } | null
  replies: MessageItem[]
  permissions: { reply: boolean; markRead: boolean }
}

function mapMessage(raw: Record<string, unknown>): MessageItem {
  const permissions = (raw['permissions'] as Record<string, boolean>) ?? {}
  const replies = (raw['replies'] as Record<string, unknown>[] | undefined) ?? []

  return {
    id: raw['id'] as number,
    parentMessageId: (raw['parent_message_id'] as number) ?? null,
    body: (raw['body'] as string) ?? '',
    readAt: (raw['read_at'] as string) ?? null,
    createdAt: raw['created_at'] as string,
    outgoing: Boolean(raw['outgoing']),
    senderName: (raw['sender_name'] as string) ?? '',
    recipientName: (raw['recipient_name'] as string) ?? '',
    target: (raw['target'] as MessageItem['target']) ?? null,
    replies: replies.map(mapMessage),
    permissions: {
      reply: Boolean(permissions['reply']),
      markRead: Boolean(permissions['mark_read']),
    },
  }
}

export async function indexMessages(params?: {
  unread?: boolean
  search?: string
  page?: number
  per_page?: number
}): Promise<PaginatedResponse<MessageItem>> {
  const { data } = await http.get('/dashboard/messages', { params })
  const items = (data.data ?? data) as Record<string, unknown>[]
  return {
    data: items.map(mapMessage),
    meta: data.meta ?? { current_page: 1, last_page: 1, per_page: 15, total: items.length },
  }
}

/** Otvorenie vlákna ho zároveň označí za prečítané — to rieši backend. */
export async function showMessage(id: number): Promise<MessageItem> {
  const { data } = await http.get(`/dashboard/messages/${id}`)
  return mapMessage((data.data ?? data) as Record<string, unknown>)
}

export async function markMessageRead(id: number, read: boolean): Promise<MessageItem> {
  const { data } = await http.post(`/dashboard/messages/${id}/read`, { read })
  return mapMessage((data.data ?? data) as Record<string, unknown>)
}

export async function replyToMessage(id: number, body: string): Promise<MessageItem> {
  const { data } = await http.post(`/dashboard/messages/${id}/reply`, { body })
  return mapMessage((data.data ?? data) as Record<string, unknown>)
}

export async function unreadMessageCount(): Promise<number> {
  const { data } = await http.get('/dashboard/messages/unread-count')
  return Number(data.unread ?? 0)
}
