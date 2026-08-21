import http from '@/api'
import { t } from '@/i18n'

/**
 * Publikovanie podujatia so závislosťami.
 *
 * Publikované podujatie musí mať publikované aj miesto a kanál — inak jeho
 * karta odkazuje na profil, ktorý sa tvári ako rozrobený. Backend to preto
 * odmietne (App\Exceptions\DependenciesNotPublishedException) a pošle zoznam
 * toho, čo visí. Tu sa z neho spraví otázka a po súhlase sa požiadavka
 * zopakuje s príznakom, ktorý závislosti dopublikuje.
 */

const DEPENDENCIES_CODE = 'dependencies_not_published'

/** Používateľ dialóg odmietol — volajúci to nemá hlásiť ako chybu. */
export const PUBLISH_CANCELLED = Symbol('publish-cancelled')

type ApiErrorBody = {
  message?: string
  code?: string
  dependencies?: Array<{ type: string; id: number; name: string; status: string; label: string }>
  errors?: Record<string, string[]>
}

export function errorBody(e: unknown): ApiErrorBody | undefined {
  return (e as { response?: { data?: ApiErrorBody } })?.response?.data
}

/** Hláška zo servera, keď ju poslal — inak nech si volajúci zvolí fallback. */
export function serverMessage(e: unknown): string | undefined {
  const message = errorBody(e)?.message
  return typeof message === 'string' && message !== '' ? message : undefined
}

export function isCancelled(e: unknown): boolean {
  return e === PUBLISH_CANCELLED
}

/**
 * Vráti true, keď má volajúci požiadavku zopakovať s `publish_dependencies`.
 * Vyhodí PUBLISH_CANCELLED, keď používateľ dopublikovanie odmietol.
 */
export function confirmDependencies(e: unknown): boolean {
  const body = errorBody(e)

  if (body?.code !== DEPENDENCIES_CODE) return false

  // Natívny confirm — rovnaká konvencia ako pri ostatných potvrdeniach v appke.
  if (!confirm(t('events.publish.dependenciesConfirm', { names: body.message ?? '' }))) {
    throw PUBLISH_CANCELLED
  }

  return true
}

/**
 * Uloženie formulára podujatia; pri 422 kvôli závislostiam sa spýta a pošle
 * to isté ešte raz so súhlasom. `send` dostane payload, nie hotovú URL —
 * volajúci si tak nechá svoju vlastnú create/update funkciu.
 */
export async function withDependencyConsent<P extends Record<string, unknown>, R>(
  send: (payload: P) => Promise<R>,
  payload: P,
): Promise<R> {
  try {
    return await send(payload)
  } catch (e) {
    if (!confirmDependencies(e)) throw e

    return await send({ ...payload, publish_dependencies: true })
  }
}

/**
 * POST na publish endpoint; pri 422 kvôli závislostiam sa spýta a zopakuje.
 */
export async function publishRequest(url: string, published: boolean): Promise<void> {
  try {
    await http.post(url, { published })
  } catch (e) {
    if (!published || !confirmDependencies(e)) throw e

    await http.post(url, { published, publish_dependencies: true })
  }
}
