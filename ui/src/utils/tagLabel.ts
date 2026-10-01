import { t, type MessageKey } from '@/i18n'

const SYSTEM_TAGS = new Set(['vonku', 'vstup-volny', 's-registraciou', 'viacdnove', 'online'])

/**
 * Názov štítka v aktuálnom jazyku. Systémové štítky atribútov majú v DB len
 * jeden (slovenský) názov, preto ich prekladáme podľa slugu; ostatné
 * (AI/redakčné) ostávajú tak, ako ich uložil server.
 */
export function tagLabel(tag: { slug: string; name: string }): string {
  return SYSTEM_TAGS.has(tag.slug) ? t(`tagNames.${tag.slug}` as MessageKey) : tag.name
}
