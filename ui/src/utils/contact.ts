/**
 * Formát telefónu a PSČ — zrkadlí App\Rules\PhoneNumber a App\Rules\Postcode
 * na API. Pri zmene pravidla upravte obe strany.
 */

/** Prázdna hodnota je v poriadku (pole je nepovinné), o povinnosti rozhoduje formulár. */
export function isValidPhone(value: string | null | undefined): boolean {
  const v = (value ?? '').trim()
  if (v === '') return true

  return /^[0-9+\s()-]{6,20}$/.test(v) && (v.match(/\d/g)?.length ?? 0) >= 6
}

const SK_CZ_COUNTRIES = new Set([
  'sk', 'svk', 'slovensko', 'slovakia', 'slowakei', 'slovenskarepublika',
  'cz', 'cze', 'cesko', 'czechia', 'czechrepublic', 'ceskarepublika', 'tschechien',
])

function isSlovakOrCzech(country: string | null | undefined): boolean {
  const c = (country ?? '').trim()
  if (c === '') return true

  const normalized = c.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/[^a-z]+/g, '')

  return SK_CZ_COUNTRIES.has(normalized)
}

/** SK/CZ: 5 číslic, voliteľne s medzerou („811 01"); iná krajina: len rozumný tvar. */
export function isValidPostcode(value: string | null | undefined, country?: string | null): boolean {
  const v = (value ?? '').trim()
  if (v === '') return true

  return isSlovakOrCzech(country)
    ? /^\d{3}\s?\d{2}$/.test(v)
    : /^[A-Za-z0-9][A-Za-z0-9\s-]{1,12}$/.test(v)
}
