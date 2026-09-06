/**
 * Verejné adresy portálu — zrkadlo `App\Support\PublicUrl` na backende.
 *
 * Detail podujatia má tvar `akcie/{id}/{slug}`, ostatné detaily `{slug}-{id}`.
 * V oboch prípadoch routuje len id a slug je pre človeka a pre vyhľadávač, takže
 * premenovanie záznamu nezhodí staré odkazy.
 *
 * Podujatia majú id vo vlastnom segmente preto, že ten istý tvar bude používať
 * aj hlascirkvi.sk, keď začne podujatia brať z tejto databázy.
 *
 * Obe strany musia dávať tú istú adresu — bot-render vrstva posiela crawlerovi
 * `canonical` a `sitemap.xml` z PHP verzie; keby SPA odkazovala inam, indexovala
 * by sa iná stránka, než na akú vedú odkazy v portáli.
 */

export const PUBLIC_EVENTS = '/akcie'

/** Predchádzajúci prefix podujatí. Už sa negeneruje, len presmerúva. */
export const PUBLIC_EVENTS_LEGACY = '/podujatia'
export const PUBLIC_VENUES = '/miesta'
export const PUBLIC_CANALS = '/organizatori'

/**
 * Nástenka otázok z publika. Zámerne nie slovenské slovo ako ostatné cesty —
 * táto adresa sa premieta na plátno a ľudia si ju prepisujú rukou.
 */
export const PUBLIC_QUESTIONS = '/q'

interface Sluggable {
  id: number
  name?: string
  slug?: string | null
}

function slugOf(item: Sluggable): string {
  return (item.slug ?? '').trim().replace(/^-+|-+$/g, '')
}

function segment(item: Sluggable): string {
  const slug = slugOf(item)

  return slug ? `${slug}-${item.id}` : String(item.id)
}

export function publicEventPath(event: Sluggable): string {
  const slug = slugOf(event)

  return slug ? `${PUBLIC_EVENTS}/${event.id}/${slug}` : `${PUBLIC_EVENTS}/${event.id}`
}

export function publicVenuePath(venue: Sluggable): string {
  return `${PUBLIC_VENUES}/${segment(venue)}`
}

export function publicCanalPath(canal: Sluggable): string {
  return `${PUBLIC_CANALS}/${segment(canal)}`
}

export function publicMunicipalityPath(slug: string): string {
  return `${PUBLIC_EVENTS}/mesto/${slug}`
}

export function publicTagPath(slug: string): string {
  return `${PUBLIC_EVENTS}/tema/${slug}`
}

export function publicWeekendPath(): string {
  return `${PUBLIC_EVENTS}/tento-vikend`
}

/**
 * Archív uplynulých podujatí — zrkadlo `PublicUrl::ARCHIVE`.
 *
 * Detail skončeného podujatia ostáva na svojej adrese navždy (odkazy z Googlu,
 * z e-mailov a zo zdieľaní musia fungovať aj o rok), ale bez tohto výpisu by
 * naň z portálu neviedol žiadny odkaz.
 */
export function publicArchivePath(): string {
  return `${PUBLIC_EVENTS}/archiv`
}

export function publicQuestionBoardPath(token: string): string {
  return `${PUBLIC_QUESTIONS}/${token}`
}

export function publicQuestionWallPath(token: string): string {
  return `${PUBLIC_QUESTIONS}/${token}/stena`
}

/**
 * Id z `{slug}-{id}`. Routa berie celý segment ako reťazec, aby fungovali aj
 * staré odkazy s holým číslom (`/events/42`) a odkazy so zastaraným slugom.
 */
export function idFromRouteParam(param: string | string[] | undefined): string {
  const value = Array.isArray(param) ? (param[0] ?? '') : (param ?? '')
  const match = value.match(/(?:^|-)(\d+)$/)

  return match ? match[1]! : value
}

/** Absolútna adresa pre `<link rel="canonical">` a `og:url`. */
export function absoluteUrl(path: string): string {
  return `${window.location.origin}${path}`
}
