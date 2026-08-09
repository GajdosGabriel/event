import { readonly, ref, watch } from 'vue'
import { useRoute, useRouter, type LocationQueryRaw } from 'vue-router'

/**
 * Drží číslo strany v adrese (`?page=`).
 *
 * Bez neho žilo stránkovanie len v stave komponentu: po odchode na detail a
 * návrate tlačidlom „späť" sa komponent namountoval odznova a zoznam začínal
 * na prvej strane, hoci človek odchádzal z tretej. To isté platilo pre reload
 * aj pre poslanie odkazu — tretia strana sa nedala zdieľať.
 *
 * Prestránkovanie je navigácia, preto `push`: „späť" vedie o stranu nižšie a
 * až prvá strana (alebo klik na logo) je adresa bez parametra. Zmena filtra
 * naopak stránku zhadzuje na prvú — vo filtrovanom výsledku tretia strana
 * nemusí existovať — a zapisuje sa cez `replace`, lebo písanie do políčka nie
 * je navigácia.
 */
export function usePageQuery(fetchPage: (page: number) => unknown) {
  const route = useRoute()
  const router = useRouter()

  /** Strana z adresy; čokoľvek iné než celé číslo > 1 je prvá strana. */
  function pageFromQuery(): number {
    const raw = Array.isArray(route.query.page) ? route.query.page[0] : route.query.page
    const value = Number(raw)
    return Number.isInteger(value) && value > 1 ? value : 1
  }

  /**
   * Naposledy vyžiadaná strana. Odlišuje zápis do adresy, ktorý vyvolal tento
   * komponent (vtedy je zoznam už načítaný), od zmeny zvonku — tlačidla
   * „späť"/„vpred" alebo odkazu. Východisko berie z adresy, aby aj prvé
   * načítanie vedelo, na ktorej strane sa začína.
   */
  const requestedPage = ref(pageFromQuery())

  /** Prvá strana je východisko — v adrese by bola len šum. */
  function withPage(query: LocationQueryRaw, page: number): LocationQueryRaw {
    const next: LocationQueryRaw = { ...query }
    delete next['page']
    if (page > 1) next['page'] = String(page)
    return next
  }

  /** Načíta stranu a zapamätá si ju; adresu nechá na volajúceho. */
  function load(page = 1) {
    requestedPage.value = page
    return fetchPage(page)
  }

  /** Klik na stránkovanie — nová položka v histórii, načítanie spustí watcher. */
  function goToPage(page: number, query: LocationQueryRaw = route.query) {
    if (page === requestedPage.value) return
    void router.push({ path: route.path, query: withPage(query, page) }).catch(() => {})
  }

  /** Zápis stavu filtrov do adresy bez záznamu v histórii. */
  function replaceQuery(query: LocationQueryRaw, page = requestedPage.value) {
    void router.replace({ path: route.path, query: withPage(query, page) }).catch(() => {})
  }

  // Strana v adrese sa zmenila zvonku — späť/vpred alebo odkaz. Vlastné zápisy
  // (`replaceQuery` po načítaní) sa poznajú podľa `requestedPage` a preskočia.
  watch(() => route.query.page, () => {
    const next = pageFromQuery()
    if (next !== requestedPage.value) void load(next)
  })

  return { requestedPage: readonly(requestedPage), pageFromQuery, load, goToPage, replaceQuery }
}
