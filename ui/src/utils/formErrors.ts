/**
 * Validačné chyby zo servera (422) v tvare, v akom ich čaká `FormField`.
 *
 * Laravel posiela pole hlášok na pole; formulár ukáže len prvú — ďalšie sú
 * spravidla dôsledok tej istej chyby a pod poľom by sa nezmestili.
 */
export function fieldErrors(e: unknown): Record<string, string> {
  const errors = (e as { response?: { data?: { errors?: Record<string, string[]> } } })
    ?.response?.data?.errors

  if (!errors) return {}

  return Object.fromEntries(
    Object.entries(errors)
      .map(([key, messages]) => [key, messages[0]])
      .filter(([, message]) => typeof message === 'string'),
  )
}
