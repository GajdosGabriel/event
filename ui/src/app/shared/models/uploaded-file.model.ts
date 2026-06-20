export interface UploadedFileItem {
  id?: number | string;
  name: string;
  url?: string | null;
  previewUrl?: string | null;
  type?: string | null;
  disk?: string | null;
  sizeBytes?: number | null;
  isPrimary?: boolean;
  mimeType?: string | null;
}
