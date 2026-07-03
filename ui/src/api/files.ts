import http from './index'

export interface FileItem {
  id: number
  name: string
  extension: string
  url: string
  thumbUrl: string | null
  largeUrl: string | null
  mimeType: string
  sizeBytes: number
  isPrimary: boolean
  sortOrder: number
  deletedAt: string | null
}

const PLACEHOLDER_MARKER = 'document-placeholder'

function resolveImageUrl(...candidates: (string | null | undefined)[]): string | null {
  for (const c of candidates) {
    if (c && !c.includes(PLACEHOLDER_MARKER)) return c
  }
  return null
}

function mapFile(raw: Record<string, unknown>): FileItem {
  const mimeType    = (raw['mime_type'] as string) ?? ''
  // A raw (non-image) document can never itself be used as an <img> src — only fall
  // back to the original file's URL when it's actually a raster image.
  const isImage     = mimeType.startsWith('image/')
  const originalUrl = (raw['original_file_url'] as string) ?? null
  const imageFallback = isImage ? originalUrl : null
  const thumbRaw    = (raw['thumb_image_url'] as string) ?? null
  const largeRaw    = (raw['large_image_url'] as string) ?? null

  return {
    id: raw['id'] as number,
    name: (raw['name'] as string) ?? '',
    extension: (raw['extension'] as string) ?? '',
    url: originalUrl ?? '',
    thumbUrl: resolveImageUrl(thumbRaw, imageFallback),
    largeUrl: resolveImageUrl(largeRaw, imageFallback, thumbRaw),
    mimeType,
    sizeBytes: (raw['size'] as number) ?? (raw['size_bytes'] as number) ?? 0,
    isPrimary: (raw['is_primary'] as boolean) ?? false,
    sortOrder: (raw['sort_order'] as number) ?? 0,
    deletedAt: (raw['deleted_at'] as string) ?? null,
  }
}

export async function listFiles(params: { fileable_type: string; fileable_id: number }): Promise<FileItem[]> {
  const { data } = await http.get('/dashboard/files', { params })
  return ((data.data ?? data) as Record<string, unknown>[]).map(mapFile)
}

export interface AdminFileParams {
  fileable_type?: string
  fileable_id?: number
  search?: string
  with_trashed?: boolean
  page?: number
}

export interface PaginatedFiles {
  data: FileItem[]
  total: number
  currentPage: number
  lastPage: number
}

export async function listAdminFiles(params: AdminFileParams = {}): Promise<PaginatedFiles> {
  const { data } = await http.get('/admin/files', { params })
  return {
    data: ((data.data ?? []) as Record<string, unknown>[]).map(mapFile),
    total: (data.meta?.total ?? data.total ?? 0) as number,
    currentPage: (data.meta?.current_page ?? data.current_page ?? 1) as number,
    lastPage: (data.meta?.last_page ?? data.last_page ?? 1) as number,
  }
}

export async function listPublicEventFiles(eventId: number): Promise<FileItem[]> {
  const { data } = await http.get(`/events/${eventId}/files`)
  return ((data.data ?? data) as Record<string, unknown>[]).map(mapFile)
}

export async function listPublicVenueFiles(venueId: number): Promise<FileItem[]> {
  const { data } = await http.get(`/venues/${venueId}/files`)
  return ((data.data ?? data) as Record<string, unknown>[]).map(mapFile)
}

export async function uploadFiles(formData: FormData): Promise<FileItem[]> {
  const { data } = await http.post('/dashboard/files', formData)
  return ((data.data ?? data) as Record<string, unknown>[]).map(mapFile)
}

export async function updateFile(id: number, payload: { is_primary?: boolean; sort_order?: number; meta?: unknown }): Promise<FileItem> {
  const { data } = await http.put(`/dashboard/files/${id}`, payload)
  return mapFile((data.data ?? data) as Record<string, unknown>)
}

export async function reorderFiles(items: { id: number; sort_order: number }[]): Promise<void> {
  await http.post('/dashboard/files/reorder', { items })
}

export async function deleteFile(id: number, scope: 'dashboard' | 'admin' = 'dashboard'): Promise<void> {
  await http.delete(`/${scope}/files/${id}`)
}

export async function restoreFile(id: number, scope: 'dashboard' | 'admin' = 'dashboard'): Promise<void> {
  await http.post(`/${scope}/files/${id}/restore`)
}
