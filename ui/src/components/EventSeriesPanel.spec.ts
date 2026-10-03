import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'

const { eventOccurrences } = vi.hoisted(() => ({ eventOccurrences: vi.fn() }))
vi.mock('@/api/events', () => ({ eventOccurrences, addEventOccurrence: vi.fn(), detachEventFromSeries: vi.fn() }))
vi.mock('vue-router', () => ({ useRouter: () => ({ push: vi.fn() }) }))
import EventSeriesPanel from './EventSeriesPanel.vue'

describe('EventSeriesPanel', () => {
  beforeEach(() => eventOccurrences.mockReset())

  it('shows a recoverable loading error and disables creation until retry succeeds', async () => {
    eventOccurrences.mockRejectedValueOnce(new Error('offline')).mockResolvedValueOnce([])
    const wrapper = mount(EventSeriesPanel, {
      props: { eventId: 1, prefix: '/dashboard', canAdd: true },
      global: { stubs: { RouterLink: true } },
    })
    await flushPromises()
    expect(wrapper.find('[role="alert"]').exists()).toBe(true)
    expect(wrapper.find('button').attributes('disabled')).toBeDefined()
    await wrapper.get('[role="alert"] button').trigger('click')
    await flushPromises()
    expect(wrapper.find('[role="alert"]').exists()).toBe(false)
    expect(wrapper.find('button').attributes('disabled')).toBeUndefined()
    wrapper.unmount()
  })
})
