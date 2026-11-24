import http from './index'

/** Stav jedného poľa v analýze plagátu — čo AI našla a čo nie. */
export type PosterFieldStatus = 'found' | 'missing' | 'guessed'

export interface PosterField {
  key: string
  label: string
  value: string | null
  status: PosterFieldStatus
  required: boolean
  note: string | null
  preview: boolean
}

export interface PosterMatch {
  id: number
  name: string
  slug?: string
}

export interface PosterAnalysis {
  fields: PosterField[]
  found_count: number
  total_count: number
  missing_required: string[]
  can_save: boolean
  matches: { canal: PosterMatch | null; venue: PosterMatch | null }
  source: {
    kind: string
    page_count: number
    text_length: number
    has_text_layer: boolean
    used_vision: boolean
  }
  notice: string | null
}

export interface PosterSuggestion {
  title?: string | null
  start_at?: string | null
  end_at?: string | null
  email?: string | null
  phone?: string | null
  /** Z predošlej opravy (overrides), ak si človek už raz vybral. */
  venue_id?: number | null
  canal_id?: number | null
  venue?: { name?: string | null; street_and_number?: string | null; city?: string | null } | null
  organizer?: { name?: string | null; street_and_number?: string | null; city?: string | null } | null
}

export interface PosterDraft {
  id: string
  /** Vracia sa iba pri nahratí — ďalej si ho drží klient. */
  token: string | null
  email: string | null
  source_kind: string
  original_filename: string | null
  expires_at: string | null
  claimed: boolean
  event_id: number | null
  analysis: PosterAnalysis
  suggestion: PosterSuggestion
  description: string | null
}

export interface PosterOverrides {
  title?: string | null
  start_at?: string | null
  end_at?: string | null
  email?: string | null
  phone?: string | null
  description?: string | null
  canal_id?: number | null
  /** Existujúce miesto vybraté zo zoznamu miest obce. */
  venue_id?: number | null
  venue?: { name?: string | null; street_and_number?: string | null; city?: string | null }
  organizer?: { name?: string | null; city?: string | null }
}

/**
 * Analýza beží bez prihlásenia, ale je drahá (OpenAI) — API ju obmedzuje
 * limiterom `ai`, takže 429 je tu očakávaný stav, nie chyba klienta.
 */
export async function analyzePoster(input: { files: File[]; text: string }): Promise<PosterDraft> {
  // Súbory aj sprievodný text idú spolu — AI ich číta ako jednu pozvánku.
  const form = new FormData()
  input.files.forEach(file => form.append('files[]', file))
  if (input.text.trim() !== '') form.append('text', input.text)

  const { data } = await http.post('/poster/analyze', form, {
    headers: { 'Content-Type': 'multipart/form-data' },
    // Vision cez OpenAI trvá aj minútu — default axios timeout by to zabil.
    timeout: 180000,
  })

  return data.draft as PosterDraft
}

/** Adresa, na ktorú sa dá pozvánka poslať e-mailom; null, keď je príjem vypnutý. */
export async function fetchPosterInbox(): Promise<string | null> {
  try {
    const { data } = await http.get('/poster/inbox')
    return typeof data?.email === 'string' && data.email !== '' ? data.email : null
  } catch {
    return null
  }
}

export interface PosterPlaces {
  venues: { id: number; name: string; street: string | null }[]
  canals: { id: number; name: string }[]
}

/** Miesta a organizátori, ktorých už v obci máme — na výber namiesto nového záznamu. */
export async function fetchPosterPlaces(municipalityId: number): Promise<PosterPlaces> {
  try {
    const { data } = await http.get('/poster/places', { params: { municipality_id: municipalityId } })
    return { venues: data?.venues ?? [], canals: data?.canals ?? [] }
  } catch {
    return { venues: [], canals: [] }
  }
}

export async function fetchPosterDraft(id: string, token: string): Promise<PosterDraft> {
  const { data } = await http.get(`/poster/drafts/${id}`, { params: { token } })
  return data.draft as PosterDraft
}

export async function rememberPosterDraft(id: string, token: string, email: string): Promise<string> {
  const { data } = await http.post(`/poster/drafts/${id}/remember`, { token, email })
  return (data.message as string) ?? ''
}

export async function claimPosterDraft(
  id: string,
  token: string,
  overrides: PosterOverrides,
): Promise<{ eventId: number; alreadyClaimed: boolean }> {
  const { data } = await http.post(`/poster/drafts/${id}/claim`, { token, overrides })
  return { eventId: data.event_id as number, alreadyClaimed: Boolean(data.already_claimed) }
}
