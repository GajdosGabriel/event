import { beforeEach, describe, expect, it, vi } from 'vitest'
import { useFormOptions } from './useFormOptions'

const { get } = vi.hoisted(() => ({ get: vi.fn() }))
vi.mock('@/api/index', () => ({ default: { get } }))

beforeEach(() => vi.resetAllMocks())

describe('useFormOptions municipalities', () => {
  it('sorts alphabetically and keeps "Celé Slovensko" out of the venue list', async () => {
    // API ich vracia od najnovšej: pseudo-obec navrchu, potom od Ž po A.
    get.mockResolvedValue({ data: { data: [
      { id: 9, fullname: 'Celé Slovensko', slug: 'cele-slovensko' },
      { id: 8, fullname: 'Žilina', slug: 'zilina' },
      { id: 7, fullname: 'Zvolen', slug: 'zvolen' },
      { id: 6, fullname: 'Banská Bystrica', slug: 'banska-bystrica' },
    ] } })

    const { municipalities, placeMunicipalities, loadMunicipalities } = useFormOptions('dashboard')
    await loadMunicipalities()

    expect(municipalities.value.map(m => m.name)).toEqual(['Banská Bystrica', 'Celé Slovensko', 'Zvolen', 'Žilina'])
    expect(placeMunicipalities.value.map(m => m.name)).toEqual(['Banská Bystrica', 'Zvolen', 'Žilina'])
  })
})
