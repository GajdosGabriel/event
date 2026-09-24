import { describe, it, expect, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import SearchField from './SearchField.vue'

const KEY = 'search_history:test'

function seed(...terms: string[]) {
  localStorage.setItem(KEY, JSON.stringify(terms))
}

let wrapper: ReturnType<typeof mount<typeof SearchField>>

/** Namontuje pole s ručne dotiahnutým `v-model` (ako v stránke). */
function mountField(modelValue = '', props: Record<string, unknown> = { collapsible: false }) {
  wrapper = mount(SearchField, {
    attachTo: document.body,
    props: {
      historyKey: 'test',
      modelValue,
      'onUpdate:modelValue': (value: string) => wrapper.setProps({ modelValue: value }),
      ...props,
    },
  })
  return wrapper
}

function suggestions() {
  return wrapper.findAll('li button:first-child').map(b => b.text())
}

describe('SearchField – zbalenie na ikonu', () => {
  beforeEach(() => localStorage.clear())

  it('bez výrazu je len ikona, po kliknutí sa pole roztiahne', async () => {
    mountField('', {})
    expect(wrapper.find('input[type="search"]').exists()).toBe(false)

    await wrapper.get('button').trigger('click')

    expect(wrapper.find('input[type="search"]').exists()).toBe(true)
    wrapper.unmount()
  })

  it('s výrazom je pole rozbalené hneď', () => {
    mountField('festival', {})
    expect(wrapper.find('input[type="search"]').exists()).toBe(true)
    wrapper.unmount()
  })

  it('prázdne pole sa po opustení zbalí', async () => {
    mountField('', {})
    await wrapper.get('button').trigger('click')

    await wrapper.get('input[type="search"]').trigger('blur')

    expect(wrapper.find('input[type="search"]').exists()).toBe(false)
    wrapper.unmount()
  })
})

describe('SearchField – história hľadania', () => {
  beforeEach(() => localStorage.clear())

  it('po kliknutí do prázdneho poľa ponúkne celú históriu', async () => {
    seed('divadlo', 'koncert')
    mountField()

    await wrapper.get('input[type="search"]').trigger('focus')

    expect(suggestions()).toEqual(['divadlo', 'koncert'])
  })

  it('pri písaní nechá len zhody a pri žiadnej sa zavrie', async () => {
    seed('divadlo', 'koncert', 'kontrola')
    mountField()
    const input = wrapper.get('input[type="search"]')

    await wrapper.setProps({ modelValue: 'kon' })
    await input.trigger('input')
    expect(suggestions()).toEqual(['koncert', 'kontrola'])

    await wrapper.setProps({ modelValue: 'xyz' })
    await input.trigger('input')
    expect(suggestions()).toEqual([])
  })

  it('výber z histórie doplní pole a spustí hľadanie hneď', async () => {
    seed('koncert')
    mountField()

    await wrapper.get('input[type="search"]').trigger('focus')
    await wrapper.get('li button:first-child').trigger('mousedown')

    expect(wrapper.props('modelValue')).toBe('koncert')
    expect(wrapper.emitted('search')).toHaveLength(1)
  })

  it('šípkou a Enterom sa dá vybrať bez myši', async () => {
    seed('divadlo', 'koncert')
    mountField()
    const input = wrapper.get('input[type="search"]')

    await input.trigger('focus')
    await input.trigger('keydown', { key: 'ArrowDown' })
    await input.trigger('keydown', { key: 'ArrowDown' })
    await input.trigger('keydown', { key: 'Enter' })

    expect(wrapper.props('modelValue')).toBe('koncert')
  })

  it('Enter uloží napísaný výraz do histórie a hľadá bez čakania', async () => {
    mountField('festival')

    await wrapper.get('input[type="search"]').trigger('keydown', { key: 'Enter' })

    expect(JSON.parse(localStorage.getItem(KEY) ?? '[]')).toEqual(['festival'])
    expect(wrapper.emitted('search')).toHaveLength(1)
  })

  it('opustenie poľa si výraz zapamätá', async () => {
    mountField('festival')

    await wrapper.get('input[type="search"]').trigger('blur')

    expect(JSON.parse(localStorage.getItem(KEY) ?? '[]')).toEqual(['festival'])
  })

  it('krížikom sa dá položka z histórie odstrániť', async () => {
    seed('divadlo', 'koncert')
    mountField()

    await wrapper.get('input[type="search"]').trigger('focus')
    await wrapper.get('li button:last-child').trigger('mousedown')

    expect(suggestions()).toEqual(['koncert'])
    expect(JSON.parse(localStorage.getItem(KEY) ?? '[]')).toEqual(['koncert'])
  })

  it('bez kľúča sa história neukladá ani neponúka', async () => {
    seed('koncert')
    const field = mount(SearchField, { props: { modelValue: 'festival' } })

    await field.get('input[type="search"]').trigger('focus')
    expect(field.findAll('li')).toHaveLength(0)

    await field.get('input[type="search"]').trigger('blur')
    expect(localStorage.getItem('search_history:')).toBeNull()
  })
})
