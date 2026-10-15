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

/**
 * Odstráni názov zopakovaný na začiatku textu. Importované popisy často
 * začínajú nadpisom („Stretnutie s … Štvrtok 15.10.") — perex vo výpise by
 * tak len ozvenou opakoval nadpis nad ním.
 */
export function stripLeadingTitle(text: string, title: string): string {
  // Porovnáva sa po slovách — import názov často skráti alebo prehodí
  // oddeľovače („Názov | Hosť" verzus „Názov Štvrtok … | Hosť").
  const words = (s: string) => s.toLocaleLowerCase().split(/[\s|:–—-]+/).filter(Boolean)
  const titleWords = words(title)
  const textWords = words(text)
  let matched = 0
  while (matched < titleWords.length && titleWords[matched] === textWords[matched]) matched++

  // Celý názov, alebo aspoň jeho podstatná časť — dve zhodné slová („Svätá omša")
  // môžu byť aj prirodzený začiatok vety.
  if (matched === 0 || (matched < titleWords.length && matched < 3)) return text

  const pattern = new RegExp(`^[\\s|:–—-]*(?:\\S+?[\\s|:–—-]+){${matched - 1}}\\S+?(?=[\\s|:–—-]|$)`, 'u')
  return text.replace(pattern, '').replace(/^[\s|:–—-]+/, '')
}
