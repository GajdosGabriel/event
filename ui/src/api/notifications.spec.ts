import { describe, expect, it, vi } from 'vitest'
const { get } = vi.hoisted(() => ({ get: vi.fn() }))
vi.mock('./index', () => ({ default: { get } }))
import { fetchUnreadCount, groupNotifications, notificationLink, resetUnreadCountCache, type NotificationItem } from './notifications'

describe('notification links and groups', () => {
  it('allows local links and rejects executable or external URLs', () => {
    expect(notificationLink('/dashboard/spravy')).toBe('/dashboard/spravy')
    expect(notificationLink(window.location.origin + '/moje-listky')).toBe('/moje-listky')
    for (const link of ['javascript:alert(1)', 'data:text/html,test', '//evil.test', 'https://evil.test', '/\\\\evil.test']) {
      expect(notificationLink(link)).toBeNull()
    }
  })
  it('groups identical messages with their read states without losing IDs', () => {
    const items: NotificationItem[] = [
      { id: '1', data: { message: 'Správa', link: '/a' }, read_at: null, created_at: '2026-09-27T12:00:00Z' },
      { id: '2', data: { message: 'Správa', link: '/a' }, read_at: '2026-09-27T12:00:00Z', created_at: '2026-09-26T12:00:00Z' },
      { id: '3', data: { message: 'Správa', link: '/b' }, read_at: null, created_at: '2026-09-25T12:00:00Z' },
    ]
    const groups = groupNotifications(items)
    expect(groups).toHaveLength(2)
    expect(groups[0]?.ids).toEqual(['1', '2'])
    expect(groups[0]?.unreadIds).toEqual(['1'])
  })
})

describe('unread count polling', () => {
  it('shares one request between concurrent callers and reuses a fresh answer', async () => {
    resetUnreadCountCache()
    get.mockReset()
    get.mockResolvedValue({ data: { unread: 3 } })

    const [a, b] = await Promise.all([fetchUnreadCount(), fetchUnreadCount()])
    expect([a, b]).toEqual([3, 3])
    expect(await fetchUnreadCount()).toBe(3)
    expect(get).toHaveBeenCalledOnce()

    // Zmena používateľa alebo úprava správ musí obísť pamäť.
    get.mockResolvedValue({ data: { unread: 0 } })
    expect(await fetchUnreadCount(true)).toBe(0)
    expect(get).toHaveBeenCalledTimes(2)
  })
})
