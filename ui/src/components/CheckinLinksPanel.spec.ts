import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import CheckinLinksPanel from './CheckinLinksPanel.vue'

const { get, post, del } = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn(), del: vi.fn() }))
vi.mock('@/api/index', () => ({ default: { get, post, delete: del } }))

const link = (id: number, label: string | null) => ({
  id,
  label,
  url: `https://tixio.test/vstup/token${id}`,
  expires_at: '2030-05-02T20:00:00Z',
  last_used_at: null,
  created_at: '2030-05-01T10:00:00Z',
})

beforeEach(() => { get.mockReset(); post.mockReset(); del.mockReset() })

describe('checkin links panel', () => {
  it('lists, creates and revokes volunteer links', async () => {
    get.mockResolvedValue({ data: { data: [link(1, 'Vchod A')] } })
    post.mockResolvedValue({ data: { data: link(2, null) } })
    del.mockResolvedValue({})
    vi.stubGlobal('confirm', () => true)

    const w = mount(CheckinLinksPanel, { props: { eventId: 7 } })
    await flushPromises()

    expect(get).toHaveBeenCalledWith('/dashboard/events/7/checkin-links')
    expect(w.text()).toContain('Vchod A')

    await w.get('input').setValue('  ')
    await w.get('form').trigger('submit')
    await flushPromises()
    expect(post).toHaveBeenCalledWith('/dashboard/events/7/checkin-links', { label: null })
    expect(w.findAll('li')).toHaveLength(2)

    await w.get('li button').trigger('click')
    await flushPromises()
    expect(del).toHaveBeenCalledWith('/dashboard/events/7/checkin-links/1')
    expect(w.findAll('li')).toHaveLength(1)
    expect(w.text()).not.toContain('Vchod A')

    vi.unstubAllGlobals()
    w.unmount()
  })
})
