import { onBeforeUnmount } from 'vue'
import { recordView, type ViewableKind } from '@/api/views'

/** Koľko musí byť stránka viditeľná, kým sa zobrazenie započíta. */
export const VIEW_DELAY_MS = 3000

/**
 * Započíta zobrazenie verejného detailu, až keď ho človek reálne videl.
 *
 * GET detailu na serveri nič nepočíta — crawler bez JavaScriptu, prefetch
 * ani opakované načítanie dát sa tak do štatistiky nedostanú. Sem prídu len
 * prehliadače, ktoré stránku vykreslili a nechali ju viditeľnú aspoň
 * {@link VIEW_DELAY_MS}. Čas v skrytej karte sa nepočíta: časovač sa pri
 * skrytí zastaví a pri návrate pokračuje.
 *
 * `start(id)` sa volá až po úspešnom načítaní záznamu; pri inom id (prechod
 * medzi detailmi bez odmontovania stránky) sa meranie začne odznova.
 */
export function useViewBeacon(kind: ViewableKind) {
  let targetId: number | null = null
  let remaining = VIEW_DELAY_MS
  let startedAt = 0
  let timer: ReturnType<typeof setTimeout> | null = null

  function pause() {
    if (timer === null) return
    clearTimeout(timer)
    timer = null
    remaining -= Date.now() - startedAt
  }

  function resume() {
    if (timer !== null || targetId === null || document.visibilityState !== 'visible') return
    startedAt = Date.now()
    timer = setTimeout(fire, Math.max(0, remaining))
  }

  function fire() {
    timer = null
    const id = targetId
    stop()
    if (id !== null) recordView(kind, id)
  }

  function onVisibility() {
    if (document.visibilityState === 'visible') resume()
    else pause()
  }

  function stop() {
    pause()
    targetId = null
    document.removeEventListener('visibilitychange', onVisibility)
  }

  function start(id: number) {
    // Automatizovaný prehliadač (Selenium, Puppeteer, Playwright) sa sám
    // priznáva cez navigator.webdriver.
    if (navigator.webdriver) return
    if (id === targetId) return

    stop()
    targetId = id
    remaining = VIEW_DELAY_MS
    document.addEventListener('visibilitychange', onVisibility)
    resume()
  }

  onBeforeUnmount(stop)

  return { start }
}
