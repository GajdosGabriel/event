import { describe, it, expect, beforeAll, vi } from 'vitest'
import { mount, RouterLinkStub } from '@vue/test-utils'
import { setLocale } from '@/i18n'

vi.mock('@/api/index', () => ({ default: { get: vi.fn(), post: vi.fn(), delete: vi.fn() } }))
vi.mock('vue-router', () => ({ useRouter: () => ({ push: vi.fn() }) }))

import ResourceActionsMenu from './ResourceActionsMenu.vue'
import type { ModelPermissions } from '@/types'

beforeAll(() => setLocale('sk'))

const NONE: ModelPermissions = {
  view: false,
  update: false,
  publish: false,
  unpublish: false,
  delete: false,
  archive: false,
  unarchive: false,
  duplicate: false,
  restore: false,
}

/**
 * Menu sa otvára cez teleport do `document.body`, takže položky sa hľadajú
 * tam, nie vo wrapperi.
 */
function openMenu(permissions: Partial<ModelPermissions>, props: Record<string, unknown> = {}) {
  document.body.innerHTML = ''
  const wrapper = mount(ResourceActionsMenu, {
    props: {
      resource: 'venue' as const,
      scope: 'dashboard' as const,
      item: { id: 5, permissions: { ...NONE, ...permissions } },
      ...props,
    },
    global: { stubs: { RouterLink: RouterLinkStub } },
    attachTo: document.body,
  })
  return wrapper
}

async function itemLabels(permissions: Partial<ModelPermissions>, props: Record<string, unknown> = {}) {
  const wrapper = openMenu(permissions, props)
  const trigger = wrapper.find('button[aria-label="Akcie"]')
  if (!trigger.exists()) return null
  await trigger.trigger('click')
  return Array.from(document.querySelectorAll('.row-menu-item')).map(el => el.textContent?.trim())
}

describe('ResourceActionsMenu — položky riadi policy', () => {
  it('ponúkne presne tie akcie, na ktoré prišlo právo', async () => {
    expect(await itemLabels({ view: true, update: true, publish: true, delete: true }))
      .toEqual(['Zobraziť', 'Upraviť', 'Publikovať', 'Zmazať'])
  })

  it('zamknuté stiahnutie z výpisu ani mazanie sa neponúka — ani zošednuté', async () => {
    // Publikované miesto, ktoré už použilo podujatie: policy `unpublish`
    // aj `delete` zamietla, takže v menu ostane len to, čo sa dá spraviť.
    expect(await itemLabels({ view: true, update: true }))
      .toEqual(['Zobraziť', 'Upraviť'])
  })

  it('zmazaný záznam ponúka obnovu namiesto mazania', async () => {
    expect(await itemLabels({ view: true, restore: true }))
      .toEqual(['Zobraziť', 'Obnoviť'])
  })

  it('publikovaný záznam s právom na stiahnutie ponúka opačný smer', async () => {
    expect(await itemLabels({ view: true, unpublish: true }))
      .toEqual(['Zobraziť', 'Zrušiť publikovanie'])
  })

  it('bez jedinej povolenej akcie sa menu vôbec nekreslí', async () => {
    expect(await itemLabels({})).toBeNull()
  })

  it('detail neponúka „Zobraziť" sám na seba ani publikovanie, keď ho rieši formulár', async () => {
    expect(await itemLabels(
      { view: true, update: true, publish: true },
      { showView: false, showPublish: false },
    )).toEqual(['Upraviť'])
  })

  it('archivované podujatie ponúka kópiu a návrat z archívu', async () => {
    expect(await itemLabels(
      { view: true, duplicate: true, unarchive: true },
      { resource: 'event' },
    )).toEqual(['Zobraziť', 'Kopírovať', 'Vrátiť z archívu'])
  })
})
