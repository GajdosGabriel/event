import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { defineComponent, h, ref } from 'vue'
import { mount } from '@vue/test-utils'
import AddressFieldset from './AddressFieldset.vue'
import SearchableSelect from './SearchableSelect.vue'
import { emptyAddress } from '@/api/address'
import { provideFormValidation } from '@/composables/useFormValidation'
import type { AddressModel } from '@/types'

const MUNICIPALITIES = [
  { id: 1, name: 'Trenčín', zip: '911 01' },
  { id: 2, name: 'Žilina', zip: '010 01' },
]

const loadMunicipalities = vi.fn()

vi.mock('@/composables/useFormOptions', () => ({
  useFormOptions: () => ({
    municipalities: ref(MUNICIPALITIES),
    canals: ref([]),
    venues: ref([]),
    canalIdentityModes: ref([]),
    loadMunicipalities,
    loadCanals: vi.fn(),
    loadVenues: vi.fn(),
    loadCanalIdentityModes: vi.fn(),
  }),
}))

const geocodeAddress = vi.fn()

vi.mock('@/api/address', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@/api/address')>()
  return { ...actual, geocodeAddress: (...args: unknown[]) => geocodeAddress(...args) }
})

/** Formulár okolo poľa — bez neho pole nevie, či už prebehla validácia. */
function mountFieldset(initial: Partial<AddressModel> = {}) {
  const address = ref<AddressModel>({ ...emptyAddress(), ...initial })

  const wrapper = mount(defineComponent({
    setup() {
      provideFormValidation()
      return () => h(AddressFieldset, {
        'scope': 'dashboard',
        'modelValue': address.value,
        'onUpdate:modelValue': (v: AddressModel) => { address.value = v },
      })
    },
  }))

  return { wrapper, address }
}

describe('AddressFieldset', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    geocodeAddress.mockReset()
    geocodeAddress.mockResolvedValue({
      latitude: 48.89, longitude: 18.04, source: 'address', city: 'Trenčín', postcode: '911 01',
    })
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('výber obce doplní PSČ z číselníka', async () => {
    const { wrapper, address } = mountFieldset()

    await wrapper.getComponent(SearchableSelect).vm.$emit('update:modelValue', 1)

    expect(address.value.municipalityId).toBe(1)
    expect(address.value.postcode).toBe('911 01')
  })

  it('ručne dopísané PSČ prežije zmenu obce', async () => {
    const { wrapper, address } = mountFieldset({ postcode: '900 01' })

    await wrapper.getComponent(SearchableSelect).vm.$emit('update:modelValue', 2)

    expect(address.value.postcode).toBe('900 01')
  })

  it('PSČ predošlej obce sa prepíše novým', async () => {
    const { wrapper, address } = mountFieldset({ municipalityId: 1, postcode: '911 01' })

    await wrapper.getComponent(SearchableSelect).vm.$emit('update:modelValue', 2)

    expect(address.value.postcode).toBe('010 01')
  })

  it('po zmene adresy dohľadá polohu a zapíše jej presnosť', async () => {
    const { wrapper, address } = mountFieldset({ municipalityId: 1 })

    await wrapper.get('input').setValue('Hlavná 12')
    await vi.advanceTimersByTimeAsync(700)

    expect(geocodeAddress).toHaveBeenCalledWith('dashboard', expect.objectContaining({
      municipality_id: 1,
      street: 'Hlavná 12',
    }))
    expect(address.value.latitude).toBe(48.89)
    expect(address.value.longitude).toBe(18.04)
    expect(address.value.coordinatesSource).toBe('address')
  })

  it('rýchle písanie pošle jeden dopyt, nie jeden za znak', async () => {
    const { wrapper } = mountFieldset({ municipalityId: 1 })

    const street = wrapper.get('input')
    await street.setValue('H')
    await street.setValue('Hl')
    await street.setValue('Hlavná 12')
    await vi.advanceTimersByTimeAsync(700)

    expect(geocodeAddress).toHaveBeenCalledTimes(1)
  })

  it('zlyhanie geokódera nezhodí formulár ani nezmaže polohu', async () => {
    geocodeAddress.mockRejectedValue(new Error('offline'))
    const { wrapper, address } = mountFieldset({ municipalityId: 1, latitude: 1, longitude: 2 })

    await wrapper.get('input').setValue('Hlavná 12')
    await vi.advanceTimersByTimeAsync(700)

    expect(address.value.latitude).toBe(1)
    expect(address.value.longitude).toBe(2)
  })

  it('bez obce sa geokóder nevolá', async () => {
    const { wrapper } = mountFieldset()

    await wrapper.get('input').setValue('Hlavná 12')
    await vi.advanceTimersByTimeAsync(700)

    expect(geocodeAddress).not.toHaveBeenCalled()
  })
})
