import { describe, it, expect, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import ResourceFilterBar from './ResourceFilterBar.vue'
import SearchField from './SearchField.vue'

// Samotné hľadanie a jeho históriu pokrýva SearchField.spec.ts.
describe('ResourceFilterBar – hľadanie', () => {
  beforeEach(() => localStorage.clear())

  it('hľadanie je zbalené na ikonu a posiela históriu podľa kľúča', () => {
    const bar = mount(ResourceFilterBar, { props: { historyKey: 'test' } })

    const field = bar.getComponent(SearchField)
    expect(field.props('historyKey')).toBe('test')
    expect(bar.find('input[type="search"]').exists()).toBe(false)
  })

  it('hľadanie z poľa spustí zmenu filtrov', async () => {
    const bar = mount(ResourceFilterBar, { props: { search: 'festival' } })

    await bar.get('input[type="search"]').trigger('keydown', { key: 'Enter' })

    expect(bar.emitted('change')).toHaveLength(1)
  })

  it('„Zrušiť filtre" vyprázdni výraz', async () => {
    let search = 'festival'
    const bar = mount(ResourceFilterBar, {
      props: { search, 'onUpdate:search': (value: string) => { search = value } },
    })

    const reset = bar.findAll('button').find(b => b.text().includes('1') && !b.attributes('aria-expanded'))!
    await reset.trigger('click')

    expect(search).toBe('')
    expect(bar.emitted('reset')).toHaveLength(1)
  })
})
