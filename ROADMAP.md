# Audit a roadmapa: z katalógu podujatí na ticketingovú platformu

## Kontext

Cieľ: portál pre **profesionálnych organizátorov a miesta** (kluby, divadlá, kultúrne domy,
agentúry), ktorý sa uživí z **provízie z predaja lístkov**. Požiadavka je „profi riešenie,
nie ďalšia appka v poradí".

Tento dokument je audit stavu k **29. 7. 2026** (commit `860f7c8`) a návrh poradia prác.

---

## 1. Čo audit zistil

### Silné stránky — na tomto sa dá stavať

| Oblasť | Stav |
|---|---|
| Kvalita backendu | 100 test súborov / ~399 testov proti reálnej MySQL, 9 policies, 24 FormRequestov, 7 ladených rate limiterov, CI |
| Doménový model | Canal (tenant) / Event / Venue / TicketType / Ticket (objednávka) / Admission (sedadlo) je správne navrhnutý |
| Ticketing okrem platieb | Kapacity pod `lockForUpdate`, predajné okná, min/max na objednávku, workshopy, čakačka s FIFO posunom, RSVP potvrdzovanie, QR + skener check-in, undo |
| Import + AI | ~3,5k riadkov pipeline (ecav/tkkbs/vyveska + RSS fallback + PDF → text/obrázky + AI detekcia organizátora a miesta). **Toto v SK nikto iný nemá.** |
| Analytika | [`OverviewStats`](api/app/Services/Stats/OverviewStats.php) (~850 riadkov) — obdobia s porovnaním, trendy, konverzia zobrazenie→registrácia, „vyžaduje pozornosť" zoznam |
| Počítanie zobrazení | Bez cookie a bez ukladania IP, denne rotujúci hash, vlastné zobrazenia sa nerátajú |
| Dokumentácia | Nadpriemerná — [api/docs/](api/docs), komentáre vysvetľujú *prečo* a zaznamenávajú reálne incidenty |

Toto **nie je** prototyp. Inžiniersky je to nad úrovňou väčšiny podobných projektov.

### Tvrdá diagnóza

Produkt je dnes **katalóg podujatí s bezplatnou registráciou**. Aby sa stal ticketingovou
platformou pre profesionálov, chýbajú tri veci — a chýbajú v tomto poradí dôležitosti:

#### A. Nedá sa zaplatiť, teda nie je z čoho brať províziu

`TicketPaymentStatus::Paid` sa v kóde nikdy **nezapisuje** — iba číta v štatistikách
([OverviewStats.php:517](api/app/Services/Stats/OverviewStats.php)). Platená objednávka
vznikne ako `Reserved` + `Pending`
([EloquentTicketRepository.php:164](api/app/Repositories/Eloquent/EloquentTicketRepository.php))
a v tom stave zostane navždy. Žiadna brána, žiadny webhook, žiadne refundácie, žiadne
výplaty, žiadne doklady. Rezervácia navyše **drží kapacitu bez expirácie**, takže platené
podujatie sa dá zablokovať fiktívnymi objednávkami.

#### B. Portál dnes nedodá organizátorovi publikum — a to je to, čo si profík kupuje

**Stav k 1. 8. 2026: vyriešené (fáza 1, body 1.1–1.5).** Pôvodná diagnóza a čo ju
nahradilo:



Otvorené ostáva 1.6 (ICS export a tlačidlá na zdieľanie) a nasadenie Apache
pravidiel na produkcii — bez nich crawler stále dostane prázdnu škrupinu.

#### C. Prevádzková podlaha je nižšia, než unesie cudzie peniaze

| Riziko | Detail |
|---|---|
| Stored XSS na 3 verejných stránkach | `v-html` na `event.body` / `venue.body` / `canal.body`, pričom validácia je len `['nullable','string']` ([EventStoreRequest.php:36](api/app/Http/Requests/EventStoreRequest.php)). Sanitizér [`HtmlBodyCleaner`](api/app/Services/Imports/HtmlBodyCleaner.php) **existuje**, ale beží len na importe a na AI výstupe — nie na to, čo pošle organizátor |
| Žiadny error tracking | Bez Sentry/Flare; produkčné chyby idú do súboru a nikto sa o nich nedozvie |
| Cron je nemonitorovaný SPOF | Celý systém visí na externom webcrone na `/cron/schedule-run`. Ak ticho vypadne, zastane fronta, importy, expirácia lístkov aj archivácia — **bez akéhokoľvek upozornenia** |
| Fronta beží `sync` | E-maily a generovanie náhľadov bežia priamo v HTTP requeste (default v [api/.env.example](api/.env.example)) |
| Bez 2FA | Super-admin má prístup k celej DB a k `/admin/tools`, chránený iba heslom |
| `!==` na porovnanie cron tokenu | [api/routes/web.php:16](api/routes/web.php) vs. správne `hash_equals` v [api/routes/api.php:410](api/routes/api.php) |



### Menšie, ale hlásené nálezy

- `POST /dashboard/events/detect-from-text` (z textu plagátu spraví podujatie) je hotový
  na backende aj v `ui/src/api/events.ts`, ale **žiadna komponenta ho nevolá**.
- Frontend má 3 testy (iba utility), nula testov komponentov, žiadne E2E.

---


## 3. Navrhované poradie prác


### Fáza 1 — Dosah (~3–5 týždňov)

Toto je argument, ktorým sa predáva provízia. Robiť **pred** platbami — bez publika nemá
platobná brána čo spracovať.

| # | Práca | Poznámka |
|---|---|---|
| 1.1 | ~~**Bot-render vrstva**~~ **HOTOVÉ** | [`PrerenderController`](api/app/Http/Controllers/Public/PrerenderController.php) + [šablóny](api/resources/views/prerender). **Zostáva nasadiť Apache pravidlá** — postup v [deploy/htaccess.md](deploy/htaccess.md) |
| 1.2 | ~~**JSON-LD `Event`**~~ **HOTOVÉ** | [`JsonLd`](api/app/Services/Seo/JsonLd.php). `offers` berie cenu a `availability` z typov lístkov, s fallbackom na `price_amount` |
| 1.3 | ~~**Slug URL** `/podujatia/{slug}-{id}`~~ **HOTOVÉ** | Routuje sa len id za poslednou pomlčkou, takže odkaz prežije premenovanie. Staré `/events/{id}` presmerúva SPA; 301 na úrovni Apache je súčasťou nasadenia 1.1 |
| 1.4 | ~~**`sitemap.xml`** + `robots.txt`~~ **HOTOVÉ** | [`SitemapController`](api/app/Http/Controllers/Public/SitemapController.php), cache 1 h; obce a štítky sú v mape len ak majú živé podujatia |
| 1.5 | ~~**Indexovateľné landing stránky**~~ **HOTOVÉ** | [`EventListPage`](ui/src/pages/events/EventListPage.vue). Obce dostali `slug` (migrácia `add_slug_to_municipalities_table`), víkendové okno drží [`EventTimeframe`](api/app/Support/EventTimeframe.php) pre SPA aj prerender |
| 1.6 | **ICS export** (`/podujatia/{id}.ics`) + „Pridať do kalendára" + tlačidlá na zdieľanie | |
| 1.7 | ~~Počet výsledkov na verejnom zozname~~ **HOTOVÉ**; dátumový filter v UI ostáva | Verejný controller už berie `range=weekend` (`date_from`/`date_to`), chýba len ovládanie v rozhraní |

### Fáza 2 — Peniaze (~6–10 týždňov)

| # | Práca | Poznámka |
|---|---|---|
| 2.1 | Abstrakcia `PaymentGateway` + jeden driver | **Odporúčanie: Stripe Connect** — má hotové výplaty tretím stranám, KYC, SCA, refundácie a doklady. GoPay/Besteron majú nižšie poplatky, ale výplaty a KYC organizátorov si treba postaviť samostatne (a to je regulovaná činnosť) |
| 2.2 | Stavový automat objednávky + **expirácia rezervácie** (~15 min hold, potom uvoľní kapacitu) | Dnes rezervácia drží miesto navždy — pri platenom podujatí zneužiteľné |
| 2.3 | Webhook endpoint s idempotenciou + denný reconciliation report | |
| 2.4 | `platform_fee_amount` na `Ticket` + rozhodnutie, kto poplatok platí (service fee na kupujúceho je v odvetví štandard) | |
| 2.5 | Refundácie a storno politika na úrovni podujatia | |
| 2.6 | Doklady/faktúry, DPH, výplatná zostava pre organizátora | |
| 2.7 | Čakačka → model „ponuka s expiráciou" namiesto auto-posunu | Postup je už rozpísaný v [api/docs/workshop-waitlist.md](api/docs/workshop-waitlist.md) |
| 2.8 | Zľavové kódy | |
| 2.9 | **Právne (nie kód, ale bez toho sa provízia vyberať nedá):** VOP, sprostredkovateľská zmluva s organizátorom, GDPR/DPA, reklamačný poriadok, rola prevádzkovateľa vs. sprostredkovateľa platby | Riešiť paralelne s 2.1 |



### Fáza 4 — Diferenciácia

| # | Práca |
|---|---|
| 4.1 | **Embed widget + API** — organizátor predáva na vlastnom webe, provízia stále tečie k nám. Toto je typicky moment, keď platforma prestane byť nahraditeľná |
| 4.2 | Mikrostránka kanála (vlastná doména / podstránka) |
| 4.3 | Reporting predaja pre organizátora: lievik zobrazenie → objednávka → check-in | `View` model a konverzia už existujú |

---

## 4. Odporúčanie

Ak treba škrtať, poradie je: **Fáza 0 → 1.1–1.4 → 3.3 → Fáza 2.**

Dôvod: fáza 0 je lacná a odstraňuje riziká, ktoré by pri platbách boli existenčné.
Body 1.1–1.4 sú jednorazová práca, ktorá odomkne organický dosah a bez nej je provízia
neobhájiteľná. Bod 3.3 je pár dní práce nad hotovým backendom a je to jediná vec, ktorou
sa dnes odlíšime. Fáza 2 je najväčšia a má právnu časť, ktorá beží mimo kódu — treba ju
rozbehnúť skoro, aj keď sa dokončí neskôr.

---

## 5. Overenie

```bash
cd api && php artisan test tests/Feature/Events tests/Feature/Tickets
```

- **XSS (0.1):** uložiť podujatie s `body` obsahujúcim `<script>alert(1)</script>` cez
  `POST /api/dashboard/events`, potom `GET /api/events/{id}` — skript musí byť preč.
  Doplniť test do `api/tests/Feature/Events/`.
- **Fronta (0.4):** `php artisan queue:work --once` a overiť, že notifikácia odíde;
  na produkcii skontrolovať tabuľku `jobs`, či sa nehromadí.
- **Dosah (1.1–1.5):** `cd api && php artisan test tests/Feature/Seo` pokrýva OG tagy,
  JSON-LD, sanitizáciu `body`, kanonickú adresu zo starej číselnej cesty aj obsah
  sitemapy. Po nasadení Apache pravidiel ešte na produkcii:
  ```bash
  curl -A "facebookexternalhit/1.1" https://event.hlascirkvi.sk/podujatia/nazov-42 | head -40
  curl -s https://event.hlascirkvi.sk/sitemap.xml | head -20
  ```
  Prvé musí vrátiť `og:title`, `og:image` a `application/ld+json` **v HTML odpovedi**,
  nie až po JS; druhé validné XML len s publikovanými podujatiami. Potom Facebook
  Sharing Debugger, Google Rich Results Test a odoslanie sitemapy v Search Console.
  Detailný postup a riešenie, keď interný prepis na `/api/` nefunguje:
  [deploy/htaccess.md](deploy/htaccess.md).

Po každej fáze: `cd ui && npm run build` a **commitnúť `ui/dist`** — inak sa na produkciu
nasadí starý frontend (pozri [README.md](README.md)).
