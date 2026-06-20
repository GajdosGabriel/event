import { API_ORIGIN } from '../../constants/api.constants';
import { UploadedFileItem } from '../models/uploaded-file.model';

const FILE_COLLECTION_KEYS = [
  'files',
  'media',
  'attachments',
  'images',
  'attached_files'
] as const;

function normalizeVariantSuffix(value: string): string {
  return value.replace(/_(thumb|small|medium|large)(?=\.[^.]+$)/i, '');
}

function canonicalizeAssetValue(value: string | null | undefined): string {
  if (!value) {
    return '';
  }

  const absolute = toAbsoluteUrl(value);
  const withoutQuery = absolute.split('?')[0]?.split('#')[0] ?? '';
  const withoutOrigin = withoutQuery.replace(/^https?:\/\/[^/]+/i, '');
  const withoutStoragePrefix = withoutOrigin.replace(/^\/storage\//i, '/');

  return normalizeVariantSuffix(withoutStoragePrefix).toLowerCase();
}

function isImageLike(file: UploadedFileItem): boolean {
  const mimeType = (file.mimeType ?? '').toLowerCase();
  const type = (file.type ?? '').toLowerCase();
  const target = file.url ?? file.previewUrl ?? file.name;
  const extension = target.toLowerCase().split('?')[0]?.split('#')[0]?.split('.').pop() ?? '';

  return (
    mimeType.startsWith('image/') ||
    type === 'image' ||
    type === 'img' ||
    ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'avif'].includes(extension)
  );
}

function buildFileIdentity(file: UploadedFileItem): string {
  const canonicalUrlPart = canonicalizeAssetValue(file.url);
  const canonicalPreviewPart = canonicalizeAssetValue(file.previewUrl);
  const canonicalNamePart = normalizeVariantSuffix((file.name ?? '').trim()).toLowerCase();
  const idPart = file.id !== undefined && file.id !== null ? String(file.id) : '';

  if (isImageLike(file)) {
    const imageAssetPart = canonicalUrlPart || canonicalPreviewPart || canonicalNamePart;
    if (imageAssetPart) {
      return `image::${imageAssetPart}`;
    }
  }

  return `${idPart}::${canonicalUrlPart}::${canonicalNamePart}`;
}

function toAbsoluteUrl(value: string): string {
  if (!value) {
    return value;
  }

  if (/^https?:\/\//i.test(value)) {
    return value;
  }

  if (value.startsWith('/')) {
    return `${API_ORIGIN}${value}`;
  }

  const normalizedValue = value.replace(/^\/+/, '');
  const storageRelativeValue = normalizedValue.startsWith('storage/')
    ? normalizedValue
    : `storage/${normalizedValue}`;

  return `${API_ORIGIN}/${storageRelativeValue}`;
}

function toNumber(value: unknown): number | null {
  if (typeof value === 'number' && Number.isFinite(value)) {
    return value;
  }

  if (typeof value === 'string' && value.trim()) {
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : null;
  }

  return null;
}

function toBoolean(value: unknown): boolean {
  if (typeof value === 'boolean') {
    return value;
  }

  if (typeof value === 'number') {
    return value === 1;
  }

  if (typeof value === 'string') {
    const normalized = value.trim().toLowerCase();
    return normalized === '1' || normalized === 'true' || normalized === 'yes';
  }

  return false;
}

function normalizeFileEntry(entry: unknown): UploadedFileItem | null {
  if (typeof entry === 'string' && entry.trim()) {
    const url = toAbsoluteUrl(entry.trim());

    return {
      name: url.split('/').filter(Boolean).pop() ?? 'Subor',
      url,
      type: 'image',
      isPrimary: true
    };
  }

  if (!entry || typeof entry !== 'object') {
    return null;
  }

  const record = entry as Record<string, unknown>;
  const nestedData = record['data'];

  if (nestedData !== undefined && nestedData !== entry) {
    const nestedEntries = normalizeFileEntries(nestedData);
    if (nestedEntries.length > 0) {
      return nestedEntries[0];
    }
  }

  const id =
    (record['id'] as number | string | undefined) ??
    (record['file_id'] as number | string | undefined) ??
    (record['media_id'] as number | string | undefined) ??
    (record['upload_id'] as number | string | undefined) ??
    undefined;

  const previewUrlRaw =
    (record['thumb_image_url'] as string | undefined) ??
    (record['small_image_url'] as string | undefined) ??
    (record['thumbnail_url'] as string | undefined) ??
    (record['preview_url'] as string | undefined) ??
    (record['thumb'] as string | undefined) ??
    (record['small'] as string | undefined) ??
    undefined;

  const urlRaw =
    (record['original_file_url'] as string | undefined) ??
    (record['file_url'] as string | undefined) ??
    (record['download_url'] as string | undefined) ??
    (record['full_url'] as string | undefined) ??
    (record['original_image_url'] as string | undefined) ??
    (record['url'] as string | undefined) ??
    (record['original_url'] as string | undefined) ??
    (record['large_image_url'] as string | undefined) ??
    (record['medium_image_url'] as string | undefined) ??
    (record['large'] as string | undefined) ??
    (record['medium'] as string | undefined) ??
    (record['src'] as string | undefined) ??
    (record['path'] as string | undefined) ??
    null;

  const url = typeof urlRaw === 'string' && urlRaw.trim() ? toAbsoluteUrl(urlRaw) : null;
  const previewUrl =
    typeof previewUrlRaw === 'string' && previewUrlRaw.trim()
      ? toAbsoluteUrl(previewUrlRaw)
      : url;

  const name =
    (record['file_name'] as string | undefined) ??
    (record['filename'] as string | undefined) ??
    (record['original_name'] as string | undefined) ??
    (record['name'] as string | undefined) ??
    (record['title'] as string | undefined) ??
    (url ? url.split('/').filter(Boolean).pop() : undefined) ??
    'Subor';

  return {
    id,
    name,
    url,
    previewUrl,
    type:
      (record['file_type'] as string | undefined) ??
      (record['type'] as string | undefined) ??
      (record['collection_name'] as string | undefined) ??
      (record['fileType'] as string | undefined) ??
      null,
    disk: (record['file_disk'] as string | undefined) ?? (record['disk'] as string | undefined) ?? null,
    sizeBytes:
      toNumber(record['size']) ??
      toNumber(record['file_size']) ??
      toNumber(record['bytes']) ??
      null,
    isPrimary:
      toBoolean(record['is_primary']) ||
      toBoolean(record['primary']) ||
      toBoolean(record['make_primary_file']),
    mimeType:
      (record['mime_type'] as string | undefined) ??
      (record['mimeType'] as string | undefined) ??
      (record['mineType'] as string | undefined) ??
      (record['mimetype'] as string | undefined) ??
      (record['mime'] as string | undefined) ??
      null
  };
}

function normalizeFileEntries(source: unknown): UploadedFileItem[] {
  if (Array.isArray(source)) {
    return source
      .map((entry) => normalizeFileEntry(entry))
      .filter((entry): entry is UploadedFileItem => entry !== null);
  }

  if (source && typeof source === 'object' && !Array.isArray(source)) {
    const record = source as Record<string, unknown>;

    if ('data' in record && record['data'] !== source) {
      return normalizeFileEntries(record['data']);
    }
  }

  const singleEntry = normalizeFileEntry(source);
  return singleEntry ? [singleEntry] : [];
}

export function extractPrimaryImageUrl(
  source: unknown,
  fallbackImageUrl?: string | null
): string | null {
  if (source && typeof source === 'object') {
    const record = source as Record<string, unknown>;
    const directImageUrl = normalizeFileEntry({
      full_url: record['full_url'],
      large_image_url: record['large_image_url'],
      medium_image_url: record['medium_image_url'],
      small_image_url: record['small_image_url'],
      thumb_image_url: record['thumb_image_url'],
      original_image_url: record['original_image_url'],
      large: record['large'],
      medium: record['medium'],
      thumb: record['thumb'],
      url: record['url'],
      original_url: record['original_url'],
      path: record['path']
    })?.url ?? null;

    if (directImageUrl) {
      return directImageUrl;
    }

    const primaryImages = normalizeFileEntries(record['primary_image']);
    const primaryImageUrl = primaryImages.find((entry) => entry.url)?.url ?? null;

    if (primaryImageUrl) {
      return primaryImageUrl;
    }

    const uploadedFiles = extractUploadedFiles(source);
    const uploadedImageUrl =
      uploadedFiles.find((entry) => entry.isPrimary && entry.url)?.url ??
      uploadedFiles.find((entry) => entry.url)?.url ??
      null;

    if (uploadedImageUrl) {
      return uploadedImageUrl;
    }
  }

  if (typeof source === 'string' && source.trim()) {
    return toAbsoluteUrl(source.trim());
  }

  if (fallbackImageUrl && fallbackImageUrl.trim()) {
    return toAbsoluteUrl(fallbackImageUrl);
  }

  return null;
}

export function extractUploadedFiles(
  source: unknown,
  fallbackImageUrl?: string | null
): UploadedFileItem[] {
  if (!source || typeof source !== 'object') {
    if (fallbackImageUrl) {
      const absoluteFallback = toAbsoluteUrl(fallbackImageUrl);
      return [{ name: 'Nahlad', url: absoluteFallback, previewUrl: absoluteFallback, type: 'image', isPrimary: true }];
    }
    return [];
  }

  const record = source as Record<string, unknown>;
  const mergedFiles: UploadedFileItem[] = [];
  const seen = new Set<string>();

  for (const key of FILE_COLLECTION_KEYS) {
    const normalized = normalizeFileEntries(record[key]);

    for (const file of normalized) {
      const identity = buildFileIdentity(file);
      if (seen.has(identity)) {
        continue;
      }

      seen.add(identity);
      mergedFiles.push(file);
    }
  }

  if (mergedFiles.length > 0) {
    return mergedFiles;
  }

  if (!fallbackImageUrl) {
    return [];
  }

  const absoluteFallback = toAbsoluteUrl(fallbackImageUrl);
  return [{ name: 'Nahlad', url: absoluteFallback, previewUrl: absoluteFallback, type: 'image', isPrimary: true }];
}
