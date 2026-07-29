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

Profesionál si vyberá platformu podľa dvoch čísel: poplatok a dosah. Dosah je dnes prakticky nulový:

- SPA sa renderuje až v prehliadači. [ui/index.html](ui/index.html) je prázdna škrupina
  s `<title>Event</title>`. OG tagy sa dopĺňajú JavaScriptom **až po načítaní**, a to len
  na detaile podujatia ([EventPublicShowPage.vue:380](ui/src/pages/events/EventPublicShowPage.vue)).
  Facebook, Messenger, WhatsApp ani LinkedIn JS nespúšťajú → **každé zdieľanie podujatia
  vyzerá ako holý odkaz bez názvu, popisu a obrázka.** Pre portál s podujatiami je zdieľanie
  hlavný distribučný kanál.
- **Žiadne JSON-LD `Event`** → podujatia nemôžu padnúť do Google Events / „čo robiť v okolí".
- **Žiadny `sitemap.xml`**, `robots.txt` len na API hoste.
- URL sú `/events/42` — číselné ID, hoci `slug` v DB existuje a nepoužíva sa.
- **Neexistuje verejná routa so zoznamom podujatí.** Všetko je homepage `/` s query
  parametrami ([ui/src/router/index.ts:14](ui/src/router/index.ts)) → žiadne indexovateľné
  stránky typu „koncerty v Košiciach" alebo „podujatia tento víkend".

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

## 2. Poziciovanie: čo z toho robí „nie ďalšiu appku"

Konkurenčnú výhodu **nezískame** lepším zoznamom podujatí ani nižšou províziou — to sa dá
skopírovať za týždeň. To, čo už máme a čo je ťažko napodobiteľné, je **AI pipeline na vznik
podujatia z ľubovoľného materiálu**: URL, PDF plagát, text pozvánky, RSS. Konkurencia
(Predpredaj, Ticketportal, Inviton, Tootoot, Goout) núti organizátora vypĺňať formulár.

Navrhované poziciovanie: **„Pošli plagát. O zvyšok sa postaráme."**

1. Organizátor hodí do systému plagát/PDF/text/odkaz → vznikne hotový koncept podujatia.
2. Portál ho dostane tam, kde ho ľudia nájdu — Google Events, sociálne siete, mestské stránky.
3. Portál predá lístky a vyplatí peniaze.

Body 1 a 3 sú produkt. Bod 2 je dôvod, prečo organizátor zaplatí províziu. Dnes funguje
bod 1 (len nie je zapojený do UI), bod 2 je rozbitý a bod 3 neexistuje.

**Čo vedome nerobiť:** sedadlové mapy (drahé, malý trh), vlastný newsletter engine,
mobilné aplikácie, vlastné SSR prepísanie celej SPA.

---

## 3. Navrhované poradie prác

### Fáza 0 — Prevádzková podlaha (~1–2 týždne)

Musí byť hotové skôr, než sa dotkneme platieb. Malé, uzavreté zmeny.

| # | Práca | Kde |
|---|---|---|
| 0.1 | Pustiť existujúci `HtmlBodyCleaner` na `body` pri zápise (event/canal/venue) | [HtmlBodyCleaner.php](api/app/Services/Imports/HtmlBodyCleaner.php) → `EventStoreRequest`, `CanalStoreRequest`, `VenueStoreRequest` alebo mutator na modeloch |
| 0.2 | Sentry (alebo Flare) + upozornenie na produkčné chyby | `api/config/logging.php`, `api/bootstrap/app.php` |
| 0.3 | Heartbeat pre webcron — po úspešnom `schedule-run` pingnúť externý watchdog, ktorý zaalarmuje pri výpadku | [api/routes/web.php](api/routes/web.php), [api/routes/console.php](api/routes/console.php) |
| 0.4 | Na produkcii `QUEUE_CONNECTION=database` + overiť, že worker naozaj beží | `.env` (bez zmeny kódu) |
| 0.5 | 2FA (TOTP) pre `super-admin` | nový middleware + `users` migrácia |
| 0.6 | `hash_equals` na cron token | [api/routes/web.php:16](api/routes/web.php) |
| 0.7 | `Model::preventLazyLoading()` v non-produkčnom prostredí — poistka proti N+1 | `AppServiceProvider` |

### Fáza 1 — Dosah (~3–5 týždňov)

Toto je argument, ktorým sa predáva provízia. Robiť **pred** platbami — bez publika nemá
platobná brána čo spracovať.

| # | Práca | Poznámka |
|---|---|---|
| 1.1 | **Bot-render vrstva**: Laravel blade routa, ktorá pre `/podujatia/*`, `/miesta/*`, `/organizatori/*` vráti plnú `<head>` s OG + Twitter + JSON-LD. Apache podľa User-Agent presmeruje crawlerov sem, ľudia idú do SPA | Najmenší zásah — žiadne SSR prepísanie. Alternatíva: prerender verejnej sekcie do statického HTML pri builde |
| 1.2 | **JSON-LD `Event`** — `name`, `startDate`, `endDate`, `location` (Venue má lat/lng), `image`, `organizer`, `offers` (cena + `availability` z `remaining_capacity`) | Vstupenka do Google Events |
| 1.3 | **Slug URL** `/podujatia/{slug}-{id}` + 301 z `/events/{id}` | `slug` už v DB je |
| 1.4 | **`sitemap.xml`** z publikovaných podujatí, kanálov, miest a landing stránok + `robots.txt` na UI hoste | Laravel routa, cache 1 h |
| 1.5 | **Indexovateľné landing stránky**: `/podujatia/{mesto}`, `/podujatia/{stitok}`, `/podujatia/tento-vikend` s vlastným title/description a JSON-LD `ItemList` | Dáta už existujú — `Municipality`, `Tag`, `scopeByDateRange` |
| 1.6 | **ICS export** (`/podujatia/{id}.ics`) + „Pridať do kalendára" + tlačidlá na zdieľanie | |
| 1.7 | Dátumový filter a počet výsledkov na verejnom zozname | `scopeByDateRange` existuje, verejný controller ho neposiela |

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
| 3.3 | Zapojiť `detect-from-text` do UI + nahranie plagátu/PDF → koncept podujatia | Backend hotový, stačí UI. **Najlacnejší diferenciátor, aký máme** |
| 3.4 | Inbox správ v dashboarde | `read_at` a `recipient_user_id` už v schéme sú; „neprečítané správy" v štatistikách dnes odkazujú na `null` |
| 3.5 | Export účastníkov (CSV), hromadný e-mail účastníkom, pripomienka pred akciou | |
| 3.6 | Naplánované publikovanie | `scheduled` už v enume |
| 3.7 | Check-in na viacerých zariadeniach + offline režim | |

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
- **Bot render (1.1–1.2):**
  ```bash
  curl -A "facebookexternalhit/1.1" https://event.hlascirkvi.sk/podujatia/42 | head -40
  ```
  musí vrátiť `og:title`, `og:image` a `application/ld+json` **v HTML odpovedi**, nie až po JS.
  Potom overiť cez Facebook Sharing Debugger a Google Rich Results Test.
- **Sitemap (1.4):** `curl https://event.hlascirkvi.sk/sitemap.xml` — validné XML,
  obsahuje len publikované podujatia.

Po každej fáze: `cd ui && npm run build` a **commitnúť `ui/dist`** — inak sa na produkciu
nasadí starý frontend (pozri [README.md](README.md)).
