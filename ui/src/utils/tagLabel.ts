import { t, type MessageKey } from '@/i18n'

const SYSTEM_TAGS = new Set(['vonku', 'vstup-volny', 's-registraciou', 'viacdnove', 'online'])

/**
 * Názov štítka v aktuálnom jazyku. Systémové štítky atribútov majú v DB len
 * jeden (slovenský) názov, preto ich prekladáme podľa slugu; ostatné
 * (AI/redakčné) ostávajú tak, ako ich uložil server. Ak preklad chýba
 * (t() vtedy vráti samotný kľúč), zobrazí sa názov z API.
 */
export function tagLabel(tag: { slug: string; name: string }): string {
  if (!SYSTEM_TAGS.has(tag.slug)) return tag.name

  const key = `events.tagNames.${tag.slug}` as MessageKey
  const label = t(key)

  return label === key ? tag.name : label
}
