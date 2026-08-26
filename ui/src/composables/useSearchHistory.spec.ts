import { describe, expect, it, beforeEach } from 'vitest'
import { ref, nextTick } from 'vue'
import { useSearchHistory } from './useSearchHistory'

describe('useSearchHistory', () => {
  beforeEach(() => localStorage.clear())

  it('drží posledné hľadania od najnovšieho', () => {
    const { items, add } = useSearchHistory(ref('event'))
    add('koncert')
    add('divadlo')
    expect(items.value).toEqual(['divadlo', 'koncert'])
  })

  it('nezdvojuje ten istý výraz, len ho posunie na vrch', () => {
    const { items, add } = useSearchHistory(ref('event'))
    add('koncert')
    add('divadlo')
    add('Koncert')
    expect(items.value).toEqual(['Koncert', 'divadlo'])
  })

  it('zahodí rozpísaný tvar, keď používateľ výraz dopíše', () => {
    const { items, add } = useSearchHistory(ref('event'))
    add('bra')
    add('bratislava')
    expect(items.value).toEqual(['bratislava'])
  })

  it('ignoruje prázdny výraz a osamotené medzery', () => {
    const { items, add } = useSearchHistory(ref('event'))
    add('   ')
    expect(items.value).toEqual([])
  })

  it('drží najviac zadaný počet položiek', () => {
    const { items, add } = useSearchHistory(ref('event'), 2)
    add('a1')
    add('b2')
    add('c3')
    expect(items.value).toEqual(['c3', 'b2'])
  })

  it('prežije reload a je oddelená pre každý kľúč', () => {
    useSearchHistory(ref('event')).add('koncert')
    useSearchHistory(ref('venue')).add('kino')

    expect(useSearchHistory(ref('event')).items.value).toEqual(['koncert'])
    expect(useSearchHistory(ref('venue')).items.value).toEqual(['kino'])
  })

  it('prepnutie kľúča načíta históriu druhého výpisu', async () => {
    useSearchHistory(ref('event')).add('koncert')

    const key = ref('venue')
    const { items } = useSearchHistory(key)
    expect(items.value).toEqual([])

    key.value = 'event'
    await nextTick()
    expect(items.value).toEqual(['koncert'])
  })

  it('vie zabudnúť jednu položku aj celú históriu', () => {
    const { items, add, remove, clear } = useSearchHistory(ref('event'))
    add('koncert')
    add('divadlo')

    remove('koncert')
    expect(items.value).toEqual(['divadlo'])

    clear()
    expect(items.value).toEqual([])
    expect(useSearchHistory(ref('event')).items.value).toEqual([])
  })

  it('bez kľúča si nič nepamätá', () => {
    const { items, add } = useSearchHistory(ref(''))
    add('koncert')
    expect(items.value).toEqual([])
  })
})
