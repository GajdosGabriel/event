import { BASE_URL, apiHeaders } from './index'

export type ViewableKind = 'events' | 'venues' | 'canals'

/**
 * Započítanie zobrazenia verejného detailu.
 *
 * Cez `fetch`, nie axios: interceptor by pri 429 ukázal návštevníkovi toast
 * o rate limite, hoci o štatistike nič nevie. Výsledok nás nezaujíma —
 * zlyhanie počítadla nesmie mať na stránku žiadny vplyv.
 */
export function recordView(kind: ViewableKind, id: number): void {
  void fetch(`${BASE_URL}/${kind}/${id}/view`, {
    method: 'POST',
    headers: apiHeaders(),
    credentials: 'include',
    keepalive: true,
  }).catch(() => { /* štatistika je doplnok */ })
}
