import http from './index'

export type SupportStatus = 'open' | 'answered' | 'closed'
export type SupportCategory = 'question' | 'problem' | 'canal' | 'idea' | 'other'

export interface SupportMessage {
  id: number
  body: string
  isStaff: boolean
  mine: boolean
  author: string
  createdAt: string
}

/**
 * Vlákno s podporou. `userEmail` a `userAgent` posiela backend len podpore
 * (super-adminovi v cudzom vlákne), používateľovi nikdy.
 */
export interface SupportTicket {
  id: number
  reference: string
  subject: string
  category: { value: SupportCategory; label: string }
  status: { value: SupportStatus; label: string }
  mine: boolean
  unread: boolean
  userName: string
  userEmail: string | null
  userId: number | null
  canal: { id: number; name: string } | null
  messagesCount: number | null
  excerpt: string | null
  lastFromStaff: boolean
  createdAt: string
  lastActivityAt: string
  pageUrl: string | null
  userAgent: string | null
  messages: SupportMessage[]
}

export interface SupportPage {
  data: SupportTicket[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
    categories: { value: SupportCategory; label: string }[]
    counts: Record<SupportStatus, number> | null
    is_staff: boolean
  }
}

function mapTicket(raw: Record<string, unknown>): SupportTicket {
  const messages = (raw['messages'] as Record<string, unknown>[] | undefined) ?? []

  return {
    id: raw['id'] as number,
    reference: (raw['reference'] as string) ?? '',
    subject: (raw['subject'] as string) ?? '',
    category: raw['category'] as SupportTicket['category'],
    status: raw['status'] as SupportTicket['status'],
    mine: Boolean(raw['mine']),
    unread: Boolean(raw['unread']),
    userName: (raw['user_name'] as string) ?? '',
    userEmail: (raw['user_email'] as string) ?? null,
    userId: (raw['user_id'] as number) ?? null,
    canal: (raw['canal'] as SupportTicket['canal']) ?? null,
    messagesCount: (raw['messages_count'] as number) ?? null,
    excerpt: (raw['excerpt'] as string) ?? null,
    lastFromStaff: Boolean(raw['last_from_staff']),
    createdAt: raw['created_at'] as string,
    lastActivityAt: raw['last_activity_at'] as string,
    pageUrl: (raw['page_url'] as string) ?? null,
    userAgent: (raw['user_agent'] as string) ?? null,
    messages: messages.map((m) => ({
      id: m['id'] as number,
      body: (m['body'] as string) ?? '',
      isStaff: Boolean(m['is_staff']),
      mine: Boolean(m['mine']),
      author: (m['author'] as string) ?? '',
      createdAt: m['created_at'] as string,
    })),
  }
}

/** `all` = schránka podpory (len super-admin); bez neho vlastné vlákna. */
export async function indexSupportTickets(params?: {
  all?: boolean
  status?: SupportStatus
  search?: string
  page?: number
}): Promise<SupportPage> {
  const { data } = await http.get('/dashboard/support/tickets', {
    params: { ...params, all: params?.all ? 1 : undefined },
  })
  return { data: (data.data as Record<string, unknown>[]).map(mapTicket), meta: data.meta }
}

export async function supportSummary(): Promise<{ unread: number; inbox: number | null }> {
  return (await http.get('/dashboard/support/summary')).data
}

export async function createSupportTicket(payload: {
  category: SupportCategory
  subject: string
  body: string
  page_url?: string | null
  canal_id?: number | null
}): Promise<SupportTicket> {
  const { data } = await http.post('/dashboard/support/tickets', payload)
  return mapTicket(data)
}

/** Otvorenie vlákna ho zároveň označí za prečítané — to rieši backend. */
export async function showSupportTicket(id: number): Promise<SupportTicket> {
  return mapTicket((await http.get(`/dashboard/support/tickets/${id}`)).data)
}

export async function replySupportTicket(id: number, body: string): Promise<SupportTicket> {
  return mapTicket((await http.post(`/dashboard/support/tickets/${id}/messages`, { body })).data)
}

export async function setSupportTicketStatus(id: number, status: 'open' | 'closed'): Promise<SupportTicket> {
  return mapTicket((await http.patch(`/dashboard/support/tickets/${id}`, { status })).data)
}
