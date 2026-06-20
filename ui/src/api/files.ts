import http from './index'

export interface FileItem {
  id: number
  name: string
  url: string
  previewUrl: string | null
  mimeType: string
  sizeBytes: number
  isPrimary: boolean
  deletedAt: string | null
}

function mapFile(raw: Record<string, unknown>): FileItem {
  return {
    id: raw['id'] as number,
    name: (raw['name'] as string) ?? '',
    url: (raw['url'] as string) ?? '',
    previewUrl: (raw['preview_url'] as string) ?? null,
    mimeType: (raw['mime_type'] as string) ?? '',
    sizeBytes: (raw['size_bytes'] as number) ?? 0,
    isPrimary: (raw['is_primary'] as boolean) ?? false,
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

export async function updateFile(id: number, payload: { is_primary?: boolean; meta?: unknown }): Promise<FileItem> {
  const { data } = await http.put(`/dashboard/files/${id}`, payload)
  return mapFile((data.data ?? data) as Record<string, unknown>)
}

export async function deleteFile(id: number, scope: 'dashboard' | 'admin' = 'dashboard'): Promise<void> {
  await http.delete(`/${scope}/files/${id}`)
}

export async function restoreFile(id: number, scope: 'dashboard' | 'admin' = 'dashboard'): Promise<void> {
  await http.post(`/${scope}/files/${id}/restore`)
}
