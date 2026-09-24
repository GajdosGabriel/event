import { mount, flushPromises } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import SearchableSelect from './SearchableSelect.vue'
const { get } = vi.hoisted(() => ({ get: vi.fn() }))
vi.mock('@/api/index', () => ({ default: { get } }))
const response = (id: number, page = 1, last = 1) => ({ data: { data: [{ id, name: `Venue ${id}` }], meta: { current_page: page, last_page: last } } })
const wrappers: ReturnType<typeof mount>[] = []
function setup(props = {}) {
  const wrapper = mount(SearchableSelect, { props: { modelValue: null, options: [], source: '/dashboard/venues', ...props }, global: { stubs: { Teleport: true } } })
  wrappers.push(wrapper)
  return wrapper
}
beforeEach(() => { get.mockReset(); vi.useFakeTimers() })
afterEach(() => { wrappers.forEach(w => w.unmount()); wrappers.length = 0; vi.useRealTimers() })
describe('remote selection', () => {
  it('finds a record beyond the old limit and keeps its label after another search', async () => {
    get.mockResolvedValue(response(150))
    const w = setup()
    await w.get('button').trigger('click'); await flushPromises()
    await w.get('input').setValue('Venue 150'); await vi.advanceTimersByTimeAsync(300); await flushPromises()
    expect(get).toHaveBeenLastCalledWith('/dashboard/venues', { params: { search: 'Venue 150', per_page: 20, page: 1 } })
    await w.get('[role="option"]').trigger('mousedown')
    await w.setProps({ modelValue: 150 })
    get.mockResolvedValue(response(200))
    await w.get('button').trigger('click'); await flushPromises()
    expect(w.get('button').text()).toContain('Venue 150')
  })
  it('ignores a previous search response even during debounce', async () => {
    let resolve!: (v: ReturnType<typeof response>) => void
    get.mockReturnValueOnce(new Promise(r => { resolve = r }))
    const w = setup()
    await w.get('button').trigger('click')
    await w.get('input').setValue('new')
    resolve(response(1)); await flushPromises()
    expect(w.findAll('[role="option"]')).toHaveLength(0)
    get.mockResolvedValue(response(222))
    await vi.advanceTimersByTimeAsync(300); await flushPromises()
    expect(w.text()).toContain('Venue 222')
  })
  it('loads another page and retries a failure without losing results', async () => {
    get.mockResolvedValueOnce(response(1, 1, 2)).mockRejectedValueOnce(new Error('offline')).mockResolvedValueOnce(response(2, 2, 2))
    const w = setup()
    await w.get('button').trigger('click'); await flushPromises()
    await w.findAll('button').slice(-1)[0]!.trigger('click'); await flushPromises()
    expect(w.find('[role="alert"]').exists()).toBe(true)
    expect(w.text()).toContain('Venue 1')
    await w.get('[role="alert"] button').trigger('click'); await flushPromises()
    expect(w.findAll('[role="option"]')).toHaveLength(2)
  })
  it('keeps local filtering and keyboard selection', async () => {
    const w = setup({ source: undefined, options: [{ id: 1, name: 'Alpha' }, { id: 2, name: 'Beta' }] })
    await w.get('button').trigger('click')
    await w.get('input').setValue('Beta')
    await w.get('input').trigger('keydown.enter')
    expect(w.emitted('update:modelValue')?.[0]).toEqual([2])
    expect(get).not.toHaveBeenCalled()
  })
})
