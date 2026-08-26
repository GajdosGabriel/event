import { describe, it, expect, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import ResourceFilterBar from './ResourceFilterBar.vue'

const KEY = 'search_history:test'

function seed(...terms: string[]) {
  localStorage.setItem(KEY, JSON.stringify(terms))
}

let wrapper: ReturnType<typeof mount<typeof ResourceFilterBar>>

/** Namontuje filter s ručne dotiahnutým `v-model:search` (ako v stránke). */
function mountBar(search = '') {
  wrapper = mount(ResourceFilterBar, {
    props: {
      historyKey: 'test',
      search,
      'onUpdate:search': (value: string) => wrapper.setProps({ search: value }),
    },
  })
  return wrapper
}

function suggestions() {
  return wrapper.findAll('li button:first-child').map(b => b.text())
}

describe('ResourceFilterBar – história hľadania', () => {
  beforeEach(() => localStorage.clear())

  it('po kliknutí do prázdneho poľa ponúkne celú históriu', async () => {
    seed('divadlo', 'koncert')
    mountBar()

    await wrapper.get('input[type="search"]').trigger('focus')

    expect(suggestions()).toEqual(['divadlo', 'koncert'])
  })

  it('pri písaní nechá len zhody a pri žiadnej sa zavrie', async () => {
    seed('divadlo', 'koncert', 'kontrola')
    mountBar()
    const input = wrapper.get('input[type="search"]')

    await wrapper.setProps({ search: 'kon' })
    await input.trigger('input')
    expect(suggestions()).toEqual(['koncert', 'kontrola'])

    await wrapper.setProps({ search: 'xyz' })
    await input.trigger('input')
    expect(suggestions()).toEqual([])
  })

  it('výber z histórie doplní pole a spustí hľadanie hneď', async () => {
    seed('koncert')
    mountBar()

    await wrapper.get('input[type="search"]').trigger('focus')
    await wrapper.get('li button:first-child').trigger('mousedown')

    expect(wrapper.props('search')).toBe('koncert')
    expect(wrapper.emitted('change')).toHaveLength(1)
  })

  it('šípkou a Enterom sa dá vybrať bez myši', async () => {
    seed('divadlo', 'koncert')
    mountBar()
    const input = wrapper.get('input[type="search"]')

    await input.trigger('focus')
    await input.trigger('keydown', { key: 'ArrowDown' })
    await input.trigger('keydown', { key: 'ArrowDown' })
    await input.trigger('keydown', { key: 'Enter' })

    expect(wrapper.props('search')).toBe('koncert')
  })

  it('Enter uloží napísaný výraz do histórie a hľadá bez čakania', async () => {
    mountBar('festival')

    await wrapper.get('input[type="search"]').trigger('keydown', { key: 'Enter' })

    expect(JSON.parse(localStorage.getItem(KEY) ?? '[]')).toEqual(['festival'])
    expect(wrapper.emitted('change')).toHaveLength(1)
  })

  it('opustenie poľa si výraz zapamätá', async () => {
    mountBar('festival')

    await wrapper.get('input[type="search"]').trigger('blur')

    expect(JSON.parse(localStorage.getItem(KEY) ?? '[]')).toEqual(['festival'])
  })

  it('krížikom sa dá položka z histórie odstrániť', async () => {
    seed('divadlo', 'koncert')
    mountBar()

    await wrapper.get('input[type="search"]').trigger('focus')
    await wrapper.get('li button:last-child').trigger('mousedown')

    expect(suggestions()).toEqual(['koncert'])
    expect(JSON.parse(localStorage.getItem(KEY) ?? '[]')).toEqual(['koncert'])
  })

  it('bez kľúča sa história neukladá ani neponúka', async () => {
    seed('koncert')
    const bar = mount(ResourceFilterBar, { props: { search: 'festival' } })

    await bar.get('input[type="search"]').trigger('focus')
    expect(bar.findAll('li')).toHaveLength(0)

    await bar.get('input[type="search"]').trigger('blur')
    expect(localStorage.getItem('search_history:')).toBeNull()
  })
})
