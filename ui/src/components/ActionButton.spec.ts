import { describe, it, expect } from 'vitest'
import { mount, RouterLinkStub } from '@vue/test-utils'
import ActionButton from './ActionButton.vue'

const global = { stubs: { RouterLink: RouterLinkStub } }

describe('ActionButton', () => {
  it('cesta v aplikácii je RouterLink', () => {
    const wrapper = mount(ActionButton, { props: { to: '/dashboard/events/1/tickets', label: 'Lístky' }, global })

    expect(wrapper.findComponent(RouterLinkStub).props('to')).toBe('/dashboard/events/1/tickets')
    expect(wrapper.text()).toContain('Lístky')
  })

  it('adresa mimo aplikácie sa otvára v novej karte a nesie šípku', () => {
    const wrapper = mount(ActionButton, { props: { href: '/podujatia/1', label: 'Verejná stránka' }, global })

    const link = wrapper.get('a')
    expect(link.attributes('target')).toBe('_blank')
    expect(link.attributes('rel')).toBe('noopener')
    // Ikona pred textom nie je, šípka „otvorí sa inde" áno.
    expect(wrapper.findAllComponents({ name: 'AppIcon' })).toHaveLength(1)
  })

  it('bez cieľa je to tlačidlo, ktoré emituje klik', async () => {
    const wrapper = mount(ActionButton, { props: { label: 'Check-in' }, global })

    expect(wrapper.get('button').attributes('type')).toBe('button')
    await wrapper.get('button').trigger('click')
    expect(wrapper.emitted('click')).toHaveLength(1)
  })

  it('zablokovaný odkaz sa stane vypnutým tlačidlom, nie klikateľnou navigáciou', () => {
    const wrapper = mount(ActionButton, { props: { to: '/dashboard', label: 'Lístky', disabled: true }, global })

    expect(wrapper.findComponent(RouterLinkStub).exists()).toBe(false)
    expect(wrapper.get('button').attributes('disabled')).toBeDefined()
  })

  it('variant určuje triedy, vlastná trieda volajúceho sa zachová', () => {
    const feature = mount(ActionButton, { props: { to: '/x', variant: 'feature' }, global, attrs: { class: 'ml-auto' } })
    expect(feature.classes()).toEqual(expect.arrayContaining(['action-btn', 'action-btn-feature', 'ml-auto']))

    const tab = mount(ActionButton, { props: { to: '/x', variant: 'tab', active: true }, global })
    expect(tab.classes()).toEqual(expect.arrayContaining(['nav-tab', 'active']))
    expect(tab.classes()).not.toContain('action-btn')
  })
})
