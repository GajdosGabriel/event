/**
 * Úprava odkazov vo formátovanom popise (podujatie, kanál, miesto) pred `v-html`.
 *
 * Importované a ručne písané popisy často obsahujú holú adresu typu
 * `https://docs.google.com/forms/d/e/1FAIpQ…/viewform`. Taká adresa je nečitateľná
 * a dlhým nezalomiteľným slovom rozťahuje stĺpec stránky. Preto:
 *  - holú URL v texte zmeníme na odkaz,
 *  - odkaz, ktorého text je len samotná URL, nahradíme krátkym názvom podľa
 *    typu cieľa („Formulár", „Dokument"…, inak doména); celá adresa ostane v `title`,
 *  - všetky odkazy otvárame v novom okne.
 * Odkaz s vlastným textom („prihláste sa tu") ponecháme.
 */

export type BodyLinkKind =
  | 'form' | 'document' | 'spreadsheet' | 'presentation'
  | 'file' | 'pdf' | 'video' | 'map' | 'meeting'

/** Holá adresa v texte. Koncová interpunkcia vety sa z nej potom odreže. */
const URL_IN_TEXT = /\b(?:https?:\/\/|www\.)[^\s<>"]+/gi
const TRAILING_PUNCTUATION = /[.,;:!?)\]}'"»“”]+$/

function hostOf(url: URL): string {
  return url.hostname.replace(/^www\./, '').toLowerCase()
}

/** Typ cieľa podľa domény a cesty, `null` = bez známeho typu (zobrazí sa doména). */
export function linkKind(href: string): BodyLinkKind | null {
  let url: URL
  try {
    url = new URL(href)
  } catch {
    return null
  }
  const host = hostOf(url)
  const path = url.pathname.toLowerCase()

  if (host === 'forms.gle' || (host === 'docs.google.com' && path.startsWith('/forms'))
    || host === 'forms.office.com' || host.endsWith('typeform.com') || host === 'jotform.com'
    || host === 'survio.com' || host === 'tally.so') return 'form'
  if (host === 'docs.google.com') {
    if (path.startsWith('/document')) return 'document'
    if (path.startsWith('/spreadsheets')) return 'spreadsheet'
    if (path.startsWith('/presentation')) return 'presentation'
  }
  if (/\.pdf$/.test(path)) return 'pdf'
  if (/\.(docx?|odt|rtf)$/.test(path)) return 'document'
  if (/\.(xlsx?|ods|csv)$/.test(path)) return 'spreadsheet'
  if (/\.(pptx?|odp)$/.test(path)) return 'presentation'
  if (host === 'drive.google.com' || host === 'dropbox.com' || host === '1drv.ms'
    || host === 'onedrive.live.com' || host.endsWith('sharepoint.com')) return 'file'
  if (host === 'youtube.com' || host === 'm.youtube.com' || host === 'youtu.be'
    || host === 'vimeo.com') return 'video'
  if (host === 'maps.app.goo.gl' || host === 'maps.google.com' || host === 'mapy.cz'
    || (host === 'google.com' && path.startsWith('/maps'))
    || (host === 'goo.gl' && path.startsWith('/maps'))) return 'map'
  if (host.endsWith('zoom.us') || host === 'meet.google.com' || host === 'teams.microsoft.com'
    || host === 'teams.live.com') return 'meeting'
  return null
}

/** Krátky názov odkazu: typ cieľa, inak doména bez `www.`. */
export function linkLabel(href: string, label: (kind: BodyLinkKind) => string): string {
  const kind = linkKind(href)
  if (kind) return label(kind)
  try {
    return hostOf(new URL(href))
  } catch {
    return href
  }
}

function normalizeHref(raw: string): string {
  return /^www\./i.test(raw) ? `https://${raw}` : raw
}

/** Text odkazu je len adresa (prípadne bez protokolu alebo s `www.`). */
function textIsUrl(text: string, href: string): boolean {
  const t = text.trim()
  if (!t || /\s/.test(t)) return false
  if (/^(?:https?:\/\/|www\.)/i.test(t)) return true
  const bare = (s: string) => s.replace(/^https?:\/\//i, '').replace(/^www\./i, '').replace(/\/$/, '')
  return bare(t) === bare(href)
}

function linkifyTextNodes(doc: Document): void {
  const walker = doc.createTreeWalker(doc.body, NodeFilter.SHOW_TEXT)
  const nodes: Text[] = []
  for (let n = walker.nextNode(); n; n = walker.nextNode()) {
    const text = n as Text
    if (text.parentElement?.closest('a, code, pre')) continue
    if (URL_IN_TEXT.test(text.data)) nodes.push(text)
    URL_IN_TEXT.lastIndex = 0
  }

  for (const node of nodes) {
    const fragment = doc.createDocumentFragment()
    let last = 0
    for (const match of node.data.matchAll(URL_IN_TEXT)) {
      const raw = match[0].replace(TRAILING_PUNCTUATION, '')
      const start = match.index ?? 0
      if (start > last) fragment.append(node.data.slice(last, start))
      const a = doc.createElement('a')
      a.href = normalizeHref(raw)
      a.textContent = raw
      fragment.append(a)
      last = start + raw.length
    }
    if (last < node.data.length) fragment.append(node.data.slice(last))
    node.replaceWith(fragment)
  }
}

export function enhanceBodyLinks(
  html: string | null | undefined,
  label: (kind: BodyLinkKind) => string,
): string {
  if (!html) return ''
  const doc = new DOMParser().parseFromString(html, 'text/html')
  linkifyTextNodes(doc)

  doc.querySelectorAll('a[href]').forEach(a => {
    const href = a.getAttribute('href') ?? ''
    if (/^(?:mailto:|tel:|#)/i.test(href)) return
    a.setAttribute('target', '_blank')
    a.setAttribute('rel', 'noopener noreferrer')
    if (!/^https?:\/\//i.test(href)) return
    // Iba čistý text — odkaz s obrázkom alebo formátovaním nechávame.
    if (a.children.length === 0 && textIsUrl(a.textContent ?? '', href)) {
      a.textContent = linkLabel(href, label)
      if (!a.hasAttribute('title')) a.setAttribute('title', href)
    }
  })
  return doc.body.innerHTML
}
