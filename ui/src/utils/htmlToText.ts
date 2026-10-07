/**
 * Prevedie HTML (napr. `body` podujatia) na čistý text vrátane dekódovania entít
 * (`&quot;`, `&amp;`, `&nbsp;`...). DOMParser nespúšťa skripty ani nenačítava obrázky.
 */
export function htmlToText(html: string | null | undefined): string {
  if (!html) return ''
  const withBreaks = html.replace(/<\/(p|div|li|h[1-6]|br)>|<br\s*\/?>/gi, ' $&')
  const doc = new DOMParser().parseFromString(withBreaks, 'text/html')
  return (doc.body.textContent ?? '').replace(/\s+/g, ' ').trim()
}
