import { describe, it, expect, beforeAll } from 'vitest'
import { mount, RouterLinkStub } from '@vue/test-utils'
import { setLocale } from '@/i18n'
import CanalRowDetail from './CanalRowDetail.vue'
import VenueRowDetail from './VenueRowDetail.vue'

const global = { stubs: { RouterLink: RouterLinkStub } }

// jsdom hlási anglické `navigator.languages`, takže by sa inak testovali
// anglické tvary — a práve na slovenčine sa dá overiť pluralizácia s `few`.
beforeAll(() => setLocale('sk'))

function facts(wrapper: { findAll: (s: string) => { text: () => string }[] }) {
  return wrapper.findAll('.row-facts > span').map(s => s.text())
}

describe('CanalRowDetail', () => {
  it('vyskladá počty aj dátum vzniku do jedného riadku faktov', () => {
    const wrapper = mount(CanalRowDetail, {
      props: {
        eventsCount: 1,
        venuesCount: 3,
        membersCount: 0,
        createdAt: '1. 2. 2026',
        indexPath: '/admin/canals',
      },
      global,
    })

    // Tvar slova podľa počtu: 1 → one, 3 → few, 0 → many.
    expect(facts(wrapper)).toEqual([
      '1 event',
      '3 miesta',
      '0 členov',
      'vytvorený 1. 2. 2026',
    ])
  })

  it('chýbajúci počet nekreslí ako nulu', () => {
    const wrapper = mount(CanalRowDetail, {
      props: { eventsCount: 0, indexPath: '/admin/canals' },
      global,
    })

    expect(facts(wrapper)).toEqual(['0 eventov'])
  })

  it('chip obce vedie na ten istý filter ako bočný prehľad', () => {
    const wrapper = mount(CanalRowDetail, {
      props: { municipalityId: 42, municipalityName: 'Košice', indexPath: '/admin/canals' },
      global,
    })

    expect(wrapper.getComponent(RouterLinkStub).props('to')).toEqual({
      path: '/admin/canals',
      query: { municipality: 42 },
    })
  })

  it('bez firmy ukáže aspoň typ identity', () => {
    const wrapper = mount(CanalRowDetail, {
      props: { identityModeLabel: 'Osobný', indexPath: '/admin/canals' },
      global,
    })

    expect(wrapper.get('.row-chip-org').text()).toBe('Osobný')
  })

  it('firma prebije typ identity — je konkrétnejšia', () => {
    const wrapper = mount(CanalRowDetail, {
      props: { organizationName: 'Kultúra s.r.o.', identityModeLabel: 'Firemný', indexPath: '/admin/canals' },
      global,
    })

    expect(wrapper.get('.row-chip-org').text()).toBe('Kultúra s.r.o.')
  })
})

describe('VenueRowDetail', () => {
  const canal = (id: number) => ({ id, name: `Kanál ${id}`, isOwner: false })

  it('kanály vedú na svoj detail v tom istom rozsahu', () => {
    const wrapper = mount(VenueRowDetail, {
      props: {
        canals: [canal(7)],
        indexPath: '/dashboard/venues',
        canalPrefix: '/dashboard/canals',
      },
      global,
    })

    expect(wrapper.get('.row-chip-canal').text()).toBe('Kanál 7')
    expect(wrapper.getComponent(RouterLinkStub).props('to')).toBe('/dashboard/canals/7')
  })

  it('nad tri kanály zvyšok zhrnie do počítadla', () => {
    const wrapper = mount(VenueRowDetail, {
      props: {
        canals: [canal(1), canal(2), canal(3), canal(4), canal(5)],
        indexPath: '/admin/venues',
        canalPrefix: '/admin/canals',
      },
      global,
    })

    expect(wrapper.findAll('.row-chip-canal')).toHaveLength(3)
    const chips = wrapper.findAll('.row-chip')
    expect(chips[chips.length - 1]!.text()).toBe('+2 ďalších')
  })

  it('nulová kapacita nie je fakt — miesto ju jednoducho nemá vyplnenú', () => {
    const wrapper = mount(VenueRowDetail, {
      props: {
        eventsCount: 2,
        capacity: 0,
        category: 'kultúrny dom',
        indexPath: '/admin/venues',
        canalPrefix: '/admin/canals',
      },
      global,
    })

    expect(facts(wrapper)).toEqual(['2 eventy', 'kultúrny dom'])
  })
})
