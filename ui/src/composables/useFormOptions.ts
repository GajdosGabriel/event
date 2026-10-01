import { computed, ref } from 'vue'
import http from '@/api/index'

export interface SelectOption { id: number; name: string }
/** Obec z číselníka. PSČ nesie preto, aby ho editor adresy vedel predvyplniť. */
export interface MunicipalityOption extends SelectOption { zip: string | null; slug: string | null }

/** Pseudo-obec pre celoslovenské záznamy — pre konkrétne miesto konania nedáva zmysel. */
const NATIONWIDE_SLUG = 'cele-slovensko'
const collator = new Intl.Collator('sk')
export interface VenueOption extends SelectOption { canalIds: number[] }
/** { value, label } z enumu na API — popisky sa prekladajú tam, nie tu. */
export interface EnumOption { value: string; label: string }

export function useFormOptions(scope: 'dashboard' | 'admin') {
  const municipalities = ref<MunicipalityOption[]>([])
  const canals = ref<SelectOption[]>([])
  const venues = ref<VenueOption[]>([])
  const canalIdentityModes = ref<EnumOption[]>([])
  /** Obce vhodné pre konkrétne miesto konania — bez „Celé Slovensko“. */
  const placeMunicipalities = computed(() => municipalities.value.filter(m => m.slug !== NATIONWIDE_SLUG))

  async function loadMunicipalities() {
    try {
      const { data } = await http.get(`/${scope}/municipalities/all`)
      // API vracia obce od najnovšej, takže „Celé Slovensko“ (založené naposledy)
      // stálo navrchu a zvyšok išiel od Ž. Abecedne ich radí až klient.
      municipalities.value = ((data.data ?? data) as Record<string, unknown>[]).map(r => ({
        id: r['id'] as number,
        name: (r['fullname'] ?? r['name']) as string,
        zip: (r['zip'] as string) ?? null,
        slug: (r['slug'] as string) ?? null,
      })).sort((a, b) => collator.compare(a.name, b.name))
    } catch { /* ignore */ }
  }

  async function loadCanals() {
    try {
      const { data } = await http.get(`/${scope}/canals`, { params: { per_page: 20 } })
      canals.value = ((data.data ?? data) as Record<string, unknown>[]).map(r => ({
        id: r['id'] as number,
        name: r['name'] as string,
      }))
    } catch { /* ignore */ }
  }

  async function loadVenues() {
    try {
      const { data } = await http.get(`/${scope}/venues`, { params: { per_page: 20 } })
      venues.value = ((data.data ?? data) as Record<string, unknown>[]).map(r => ({
        id: r['id'] as number,
        name: r['name'] as string,
        canalIds: ((r['canals_list'] as Record<string, unknown>[] | undefined) ?? [])
          .filter(c => c['status'] === 'published')
          .map(c => c['id'] as number),
      }))
    } catch { /* ignore */ }
  }

  async function loadCanalIdentityModes() {
    try {
      const { data } = await http.get(`/${scope}/canals/identity-modes`)
      canalIdentityModes.value = (data.data ?? data) as EnumOption[]
    } catch { /* ignore */ }
  }

  return {
    municipalities,
    placeMunicipalities,
    canals,
    venues,
    canalIdentityModes,
    loadMunicipalities,
    loadCanals,
    loadVenues,
    loadCanalIdentityModes,
  }
}
