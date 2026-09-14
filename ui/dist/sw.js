/*
 * Service worker.
 *
 * Existuje kvôli dvom veciam, ktoré sa dejú tam, kde nie je signál: skener pri
 * vchode a vstupenka s QR kódom v telefóne. Bez neho sa obe stránky offline
 * vôbec nenačítajú — SPA je jeden index.html a ten by nemal odkiaľ prísť.
 *
 * Stratégia je zámerne opatrná, lebo produkcia sa nasadzuje `git pull`-om
 * a `ui/dist` je verzovaný: zlá cache by znamenala používateľov zaseknutých
 * na starom builde, čo je horšia porucha než chýbajúci offline režim.
 *
 *   - HTML (navigácia): najprv sieť, cache len ako záchrana. Online teda vždy
 *     príde čerstvý index.html a s ním čerstvé hashe assetov.
 *   - /assets/*: cache-first. Vite dáva do názvu hash obsahu, takže súbor pod
 *     danou adresou sa nikdy nezmení — starý build si podrží svoje súbory,
 *     nový si vypýta iné.
 *   - /api, /sanctum, /storage: nikdy. Odpovede sú per používateľ a per
 *     moment; cachovať check-in alebo zoznam prihlásených by bolo nebezpečné.
 *
 * Verziu cache treba pri zmene tohto súboru zvýšiť — starý obsah sa potom
 * zmaže pri aktivácii.
 */

const CACHE = 'event-shell-v1'
const SHELL = ['/', '/index.html', '/favicon.svg', '/icon-192.png']

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE)
      // `reload` obchádza HTTP cache prehliadača — inak by sa do shellu mohol
      // uložiť práve ten starý index.html, ktorý sa snažíme nahradiť.
      .then((cache) => cache.addAll(SHELL.map((url) => new Request(url, { cache: 'reload' }))))
      .then(() => self.skipWaiting())
      // Nedostupný súbor pri inštalácii nesmie zhodiť celý worker.
      .catch(() => self.skipWaiting()),
  )
})

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((key) => key !== CACHE).map((key) => caches.delete(key))))
      .then(() => self.clients.claim()),
  )
})

self.addEventListener('fetch', (event) => {
  const request = event.request

  if (request.method !== 'GET') return

  const url = new URL(request.url)

  if (url.origin !== self.location.origin) return

  // Backend a nahraté súbory idú vždy na sieť.
  if (/^\/(api|sanctum|storage|build)\//.test(url.pathname)) return

  if (request.mode === 'navigate') {
    event.respondWith(
      fetch(request)
        .then((response) => {
          const copy = response.clone()
          caches.open(CACHE).then((cache) => cache.put('/index.html', copy)).catch(() => {})
          return response
        })
        .catch(() => caches.match('/index.html').then((cached) => cached ?? Response.error())),
    )
    return
  }

  if (url.pathname.startsWith('/assets/')) {
    event.respondWith(
      caches.match(request).then((cached) => cached ?? fetch(request).then((response) => {
        if (response.ok) {
          const copy = response.clone()
          caches.open(CACHE).then((cache) => cache.put(request, copy)).catch(() => {})
        }
        return response
      })),
    )
  }
})
