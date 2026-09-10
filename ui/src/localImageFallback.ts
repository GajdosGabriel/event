/** Local API image URLs carry a fallback in the fragment (never sent to S3). */
export function handleLocalImageError(event: Event): void {
  const image = event.target
  if (!(image instanceof HTMLImageElement)) return

  const source = new URL(image.currentSrc || image.src, window.location.href)
  const fallback = new URLSearchParams(source.hash.slice(1)).get('local-image-fallback')
  if (!fallback) return

  const target = new URL(fallback, source)
  if (!['https:', 'http:'].includes(target.protocol) || target.origin !== source.origin) return
  // Clear srcset too, otherwise the browser can keep selecting the failed variant.
  image.removeAttribute('srcset')
  image.src = target.href
}

export function installLocalImageFallback(): void {
  // Image error events do not bubble, so listen during capture.
  document.addEventListener('error', handleLocalImageError, true)
}
