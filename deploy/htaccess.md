# Nasadenie Apache pravidiel pre dosah (bot-render, sitemap)

Zdieľanie odkazov a indexácia stoja na dvoch pravidlách v `.htaccess` v koreni
SPA hostu. **Tento súbor sa nenasadzuje `git pull`-om** — aktívny `.htaccess`
je v docroote subdomény a nie je verzovaný, takže sa prenáša ručne.

Predloha: [`ui/public/.htaccess`](../ui/public/.htaccess) — build ju kopíruje do
`ui/dist/.htaccess`, takže je verzovaná a nasadenie z nej len prenesie obsah.

## Čo pravidlá robia

| Pravidlo | Účel |
|---|---|
| `^sitemap\.xml$ → /api/sitemap.xml` | Mapa stránok musí byť na koreni SPA hostu, generuje ju Laravel |
| `User-Agent crawlera → /api/prerender?path=…` | Facebook, Messenger, WhatsApp, LinkedIn a vyhľadávače nespúšťajú JS; dostanú serverom vykreslené HTML s OG tagmi a JSON-LD |

Ľudia pravidlá nikdy netrafia — SPA sa im servuje bez zmeny.

## Postup

1. Zálohuj existujúci `.htaccess` v docroote.
2. Prenes obsah `ui/public/.htaccess`. Ak je v aktívnom súbore niečo navyše
   (presmerovanie na HTTPS, hlavičky, `ErrorDocument`), **nechaj to** a doplň
   len bloky 1 a 2 **pred** SPA fallback.
3. Over podľa sekcie nižšie.

### Ak interný prepis na `/api/` nefunguje

Blok 2 predpokladá, že `/api` je pod tým istým docrootom (tak, ako to dnes
predpokladá aj SPA fallback, ktorý `/api` vynecháva z prepisu). Ak by API bežalo
na inom vhoste, prepis potichu spadne na SPA fallback a crawler dostane prázdnu
škrupinu. Vtedy nahraď `RewriteRule` v bloku 2 jednou z týchto možností:

```apache
# a) proxy — vyžaduje mod_proxy a mod_proxy_http
RewriteRule ^(.*)$ https://api.example.sk/api/prerender?path=/$1 [P,QSA,L]

# b) presmerovanie — funguje vždy, crawler nasleduje 302 a canonical z odpovede
RewriteRule ^(.*)$ https://api.example.sk/api/prerender?path=/$1 [R=302,QSA,L]
```

Možnosť (b) je fallback: Facebook aj Google presmerovanie nasledujú a riadia sa
`og:url` / `<link rel="canonical">`, ktoré ukazujú späť na SPA adresu.

## Overenie

```bash
curl -A "facebookexternalhit/1.1" https://event.hlascirkvi.sk/podujatia/nazov-42 | head -40
```

Odpoveď musí obsahovať `og:title`, `og:image` a `application/ld+json`
**priamo v HTML**, nie až po JS.

```bash
curl -s https://event.hlascirkvi.sk/sitemap.xml | head -20
```

Musí vrátiť validné XML s `<urlset>` a len s publikovanými podujatiami.

```bash
curl -sI https://event.hlascirkvi.sk/podujatia/nazov-42 | head -3
```

Bez crawler `User-Agent` musí prísť `index.html` (SPA), nie prerender.

Nakoniec: [Facebook Sharing Debugger](https://developers.facebook.com/tools/debug/)
a [Google Rich Results Test](https://search.google.com/test/rich-results) na
adresu podujatia; v Search Console odoslať `sitemap.xml`.

## Súvisiace nastavenie

`FRONTEND_URL` v `api/.env` musí byť **verejná adresa SPA hostu** (napr.
`https://event.hlascirkvi.sk`). Z nej sa skladá `canonical`, `og:url` aj každá
adresa v `sitemap.xml` — pri zlej hodnote budú odkazy ukazovať mimo portál.

## Zmeny z 4. 9. 2026 (5.6 a 5.7)

Predloha `ui/public/.htaccess` dostala tri veci, ktoré sa **musia preniesť do
docrootu**, inak zostanú nefunkčné:

1. **`webmanifest` medzi príponami**, ktoré sa neprepisujú na prerender. Bez
   toho dostane crawler namiesto manifestu HTML a PWA sa neinštaluje.
2. **Značka `EVENT_EMBED`** pre cesty `/embed/...` (pravidlo 2b).
3. **Hlavičky proti rámovaniu.** Portál dovtedy neposielal `X-Frame-Options`
   ani `frame-ancestors` — dal sa teda celý, vrátane dashboardu a prihlásenia,
   natiahnuť do priehľadného rámu na cudzej stránke. Odteraz je rámovanie
   zakázané všade okrem widgetu.

Overenie po prenesení:

```bash
curl -sI https://<portal>/podujatia | grep -i -E 'x-frame|content-security'
# → X-Frame-Options: SAMEORIGIN + frame-ancestors 'self'

curl -sI https://<portal>/embed/organizator/x-1 | grep -i -E 'x-frame|content-security'
# → X-Frame-Options tam byť NESMIE, frame-ancestors *

curl -sI https://<portal>/manifest.webmanifest | head -3
# → 200 a application/manifest+json (nie text/html)
```

Ak hlavičky nechodia vôbec, na hostingu pravdepodobne nie je `mod_headers` —
celý blok je v `<IfModule>`, takže Apache nespadne, len sa ticho preskočí.
