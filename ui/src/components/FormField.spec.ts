import { describe, it, expect } from 'vitest'
import { defineComponent, h, ref } from 'vue'
import { mount } from '@vue/test-utils'
import FormField from './FormField.vue'
import { provideFormValidation, type FormValidation } from '@/composables/useFormValidation'
import type { FieldValue } from '@/types'

/** Formulár okolo poľa — bez neho pole nevie, či už prebehla validácia. */
function mountInForm(props: Record<string, unknown>, slots: Record<string, unknown> = {}) {
  let validation!: FormValidation

  const wrapper = mount(defineComponent({
    setup() {
      validation = provideFormValidation()
      return () => h(FormField, props, slots)
    },
  }))

  return { wrapper, validation: () => validation }
}

describe('FormField', () => {
  it('prázdne povinné pole pred validáciou nefarbí', () => {
    const { wrapper } = mountInForm({ label: 'Názov', required: true, modelValue: '' })

    expect(wrapper.get('input').classes()).not.toContain('invalid')
    // Natívne `required` ostáva — červenú pred odoslaním rieši CSS :user-invalid.
    expect(wrapper.get('input').attributes('required')).toBeDefined()
  })

  it('po pokuse o odoslanie prázdne povinné pole zčervenie', async () => {
    const { wrapper, validation } = mountInForm({ label: 'Názov', required: true, modelValue: '' })

    validation().markValidated()
    await wrapper.vm.$nextTick()

    expect(wrapper.get('input').classes()).toContain('invalid')
  })

  it('vyplnené povinné pole ostáva čisté aj po validácii', async () => {
    const { wrapper, validation } = mountInForm({ label: 'Názov', required: true, modelValue: 'Ples' })

    validation().markValidated()
    await wrapper.vm.$nextTick()

    expect(wrapper.get('input').classes()).not.toContain('invalid')
  })

  it('chyba zo servera zafarbí pole hneď, aj bez validácie', () => {
    const { wrapper } = mountInForm({ label: 'Názov', modelValue: 'Ples', error: 'Názov je už obsadený.' })

    expect(wrapper.get('input').classes()).toContain('invalid')
    expect(wrapper.get('.field-error').text()).toBe('Názov je už obsadený.')
  })

  it('nepovinné prázdne pole nezčervenie ani po validácii', async () => {
    const { wrapper, validation } = mountInForm({ label: 'Telefón', modelValue: '' })

    validation().markValidated()
    await wrapper.vm.$nextTick()

    expect(wrapper.get('input').classes()).not.toContain('invalid')
  })

  it('vlastný `validated` prebije formulár — modál validuje sám za seba', async () => {
    const { wrapper, validation } = mountInForm({
      label: 'Názov',
      required: true,
      modelValue: '',
      validated: false,
    })

    // Hlavný formulár sa už odoslal, modál nad ním ešte nie.
    validation().markValidated()
    await wrapper.vm.$nextTick()

    expect(wrapper.get('input').classes()).not.toContain('invalid')
  })

  it('vlastný ovládač dostane `invalid` cez slot', async () => {
    const { wrapper, validation } = mountInForm(
      { label: 'Obec', required: true, modelValue: null },
      { default: (slotProps: { invalid: boolean }) => h('span', { class: { invalid: slotProps.invalid } }, 'select') },
    )

    expect(wrapper.get('span.form-required')).toBeTruthy()
    expect(wrapper.find('span.invalid').exists()).toBe(false)

    validation().markValidated()
    await wrapper.vm.$nextTick()

    expect(wrapper.find('span.invalid').exists()).toBe(true)
  })

  it('`<option>` zapísané do značky idú do selectu, nenahradia ho', () => {
    const { wrapper } = mountInForm(
      { label: 'Stav', type: 'select', modelValue: 'draft' },
      { default: () => [h('option', { value: 'draft' }, 'Koncept'), h('option', { value: 'published' }, 'Publikovaný')] },
    )

    const select = wrapper.get('select')
    expect(select.findAll('option')).toHaveLength(2)
    expect((select.element as HTMLSelectElement).value).toBe('draft')
  })

  it('nezaškrtnuté povinné políčko zčervenie až po validácii', async () => {
    const { wrapper, validation } = mountInForm({
      label: 'Súhlasím s podmienkami',
      type: 'checkbox',
      required: true,
      modelValue: false,
    })

    expect(wrapper.get('label').classes()).not.toContain('invalid')
    expect(wrapper.get('input').attributes('required')).toBeDefined()

    validation().markValidated()
    await wrapper.vm.$nextTick()

    expect(wrapper.get('label').classes()).toContain('invalid')
  })

  it('zaškrtnuté povinné políčko ostáva čisté', async () => {
    const { wrapper, validation } = mountInForm({
      label: 'Súhlasím s podmienkami',
      type: 'checkbox',
      required: true,
      modelValue: true,
    })

    validation().markValidated()
    await wrapper.vm.$nextTick()

    expect(wrapper.get('label').classes()).not.toContain('invalid')
  })

  it('číselné pole vracia číslo, prázdne vracia null', async () => {
    const model = ref<number | null>(null)

    const wrapper = mount(defineComponent({
      setup: () => () => h(FormField, {
        type: 'number',
        label: 'Kapacita',
        modelValue: model.value,
        'onUpdate:modelValue': (v: FieldValue) => { model.value = typeof v === 'number' ? v : null },
      }),
    }))

    await wrapper.get('input').setValue('42')
    expect(model.value).toBe(42)

    await wrapper.get('input').setValue('')
    expect(model.value).toBeNull()
  })
})
