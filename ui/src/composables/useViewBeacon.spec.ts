import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { defineComponent, h } from 'vue'
import { mount } from '@vue/test-utils'

vi.mock('@/api/views', () => ({ recordView: vi.fn() }))

import { recordView } from '@/api/views'
import { useViewBeacon, VIEW_DELAY_MS } from './useViewBeacon'

let visibility: DocumentVisibilityState = 'visible'

function setVisibility(state: DocumentVisibilityState) {
  visibility = state
  document.dispatchEvent(new Event('visibilitychange'))
}

function mountBeacon() {
  let api!: ReturnType<typeof useViewBeacon>
  const wrapper = mount(defineComponent({
    setup() {
      api = useViewBeacon('events')
      return () => h('div')
    },
  }))
  return { wrapper, start: api.start }
}

describe('useViewBeacon', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    visibility = 'visible'
    vi.spyOn(document, 'visibilityState', 'get').mockImplementation(() => visibility)
    vi.mocked(recordView).mockClear()
  })

  afterEach(() => {
    vi.useRealTimers()
    vi.restoreAllMocks()
  })

  it('records the view only after the page was visible long enough', () => {
    const { start } = mountBeacon()
    start(7)

    vi.advanceTimersByTime(VIEW_DELAY_MS - 1)
    expect(recordView).not.toHaveBeenCalled()

    vi.advanceTimersByTime(1)
    expect(recordView).toHaveBeenCalledExactlyOnceWith('events', 7)
  })

  it('does not count time spent in a hidden tab', () => {
    const { start } = mountBeacon()
    start(7)

    vi.advanceTimersByTime(1000)
    setVisibility('hidden')
    vi.advanceTimersByTime(10_000)
    expect(recordView).not.toHaveBeenCalled()

    setVisibility('visible')
    vi.advanceTimersByTime(VIEW_DELAY_MS - 1000)
    expect(recordView).toHaveBeenCalledOnce()
  })

  it('sends nothing when the visitor leaves early', () => {
    const { wrapper, start } = mountBeacon()
    start(7)

    vi.advanceTimersByTime(1000)
    wrapper.unmount()
    vi.advanceTimersByTime(VIEW_DELAY_MS)

    expect(recordView).not.toHaveBeenCalled()
  })

  it('ignores automated browsers', () => {
    // jsdom vlastnosť nemá, takže ju nemá ani čím špehovať.
    Object.defineProperty(navigator, 'webdriver', { value: true, configurable: true })
    try {
      const { start } = mountBeacon()
      start(7)

      vi.advanceTimersByTime(VIEW_DELAY_MS)

      expect(recordView).not.toHaveBeenCalled()
    } finally {
      delete (navigator as { webdriver?: boolean }).webdriver
    }
  })
})
