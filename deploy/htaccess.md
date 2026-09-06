# Nasadenie Apache pravidiel pre dosah (bot-render, sitemap)

Zdieľanie odkazov a indexácia stoja na dvoch pravidlách v `.htaccess` v koreni
SPA hostu. **Tento súbor sa nenasadzuje `git pull`-om** — aktívny `.htaccess`
je v docroote subdomény a nie je verzovaný, takže sa prenáša ručne.

Predloha: [`ui/public/.htaccess`](../ui/public/.htaccess) — build ju kopíruje do
`ui/dist/.htaccess`, takže je verzovaná a nasadenie z nej len prenesie obsah.

## Čo pravidlá robia

| Pravidlo | Účel |
|---|---|
| `^podujatia/… → /akcie/…` (301) | Podujatia sa presťahovali z `/podujatia/{slug}-{id}` na `/akcie/{id}/{slug}`. Staré adresy sú zaindexované a rozposlané v e-mailoch. Presmerovanie rieši aj Vue Router, ale ten beží až po načítaní JS a vyhľadávač z neho prenos hodnotenia nevyčíta — na to treba 301 tu |
| `^sitemap\.xml$ → /api/sitemap.xml` | Mapa stránok musí byť na koreni SPA hostu, generuje ju Laravel |
| `User-Agent crawlera → /api/prerender?path=…` | Facebook, Messenger, WhatsApp, LinkedIn a vyhľadávače nespúšťajú JS; dostanú serverom vykreslené HTML s OG tagmi a JSON-LD |

Ľudia pravidlá nikdy netrafia — SPA sa im servuje bez zmeny.

## Postup

Produkčný súbor je **[`.htaccess`](../.htaccess) v koreni repozitára** a je
verzovaný, takže sa nasadzuje `git pull`-om. Ručne sa už neprenáša nič.

> **`ui/public/.htaccess` NIE JE produkčný súbor a nikdy sa nekopíruje celý.**
>
> Docroot subdomény je koreň repozitára, nie `ui/dist`. Produkčný súbor preto
> navyše mapuje požiadavky do `ui/dist/` a `/api/` do `api/public/` — a tieto
> pravidlá v šablóne **nie sú**, lebo tá sa píše z pohľadu priečinka s
> buildom. Prepísaním produkčného súboru šablónou zmizne mapovanie na súbory
> a celá subdoména padne na `403` v koreni a `404` všade inde. Presne to sa
> stalo 6. 9. 2026.

1. Zmenu urob v koreňovom `.htaccess` a commitni.
2. Na produkcii `git pull`.
3. Over podľa sekcie nižšie. Prvý test rob na `/` — musí vrátiť `200`,
   nie `403`.

Ak v docroote ešte leží starý neverzovaný `.htaccess`, `git pull` na ňom
zlyhá hláškou *„untracked working tree file '.htaccess' would be overwritten"*.
Premenuj ho (`mv .htaccess .htaccess.zaloha`) a pull zopakuj — záloha sa
zíde na porovnanie, či v ňom nebolo niečo navyše (hlavičky, `ErrorDocument`,
presmerovanie na HTTPS).

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
curl -A "facebookexternalhit/1.1" https://event.hlascirkvi.sk/akcie/42/nazov | head -40
```

Odpoveď musí obsahovať `og:title`, `og:image` a `application/ld+json`
**priamo v HTML**, nie až po JS.

Stará adresa musí odpovedať `301` a `Location` na nový tvar:

```bash
curl -sI https://event.hlascirkvi.sk/podujatia/nazov-42 | head -3
```

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
