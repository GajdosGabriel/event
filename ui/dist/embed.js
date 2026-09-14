/*
 * Loader widgetu Event.
 *
 * Organizátor vloží na svoj web jeden `<script>` s parametrami v `data-*`
 * atribútoch; skript na jeho mieste vytvorí iframe a drží mu výšku podľa
 * obsahu. Bez toho by widget musel mať pevnú výšku a program by v ňom
 * skroloval v okienku.
 *
 *   <script src="https://<portal>/embed.js"
 *           data-canal="nazov-organizatora-12"
 *           data-limit="5"></script>
 *
 * Alebo `data-event="nazov-podujatia-42"` pre jedno podujatie s registráciou.
 *
 * Zámerne bez závislostí a bez build kroku: súbor sa kopíruje do `dist` ako
 * je a musí fungovať na hocijakom cudzom webe, aj tam, kde beží starší
 * prehliadač alebo cudzí framework.
 */
(function () {
  'use strict'

  var script = document.currentScript
  if (!script) return

  var origin = new URL(script.src, window.location.href).origin
  var canal = script.getAttribute('data-canal')
  var event = script.getAttribute('data-event')

  if (!canal && !event) {
    // Bez cieľa nie je čo vykresliť. Ticho — chyba v cudzej konzole by
    // majiteľovi webu nič nepovedala a nám sa k nej nedostane.
    return
  }

  var path = event
    ? '/embed/podujatie/' + encodeURIComponent(event)
    : '/embed/organizator/' + encodeURIComponent(canal)

  var params = []
  var limit = script.getAttribute('data-limit')
  if (limit) params.push('limit=' + encodeURIComponent(limit))
  if (script.getAttribute('data-title') === '0') params.push('title=0')
  if (script.getAttribute('data-images') === '0') params.push('images=0')

  var iframe = document.createElement('iframe')
  iframe.src = origin + path + (params.length ? '?' + params.join('&') : '')
  iframe.title = script.getAttribute('data-label') || 'Podujatia'
  iframe.loading = 'lazy'
  iframe.style.width = '100%'
  iframe.style.border = '0'
  iframe.style.display = 'block'
  // Kým príde prvá správa o výške, drží widget rozumné miesto — inak by obsah
  // stránky pod ním po načítaní poskočil.
  iframe.style.height = (script.getAttribute('data-height') || '320') + 'px'
  // Registrácia potrebuje formuláre a skripty; navigáciu na najvyššej úrovni
  // widget nerobí (odkazy majú target="_blank"), takže mu ju nepovoľujeme.
  iframe.setAttribute('sandbox', 'allow-scripts allow-forms allow-same-origin allow-popups')

  script.parentNode.insertBefore(iframe, script)

  window.addEventListener('message', function (message) {
    // Prijmeme len správu z toho istého portálu a len o výške — na stránke
    // môžu bežať aj iné widgety a tie nemajú čo meniť náš iframe.
    if (message.origin !== origin) return
    if (!message.data || message.data.type !== 'event-embed:height') return
    if (message.source !== iframe.contentWindow) return

    var height = parseInt(message.data.height, 10)
    if (height > 0) iframe.style.height = height + 'px'
  })
})()
