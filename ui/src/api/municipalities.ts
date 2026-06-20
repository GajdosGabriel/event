import http from './index'
import type { LookupOption } from '@/types'

export async function listMunicipalities(): Promise<LookupOption[]> {
  const { data } = await http.get('/dashboard/municipalities/all')
  return (data.data ?? data) as LookupOption[]
}
