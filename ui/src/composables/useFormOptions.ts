import { ref, onMounted } from 'vue'
import http from '@/api/index'

export interface SelectOption { id: number; name: string }

export function useFormOptions(scope: 'dashboard' | 'admin') {
  const municipalities = ref<SelectOption[]>([])
  const canals = ref<SelectOption[]>([])
  const venues = ref<SelectOption[]>([])

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
      const { data } = await http.get(`/${scope}/canals`)
      canals.value = ((data.data ?? data) as Record<string, unknown>[]).map(r => ({
        id: r['id'] as number,
        name: r['name'] as string,
      }))
    } catch { /* ignore */ }
  }

  async function loadVenues() {
    try {
      const { data } = await http.get(`/${scope}/venues`)
      venues.value = ((data.data ?? data) as Record<string, unknown>[]).map(r => ({
        id: r['id'] as number,
        name: r['name'] as string,
      }))
    } catch { /* ignore */ }
  }

  return { municipalities, canals, venues, loadMunicipalities, loadCanals, loadVenues }
}
