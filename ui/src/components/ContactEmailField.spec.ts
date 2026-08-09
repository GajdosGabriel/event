import { describe, it, expect, vi, beforeEach } from 'vitest'
import { defineComponent, h } from 'vue'
import { mount } from '@vue/test-utils'
import ContactEmailField from './ContactEmailField.vue'
import { provideFormValidation } from '@/composables/useFormValidation'
import { resendContactEmail } from '@/api/contactEmail'

vi.mock('@/api/contactEmail', () => ({
  resendContactEmail: vi.fn(async () => ({ message: 'Poslané.' })),
}))

vi.mock('@/composables/useToast', () => ({
  useToast: () => ({ success: vi.fn(), error: vi.fn() }),
}))

/** Pole žije vo formulári — bez neho nevie, či už prebehla validácia. */
function mountField(props: Record<string, unknown>) {
  return mount(defineComponent({
    setup() {
      provideFormValidation()
      return () => h(ContactEmailField, {
        target: 'canal',
        modelValue: 'kontakt@divadlo.sk',
        savedEmail: 'kontakt@divadlo.sk',
        targetId: 7,
        ...props,
      })
    },
  }))
}

describe('ContactEmailField', () => {
  beforeEach(() => vi.mocked(resendContactEmail).mockClear())

  it('overená adresa je zelená a neponúka opakované odoslanie', () => {
    const wrapper = mountField({ state: { verified: true } })

    expect(wrapper.text()).toContain('Adresa je overená.')
    expect(wrapper.find('button').exists()).toBe(false)
  })

  it('neoverená adresa to povie a ponúkne poslať znova', () => {
    const wrapper = mountField({ state: { verified: false, pending: true, canResend: true } })

    expect(wrapper.text()).toContain('Neoverená')
    expect(wrapper.get('button').text()).toContain('Poslať overenie znova')
  })

  it('počas čakacej lehoty je tlačidlo neaktívne', () => {
    const wrapper = mountField({ state: { verified: false, pending: true, canResend: false } })

    expect(wrapper.get('button').attributes('disabled')).toBeDefined()
  })

  // Kým sa zmena neuloží, stav z API sa jej netýka — overená adresa sa nesmie
  // tváriť, že platí aj pre práve dopísanú.
  it('rozpísaná zmena adresy prestane hlásiť overené', () => {
    const wrapper = mountField({
      modelValue: 'novy@divadlo.sk',
      savedEmail: 'kontakt@divadlo.sk',
      state: { verified: true },
    })

    expect(wrapper.text()).not.toContain('Adresa je overená.')
    expect(wrapper.text()).toContain('Po uložení')
    expect(wrapper.find('button').exists()).toBe(false)
  })

  it('prázdne pole nezobrazuje žiadny stav', () => {
    const wrapper = mountField({ modelValue: '', savedEmail: '', state: null })

    expect(wrapper.text()).not.toContain('overená')
    expect(wrapper.find('button').exists()).toBe(false)
  })

  it('kliknutie pošle overenie pre správny model', async () => {
    const wrapper = mountField({ state: { verified: false, pending: false, canResend: true } })

    await wrapper.get('button').trigger('click')

    expect(resendContactEmail).toHaveBeenCalledWith('canal', 7)
  })

  it('pri zakladaní záznamu sa poslať znova neponúka', () => {
    const wrapper = mountField({ targetId: null, state: null })

    expect(wrapper.find('button').exists()).toBe(false)
  })
})
