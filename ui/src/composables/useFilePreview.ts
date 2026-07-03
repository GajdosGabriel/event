import { ref } from 'vue'
import type { FileItem } from '@/api/files'

const PLACEHOLDER_MARKER = 'document-placeholder'

export function isImageFile(f: FileItem): boolean {
  return f.mimeType.startsWith('image/')
}

export function extensionLabel(f: FileItem): string {
  return (f.extension || f.mimeType.split('/').pop() || 'file').toUpperCase().slice(0, 4)
}

export function openOriginal(f: FileItem): void {
  if (f.url) window.open(f.url, '_blank', 'noopener')
}

function isUsable(url: string | null): url is string {
  return !!url && !url.includes(PLACEHOLDER_MARKER)
}

/**
 * Shared "what <img src> should this file tile use" logic for FileManager/gallery
 * components. A raw PDF/DOC is never usable as an <img> src — only a real generated
 * thumb/large counts, or (for actual raster images) the original file as a fallback.
 * Centralized here so upload (ImageManager) and display (ImageGallery) components
 * can't silently diverge on how they treat non-image documents.
 */
export function useFilePreview() {
  const failedSrcs = ref(new Set<number>())

  function imgSrc(f: FileItem): string | null {
    const originalFallback = isImageFile(f) ? f.url : null

    if (failedSrcs.value.has(f.id)) {
      return isUsable(originalFallback) ? originalFallback : null
    }

    const candidate = f.thumbUrl ?? originalFallback
    return isUsable(candidate) ? candidate : null
  }

  function onImgError(e: Event, f: FileItem) {
    const el = e.target as HTMLImageElement
    const originalFallback = isImageFile(f) ? f.url : null
    if (!failedSrcs.value.has(f.id) && isUsable(originalFallback) && originalFallback !== el.src) {
      failedSrcs.value.add(f.id)
      el.src = originalFallback
    }
  }

  function onImgLoad(f: FileItem) {
    failedSrcs.value.delete(f.id)
  }

  return { failedSrcs, imgSrc, onImgError, onImgLoad }
}
