import http from './index'

export interface NotificationItem {
  id: string
  data: { message: string; link: string | null }
  read_at: string | null
  created_at: string
}

export interface NotificationPage {
  data: NotificationItem[]
  meta: { current_page: number; last_page: number; unread: number }
}

export async function fetchNotifications(page = 1): Promise<NotificationPage> {
  return (await http.get<NotificationPage>('/notifications', { params: { page } })).data
}

export async function fetchUnreadCount(): Promise<number> {
  return (await http.get<{ unread: number }>('/notifications/count')).data.unread
}

export async function markNotifications(read: boolean, ids?: string[]): Promise<void> {
  await http.post(`/notifications/${read ? 'read' : 'unread'}`, ids ? { ids } : {})
}

export async function deleteNotifications(selection: { ids?: string[]; only?: 'read' } = {}): Promise<void> {
  await http.delete('/notifications', { data: selection })
}

export function notificationLink(value: string | null): string | null {
  if (!value) return null
  try {
    const url = new URL(value, window.location.origin)
    if (url.origin !== window.location.origin || !['http:', 'https:'].includes(url.protocol)) return null
    return url.pathname + url.search + url.hash
  } catch { return null }
}

export function groupNotifications(items: NotificationItem[]) {
  const groups = new Map<string, { key: string; ids: string[]; unreadIds: string[]; message: string; link: string | null; created_at: string }>()
  for (const item of items) {
    const key = JSON.stringify([item.data.link, item.data.message])
    let group = groups.get(key)
    if (!group) {
      group = { key, ids: [], unreadIds: [], message: item.data.message, link: notificationLink(item.data.link), created_at: item.created_at }
      groups.set(key, group)
    }
    group.ids.push(item.id)
    if (!item.read_at) group.unreadIds.push(item.id)
  }
  return [...groups.values()]
}
