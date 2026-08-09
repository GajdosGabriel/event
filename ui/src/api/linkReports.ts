import { BASE_URL, apiHeaders } from './index'
import type { LinkReportTarget } from '@/types'

/**
 * Ohlásenie kliknutia na odkaz mimo portál.
 *
 * Nie je to hlásenie chyby — prehliadač po kliknutí odíde na cudziu doménu
 * a či sa tam niečo načítalo, sa z našej stránky zistiť nedá. Je to podnet:
 * o tento odkaz niekto stojí, over ho prednostne. Rozhodne až sonda na
 * serveri a majiteľovi sa ozve len vtedy, keď odkaz naozaj nefunguje.
 *
 * Posiela sa len typ a id záznamu, nikdy samotná adresa — server si ju nájde
 * sám. Inak by z toho bola brána, cez ktorú si ktokoľvek nechá náš server
 * zaklopať na ľubovoľnú adresu.
 */
export function reportLinkClick(
  type: LinkReportTarget,
  id: number,
  from?: string,
  attribute = 'website',
): void {
  // `fetch` s `keepalive` namiesto axiosu z dvoch dôvodov: požiadavka musí
  // dobehnúť, aj keby prehliadač stránku opustil, a nesmie prejsť cez
  // spoločný interceptor, ktorý pri limite vypisuje chybu do rohu obrazovky.
  // Toto je tichá vec na pozadí — návštevník o nej nemá vedieť ani vtedy,
  // keď zlyhá.
  try {
    void fetch(`${BASE_URL}/link-reports`, {
      method: 'POST',
      credentials: 'include',
      keepalive: true,
      headers: apiHeaders(),
      body: JSON.stringify({
        type,
        id,
        attribute,
        // Cesta bez query — v adrese stránky nemajú čo robiť osobné údaje
        // a server ju aj tak prijme len ako cestu na našom webe.
        from: from ?? window.location.pathname,
      }),
    }).catch(() => {})
  } catch {
    // Zámerne ticho.
  }
}
