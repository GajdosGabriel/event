import http from './index'

export interface FileItem {
  id: number
  name: string
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
  const originalUrl = (raw['original_file_url'] as string) ?? null
  const thumbRaw    = (raw['thumb_image_url'] as string) ?? null
  const largeRaw    = (raw['large_image_url'] as string) ?? null

  return {
    id: raw['id'] as number,
    name: (raw['name'] as string) ?? '',
    url: originalUrl ?? '',
    thumbUrl: resolveImageUrl(thumbRaw, originalUrl),
    largeUrl: resolveImageUrl(largeRaw, originalUrl, thumbRaw),
    mimeType: (raw['mime_type'] as string) ?? '',
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
