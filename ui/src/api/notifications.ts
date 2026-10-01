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

/**
 * Počet neprečítaných sa pýta z viacerých miest naraz: odznak v každom layoute
 * (prechod verejná stránka ↔ dashboard ho prekreslí), návrat na kartu, časovač.
 * Server pritom platí celý beh frameworku za každé volanie, takže čerstvá
 * odpoveď sa krátko drží a súbežné volania sa zlejú do jedného requestu.
 */
const COUNT_TTL_MS = 15_000
let countCache: { at: number; value: number } | null = null
let countPending: Promise<number> | null = null

/** `force` obíde krátku pamäť — po zmene prihláseného používateľa alebo úprave správ. */
export function fetchUnreadCount(force = false): Promise<number> {
  if (!force && countCache && Date.now() - countCache.at < COUNT_TTL_MS) return Promise.resolve(countCache.value)

  if (force || !countPending) {
    const request = http.get<{ unread: number }>('/notifications/count')
      .then(({ data }) => {
        countCache = { at: Date.now(), value: data.unread }
        return data.unread
      })
      .finally(() => { if (countPending === request) countPending = null })
    countPending = request
  }

  return countPending
}

export function resetUnreadCountCache(): void {
  countCache = null
  countPending = null
}

export async function markNotifications(read: boolean, ids?: string[]): Promise<void> {
  resetUnreadCountCache()
  await http.post(`/notifications/${read ? 'read' : 'unread'}`, ids ? { ids } : {})
}

export async function deleteNotifications(selection: { ids?: string[]; only?: 'read' } = {}): Promise<void> {
  resetUnreadCountCache()
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
