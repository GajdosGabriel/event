import { describe, it, expect, vi } from 'vitest'
import { defineComponent, h } from 'vue'
import { mount } from '@vue/test-utils'
import { useWindowKeydown } from './useWindowKeydown'

function mountWithHandler(handler: (e: KeyboardEvent) => void) {
  const Component = defineComponent({
    setup() {
      useWindowKeydown(handler)
      return () => h('div')
    },
  })

  return mount(Component, { attachTo: document.body })
}

describe('useWindowKeydown', () => {
  it('zachytí klávesu stlačenú kdekoľvek na window', () => {
    const handler = vi.fn()
    mountWithHandler(handler)

    window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))

    expect(handler).toHaveBeenCalledTimes(1)
    expect(handler.mock.calls[0][0].key).toBe('Escape')
  })

  it('po odmountovaní už nereaguje — listener sa odregistruje', () => {
    const handler = vi.fn()
    const wrapper = mountWithHandler(handler)

    wrapper.unmount()
    window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))

    expect(handler).not.toHaveBeenCalled()
  })

  it('funguje aj keď nie je fokusovaný žiadny prvok komponentu', () => {
    // Presne kvôli tomuto composable existuje: @keydown na nefokusovanom
    // <div> sa nikdy nespustí a Vue nepozná modifikátor `.window`.
    const handler = vi.fn()
    mountWithHandler(handler)

    document.body.focus()
    window.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowRight' }))

    expect(handler).toHaveBeenCalledTimes(1)
  })
})
