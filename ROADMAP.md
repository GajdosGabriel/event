# Audit a roadmapa: z katalógu podujatí na ticketingovú platformu

## Kontext

Cieľ: portál pre **profesionálnych organizátorov a miesta** (kluby, divadlá, kultúrne domy,
agentúry), ktorý sa uživí z **provízie z predaja lístkov**. Požiadavka je „profi riešenie,
nie ďalšia appka v poradí".

Tento dokument je audit stavu k **29. 7. 2026** (commit `860f7c8`) a návrh poradia prác.
Priebežne sa dopĺňa: hotové body sú prečiarknuté a doplnené o odkaz do kódu.
Posledná aktualizácia **2. 8. 2026** — fáza 0 (okrem 0.4 a 0.5) a body 3.4–3.6.

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

| Bolo | Je |
|---|---|
| SPA sa renderuje až v prehliadači, `ui/index.html` je prázdna škrupina s `<title>Event</title>`; OG tagy dopĺňal JS až po načítaní, a to len na detaile podujatia → každé zdieľanie vyzeralo ako holý odkaz | [`PrerenderController`](api/app/Http/Controllers/Public/PrerenderController.php) vracia crawlerom serverom vykreslené HTML s plnou `<head>` a čitateľným telom; Apache ich tam púšťa podľa `User-Agent` ([deploy/htaccess.md](deploy/htaccess.md)) |
| Žiadne JSON-LD `Event` | [`JsonLd`](api/app/Services/Seo/JsonLd.php) — `Event` s `offers` z typov lístkov, `Place`, `Organization`, `ItemList`, `BreadcrumbList` |
| Žiadny `sitemap.xml`, `robots.txt` len na API hoste | [`SitemapController`](api/app/Http/Controllers/Public/SitemapController.php) (cache 1 h, len živý publikovaný obsah) + [`ui/public/robots.txt`](ui/public/robots.txt) |
| URL `/events/42` — číselné id, hoci `slug` v DB je | `/podujatia/{slug}-{id}`, `/miesta/…`, `/organizatori/…`; staré adresy presmerúvajú a `canonical` ukazuje na novú. Zdroj pravdy je [`PublicUrl`](api/app/Support/PublicUrl.php) a jeho dvojička [`publicUrl.ts`](ui/src/utils/publicUrl.ts) |
| Neexistuje verejná routa so zoznamom — všetko je homepage `/` s query parametrami | [`EventListPage`](ui/src/pages/events/EventListPage.vue): `/podujatia`, `/podujatia/mesto/{slug}`, `/podujatia/tema/{slug}`, `/podujatia/tento-vikend`, každá s vlastným `title`, popisom a `canonical` |

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

- ~~`POST /dashboard/events/detect-from-text` nikto nevolá~~ — vyriešené v 3.3, tok je
  popísaný v [api/docs/poster-upload-flow.md](api/docs/poster-upload-flow.md).
- Frontend má 3 testy (iba utility), nula testov komponentov, žiadne E2E.
- `EventFactory` losuje `status` zo **všetkých** stavov, takže testy nad `futureEvent`
  závisia od hodu kockou a musia si stav pripnúť samy. Nové testy to robia, staršie nie —
  `DashboardEventDestroyTest` a `EventTagAssignmentTest` preto občas padnú aj na čistom
  strome.

---

## 2. Poziciovanie: čo z toho robí „nie ďalšiu appku"

Konkurenčnú výhodu **nezískame** lepším zoznamom podujatí ani nižšou províziou — to sa dá
skopírovať za týždeň. To, čo už máme a čo je ťažko napodobiteľné, je **AI pipeline na vznik
podujatia z ľubovoľného materiálu**: URL, PDF plagát, text pozvánky, RSS. Konkurencia
(Predpredaj, Ticketportal, Inviton, Tootoot, Goout) núti organizátora vypĺňať formulár.

Navrhované poziciovanie: **„Pošli plagát. O zvyšok sa postaráme."**

1. Organizátor hodí do systému plagát/PDF/text/odkaz → vznikne hotový koncept podujatia.
2. Portál ho dostane tam, kde ho ľudia nájdu — Google Events, sociálne siete, mestské stránky.
3. Portál predá lístky a vyplatí peniaze.

Body 1 a 3 sú produkt. Bod 2 je dôvod, prečo organizátor zaplatí províziu. Dnes fungujú
body 1 a 2, bod 3 neexistuje.

**Čo vedome nerobiť:** sedadlové mapy (drahé, malý trh), vlastný newsletter engine,
mobilné aplikácie, vlastné SSR prepísanie celej SPA.

---

## 3. Navrhované poradie prác

### Fáza 0 — Prevádzková podlaha (~1–2 týždne)

Musí byť hotové skôr, než sa dotkneme platieb. Malé, uzavreté zmeny.

| # | Práca | Kde |
|---|---|---|
| 0.1 | ~~Pustiť existujúci `HtmlBodyCleaner` na `body` pri zápise (event/canal/venue)~~ **HOTOVÉ** | Mutator v [`SanitizesHtmlBody`](api/app/Models/Traits/SanitizesHtmlBody.php) na Evente, Kanáli aj Mieste — čistí sa pri zápise, nech príde odkiaľkoľvek |
| 0.2 | ~~Sentry + upozornenie na produkčné chyby~~ **HOTOVÉ** | [api/config/sentry.php](api/config/sentry.php), zapína sa cez `SENTRY_LARAVEL_DSN` |
| 0.3 | ~~Heartbeat pre webcron~~ **HOTOVÉ** | [`CronHeartbeat`](api/app/Support/CronHeartbeat.php) pingne watchdog po úspešnom `schedule-run` |
| 0.4 | Na produkcii `QUEUE_CONNECTION=database` + overiť, že worker naozaj beží | `.env` (bez zmeny kódu). Kód je pripravený — `queue:work` je v [routes/console.php](api/routes/console.php) |
| 0.5 | 2FA (TOTP) pre `super-admin` | nový middleware + `users` migrácia |
| 0.6 | ~~`hash_equals` na cron token~~ **HOTOVÉ** | [`CronToken`](api/app/Support/CronToken.php) — jedno miesto pre web aj API routu |
| 0.7 | ~~`Model::preventLazyLoading()` v non-produkčnom prostredí~~ **HOTOVÉ** | [AppServiceProvider](api/app/Providers/AppServiceProvider.php) |

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

### Fáza 3 — Práca organizátora (dá sa robiť paralelne s fázou 2)

| # | Práca | Prečo |
|---|---|---|
| 3.1 | ~~**Tím kanála**: pozvánka e-mailom, per-kanál rola (owner / editor / vstup), zápis do `canal_user`~~ **HOTOVÉ** | Rola je v `canal_user.role` ([CanalRole](api/app/Enums/CanalRole.php)), pozvánky v `canal_invitations`, práva rieši `User::canInCanal()` v policies. Globálna spatie rola ostáva len ako hrubé sito pre `permission:` middleware |
| 3.2 | **Séria / opakované termíny** — jedno podujatie, viac termínov, spoločný popis a typy lístkov | Najväčšia denná bolesť klubu a divadla; dnes je riešením „duplikovať" |
| 3.3 | ~~Zapojiť `detect-from-text` do UI + nahranie plagátu/PDF → koncept podujatia~~ **HOTOVÉ** | Verejný tok bez účtu: `POST /api/poster/analyze` → kontrola nálezov → registrácia → `claim` založí event, kanál aj miesto. PDF/DOCX/TXT/obrázok, skenovaný plagát cez vision. Popis: [api/docs/poster-upload-flow.md](api/docs/poster-upload-flow.md) |
| 3.4 | ~~Inbox správ v dashboarde~~ **HOTOVÉ** | `/dashboard/spravy` ([DashboardMessagesPage](ui/src/pages/dashboard/DashboardMessagesPage.vue)) nad [DashboardMessageController](api/app/Http/Controllers/Dashboard/DashboardMessageController.php): vlákna, prečítané/neprečítané, odznak v menu a odpoveď priamo z dashboardu (`parent_message_id`, notifikácia [MessageReplied](api/app/Notifications/MessageReplied.php)). E-mail protistrany sa v UI neukazuje — [MessageResource](api/app/Http/Resources/MessageResource.php) ho neposiela |
| 3.5 | ~~Export účastníkov (CSV), hromadný e-mail účastníkom, pripomienka pred akciou~~ **HOTOVÉ** | Kto je účastník, rieši jedno miesto — [AttendeeDirectory](api/app/Services/Events/AttendeeDirectory.php). CSV s BOM a bodkočiarkou pre slovenský Excel ([AttendeeCsv](api/app/Services/Events/AttendeeCsv.php)), hromadný e-mail cez frontu ([EventAnnouncement](api/app/Notifications/EventAnnouncement.php)), pripomienka X hodín pred začiatkom podľa `events.reminder_hours_before` (príkaz `app:events-send-reminders`, poistka `reminder_sent_at`) |
| 3.6 | ~~Naplánované publikovanie~~ **HOTOVÉ** | `events.publish_at` + stav `scheduled`; preklápa ho `app:events-publish-scheduled` každých päť minút. `published_at` ostáva časom **prvého** zverejnenia. Verejný detail odvtedy filtruje stav ([`publicShow`](api/app/Repositories/Eloquent/EloquentEventRepository.php)) — predtým sa dal koncept prečítať uhádnutím id |
| 3.7 | Check-in na viacerých zariadeniach + offline režim | |

### Fáza 4 — Diferenciácia

| # | Práca |
|---|---|
| 4.1 | **Embed widget + API** — organizátor predáva na vlastnom webe, provízia stále tečie k nám. Toto je typicky moment, keď platforma prestane byť nahraditeľná |
| 4.2 | Mikrostránka kanála (vlastná doména / podstránka) |
| 4.3 | Reporting predaja pre organizátora: lievik zobrazenie → objednávka → check-in | `View` model a konverzia už existujú |

---

## 4. Odporúčanie

Pôvodné poradie bolo **Fáza 0 → 1.1–1.4 → 3.3 → Fáza 2.** Z toho je hotové všetko okrem
Fázy 2 a dvoch zvyškov: **0.4** (prepnúť frontu na produkcii — čistá zmena `.env`) a
**0.5** (2FA pre super-admina). Spolu s nasadením Apache pravidiel z 1.1 sú to posledné
veci, ktoré držia „prevádzkovú podlahu" pod úrovňou, na akej sa dajú prijímať cudzie peniaze.

Ďalej v poradí: **Fáza 2** (najväčšia a má právnu časť mimo kódu — rozbehnúť skoro, aj keď
sa dokončí neskôr) a paralelne **3.2** (série termínov), čo je z Fázy 3 jediná vec, ktorú
organizátori pýtajú denne.

---

## 5. Overenie

```bash
cd api && php artisan test tests/Feature/Events tests/Feature/Tickets
```

- **XSS (0.1):** pokrýva `tests/Feature/Events/DashboardEventBodyXssTest.php` a
  `tests/Unit/Events/BodySanitizationTest.php` — skript uložený cez dashboard sa
  na verejný detail nesmie dostať.
- **Fáza 3 (3.4–3.6):**
  ```bash
  cd api && php artisan test tests/Feature/Messages tests/Feature/Events/ScheduledPublishingTest.php tests/Feature/Events/AttendeeExportAndMailingTest.php
  ```
  Ručne ešte stojí za pozretie: naplánovaný event v `/dashboard/events/{id}/edit`
  (stav „Naplánovaný" + termín), `/dashboard/spravy` s odznakom neprečítaných
  a tlačidlo „Export CSV" na karte prihlásených — CSV sa musí v Exceli otvoriť
  po stĺpcoch a s diakritikou.
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
