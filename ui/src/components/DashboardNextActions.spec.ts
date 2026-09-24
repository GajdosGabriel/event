import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import DashboardNextActions from './DashboardNextActions.vue'
const { get, fetchRules } = vi.hoisted(() => ({ get: vi.fn(), fetchRules: vi.fn() }))
vi.mock('@/api/index', () => ({ default: { get } }))
vi.mock('@/api/ai', () => ({ fetchReadinessRules: fetchRules }))
beforeEach(() => { get.mockReset(); fetchRules.mockReset() })
describe('dashboard next actions', () => {
  it('shares readiness rules and hides a completed checklist', async () => {
    get.mockResolvedValue({ data: { data: { upcoming: [], unread: 3, checklist: [{ kind: 'canal', done: true }], drafts: [1, 2].map(id => ({ id, name: `Draft ${id}`, can_edit: true, values: { name: 'Ready' } })) } } })
    fetchRules.mockResolvedValue({ event: [{ key: 'name', rule: 'filled', fields: ['name'] }] })
    const w = mount(DashboardNextActions, { global: { stubs: { RouterLink: { props: ['to'], template: '<a :href="to"><slot /></a>' } } } })
    await flushPromises()
    expect(fetchRules).toHaveBeenCalledTimes(1)
    expect(w.findAll('progress')).toHaveLength(2)
    expect(w.get('progress').attributes('value')).toBe('100')
    expect(w.find('ol').exists()).toBe(false)
    expect(w.get('a[href="/dashboard/messages?unread=1"]').text()).toContain('3')
    w.unmount()
  })
  it('offers retry when the new endpoint fails', async () => {
    get.mockRejectedValue(new Error('offline')); fetchRules.mockRejectedValue(new Error('offline'))
    const w = mount(DashboardNextActions)
    await flushPromises()
    expect(w.find('[role="alert"] button').exists()).toBe(true)
    w.unmount()
  })
})
