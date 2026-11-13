/**
 * Stránka „Pre organizátorov". Rovnako ako právne dokumenty je mimo
 * `src/i18n/locales`: je to súvislý text, nie popisky rozhrania, a v slovníku
 * by sa každá úprava vety rozsypala do štyroch obrovských súborov.
 *
 * Skrátenú verziu pre vyhľadávače (nadpis, popis, zoznam funkcií) skladá
 * backend z `resources/lang/{jazyk}/seo.php → organizers` — pri zmene ponuky
 * treba upraviť obe.
 */
import { currentLocale, type Locale } from '@/i18n'
import type { OrganizersPageContent } from './types'
import sk from './sk'
import cs from './cs'
import de from './de'
import en from './en'

export type { OrganizersPageContent, OrganizersAction } from './types'

/** Verejná cesta stránky. Rovnakú drží backend v `PublicUrl::FOR_ORGANIZERS`. */
export const FOR_ORGANIZERS_PATH = '/pre-organizatorov'

const pages: Record<Locale, OrganizersPageContent> = { sk, cs, de, en }

/** Obsah v jazyku rozhrania, záložne slovenský. */
export function organizersPage(locale: Locale = currentLocale()): OrganizersPageContent {
  return pages[locale] ?? pages.sk
}
