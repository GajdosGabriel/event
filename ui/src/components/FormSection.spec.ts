import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import FormSection from './FormSection.vue'

describe('FormSection', () => {
  it('zabalená sekcia ukazuje len nadpis a zhrnutie', () => {
    const wrapper = mount(FormSection, {
      props: { title: 'Fakturačné údaje', note: 'Chýba: e-mail na faktúry' },
      slots: { default: '<input class="inner" />' },
    })

    expect(wrapper.get('details').attributes('open')).toBeUndefined()
    expect(wrapper.get('summary').text()).toContain('Chýba: e-mail na faktúry')
  })

  it('defaultOpen otvorí sekciu hneď', () => {
    const wrapper = mount(FormSection, { props: { title: 'Profil', defaultOpen: true } })

    expect(wrapper.get('details').attributes('open')).toBeDefined()
  })

  it('chyba v zabalenej sekcii ju otvorí za človeka', async () => {
    const wrapper = mount(FormSection, { props: { title: 'Fakturačné údaje' } })
    expect(wrapper.get('details').attributes('open')).toBeUndefined()

    await wrapper.setProps({ forceOpen: true })

    expect(wrapper.get('details').attributes('open')).toBeDefined()
  })

  it('po oprave chyby sekcia ostane otvorená', async () => {
    const wrapper = mount(FormSection, { props: { title: 'Fakturačné údaje', forceOpen: true } })

    await wrapper.setProps({ forceOpen: false })

    expect(wrapper.get('details').attributes('open')).toBeDefined()
  })
})
