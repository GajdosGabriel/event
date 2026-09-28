import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { reactive } from 'vue'
import { setLocale } from '@/i18n'
import NotificationBell from './NotificationBell.vue'
import * as api from '@/api/notifications'

vi.hoisted(() => { vi.stubGlobal('localStorage', { getItem: () => null, setItem: () => {}, removeItem: () => {} }) })
const mocks = vi.hoisted(() => ({ push: vi.fn(), auth: { ready: true, isAuthenticated: true, identity: { id: 1 } } }))
vi.mock('@/stores/auth', () => ({ useAuthStore: () => mocks.auth }))
vi.mock('vue-router', () => ({ useRoute: () => ({ fullPath: '/' }), useRouter: () => ({ push: mocks.push }) }))
vi.mock('@/api/notifications', async importOriginal => ({
  ...await importOriginal<typeof import('@/api/notifications')>(),
  fetchNotifications: vi.fn(), fetchUnreadCount: vi.fn(), markNotifications: vi.fn(), deleteNotifications: vi.fn(),
}))
const item = { id: 'n1', data: { message: 'Nová správa', link: '/dashboard/spravy' }, read_at: null, created_at: '2026-09-27T12:00:00Z' }
const response = { data: [item], meta: { current_page: 1, last_page: 1, unread: 1 } }
const wrappers: ReturnType<typeof mount>[] = []
function create() { const wrapper = mount(NotificationBell, { attachTo: document.body }); wrappers.push(wrapper); return wrapper }

beforeEach(() => {
  vi.resetAllMocks()
  setLocale('sk')
  mocks.auth = reactive({ ready: true, isAuthenticated: true, identity: { id: 1 } })
  vi.mocked(api.fetchUnreadCount).mockResolvedValue(1)
  vi.mocked(api.fetchNotifications).mockResolvedValue(response)
  vi.mocked(api.markNotifications).mockResolvedValue()
  vi.mocked(api.deleteNotifications).mockResolvedValue()
})
afterEach(() => { wrappers.splice(0).forEach(wrapper => wrapper.unmount()); vi.restoreAllMocks() })

describe('NotificationBell', () => {
  it('fetches only the count until opened and closes on Escape', async () => {
    const wrapper = create()
    await flushPromises()
    expect(api.fetchUnreadCount).toHaveBeenCalledOnce()
    expect(api.fetchNotifications).not.toHaveBeenCalled()
    await wrapper.get('button').trigger('click')
    await flushPromises()
    expect(wrapper.text()).toContain('Nová správa')
    await wrapper.get('section').trigger('keydown', { key: 'Escape' })
    expect(wrapper.find('section').exists()).toBe(false)
  })
  it('keeps unread state and displays an error when marking fails', async () => {
    vi.mocked(api.markNotifications).mockRejectedValue(new Error('offline'))
    const wrapper = create()
    await wrapper.get('button').trigger('click')
    await flushPromises()
    await wrapper.get('a').trigger('click')
    await flushPromises()
    expect(wrapper.get('[role="alert"]').text()).toContain('Zmenu sa nepodarilo uložiť.')
    expect(mocks.push).not.toHaveBeenCalled()
    expect(wrapper.find('section').exists()).toBe(true)
  })
  it('marks an opened notification before navigating', async () => {
    const wrapper = create()
    await wrapper.get('button').trigger('click')
    await flushPromises()
    await wrapper.get('a').trigger('click')
    await flushPromises()
    expect(api.markNotifications).toHaveBeenCalledWith(true, ['n1'])
    expect(mocks.push).toHaveBeenCalledWith('/dashboard/spravy')
    expect(wrapper.find('section').exists()).toBe(false)
  })
  it('does not fetch or render for guests', async () => {
    mocks.auth.isAuthenticated = false
    const wrapper = create()
    await flushPromises()
    expect(wrapper.find('button').exists()).toBe(false)
    expect(api.fetchUnreadCount).not.toHaveBeenCalled()
  })
  it('ignores a pending response after logout', async () => {
    let resolve!: (value: typeof response) => void
    vi.mocked(api.fetchNotifications).mockReturnValue(new Promise(done => { resolve = done }))
    const wrapper = create()
    await wrapper.get('button').trigger('click')
    mocks.auth.isAuthenticated = false
    await flushPromises()
    resolve(response)
    await flushPromises()
    expect(wrapper.find('button').exists()).toBe(false)
    expect(wrapper.text()).not.toContain('Nová správa')
  })
})
