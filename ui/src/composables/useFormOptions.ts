import { ref, onMounted } from 'vue'
import http from '@/api/index'

export interface SelectOption { id: number; name: string }
export interface VenueOption extends SelectOption { canalIds: number[] }

export function useFormOptions(scope: 'dashboard' | 'admin') {
  const municipalities = ref<SelectOption[]>([])
  const canals = ref<SelectOption[]>([])
  const venues = ref<VenueOption[]>([])

  async function loadMunicipalities() {
    try {
      const { data } = await http.get(`/${scope}/municipalities/all`)
      municipalities.value = ((data.data ?? data) as Record<string, unknown>[]).map(r => ({
        id: r['id'] as number,
        name: (r['fullname'] ?? r['name']) as string,
      }))
    } catch { /* ignore */ }
  }

  async function loadCanals() {
    try {
      const { data } = await http.get(`/${scope}/canals`, { params: { per_page: 100 } })
      canals.value = ((data.data ?? data) as Record<string, unknown>[]).map(r => ({
        id: r['id'] as number,
        name: r['name'] as string,
      }))
    } catch { /* ignore */ }
  }

  async function loadVenues() {
    try {
      const { data } = await http.get(`/${scope}/venues`, { params: { per_page: 100 } })
      venues.value = ((data.data ?? data) as Record<string, unknown>[]).map(r => ({
        id: r['id'] as number,
        name: r['name'] as string,
        canalIds: ((r['canals_list'] as Record<string, unknown>[] | undefined) ?? [])
          .filter(c => c['status'] === 'published')
          .map(c => c['id'] as number),
      }))
    } catch { /* ignore */ }
  }

  return { municipalities, canals, venues, loadMunicipalities, loadCanals, loadVenues }
}
