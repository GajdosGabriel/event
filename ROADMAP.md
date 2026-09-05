# Audit a roadmapa: z katalógu podujatí na ticketingovú platformu

## Kontext

Cieľ: portál pre **profesionálnych organizátorov a miesta** (kluby, divadlá, kultúrne domy,
agentúry), ktorý sa uživí z **provízie z predaja lístkov**. Požiadavka je „profi riešenie,
nie ďalšia appka v poradí".

Tento dokument je audit stavu k **29. 7. 2026** (commit `860f7c8`) a návrh poradia prác.
Priebežne sa dopĺňa: hotové body sú prečiarknuté a doplnené o odkaz do kódu.
Posledná aktualizácia **3. 9. 2026** — bod 1.6, revízia stavu po augustovom kole
(Q&A, odbery, S3, Account, prihlásenie cez Google/Facebook) a nová **fáza 5**
s nálezmi z auditu 3. 9. 2026.

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
| 1.6 | ~~**ICS export** + „Pridať do kalendára" + tlačidlá na zdieľanie~~ **HOTOVÉ** | `GET /api/events/{id}/calendar.ics` ([EventCalendarController](api/app/Http/Controllers/Public/EventCalendarController.php) nad [IcsGenerator](api/app/Services/Calendar/IcsGenerator.php)); ten istý súbor je prílohou e-mailu o lístku. Odkazy do Google/Outlook kalendára skladá [EventCalendarLinks](api/app/Services/Calendar/EventCalendarLinks.php), v UI [AddToCalendarButton](ui/src/components/AddToCalendarButton.vue) a [ShareButtons](ui/src/components/ShareButtons.vue) |
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
| 3.2 | ~~**Séria / opakované termíny**~~ **HOTOVÉ** | Model: **každý termín je samostatné podujatie**, séria ich len spája (`event_series` + `events.series_id`). Vlastná kapacita, prihlásení aj check-in na termín; spoločný popis, obrázky, miesto a štítky sa prepíšu do všetkých — a to len tie polia, ktoré sa naozaj zmenili ([EventSeriesManager](api/app/Services/Events/EventSeriesManager.php)). Verejný výpis zo série ukáže najbližší termín s odznakom „a ďalších N", detail vypíše ostatné. Rozhodnutia: [api/docs/event-series.md](api/docs/event-series.md) |
| 3.3 | ~~Zapojiť `detect-from-text` do UI + nahranie plagátu/PDF → koncept podujatia~~ **HOTOVÉ** | Verejný tok bez účtu: `POST /api/poster/analyze` → kontrola nálezov → registrácia → `claim` založí event, kanál aj miesto. PDF/DOCX/TXT/obrázok, skenovaný plagát cez vision. Popis: [api/docs/poster-upload-flow.md](api/docs/poster-upload-flow.md) |
| 3.4 | ~~Inbox správ v dashboarde~~ **HOTOVÉ** | `/dashboard/spravy` ([DashboardMessagesPage](ui/src/pages/dashboard/DashboardMessagesPage.vue)) nad [DashboardMessageController](api/app/Http/Controllers/Dashboard/DashboardMessageController.php): vlákna, prečítané/neprečítané, odznak v menu a odpoveď priamo z dashboardu (`parent_message_id`, notifikácia [MessageReplied](api/app/Notifications/MessageReplied.php)). E-mail protistrany sa v UI neukazuje — [MessageResource](api/app/Http/Resources/MessageResource.php) ho neposiela |
| 3.5 | ~~Export účastníkov (CSV), hromadný e-mail účastníkom, pripomienka pred akciou~~ **HOTOVÉ** | Kto je účastník, rieši jedno miesto — [AttendeeDirectory](api/app/Services/Events/AttendeeDirectory.php). CSV s BOM a bodkočiarkou pre slovenský Excel ([AttendeeCsv](api/app/Services/Events/AttendeeCsv.php)), hromadný e-mail cez frontu ([EventAnnouncement](api/app/Notifications/EventAnnouncement.php)), pripomienka X hodín pred začiatkom podľa `events.reminder_hours_before` (príkaz `app:events-send-reminders`, poistka `reminder_sent_at`) |
| 3.6 | ~~Naplánované publikovanie~~ **HOTOVÉ** | `events.publish_at` + stav `scheduled`; preklápa ho `app:events-publish-scheduled` každých päť minút. `published_at` ostáva časom **prvého** zverejnenia. Verejný detail odvtedy filtruje stav ([`publicShow`](api/app/Repositories/Eloquent/EloquentEventRepository.php)) — predtým sa dal koncept prečítať uhádnutím id |
| 3.7 | ~~Check-in na viacerých zariadeniach + offline režim~~ **HOTOVÉ** | Fronta skenov v IndexedDB ([checkinQueue.ts](ui/src/utils/checkinQueue.ts)), prehratie po obnovení spojenia, `scanned_at` na endpointe (aby všetci z výpadku nemali jeden čas) a PWA. Viac zariadení naraz funguje už dnes — check-in je idempotentný a beží pod `lockForUpdate`; k tomu pribudlo obnovovanie počtov každých 20 s (dovtedy každé zariadenie ukazovalo iné číslo) a pri opakovanom skene sa vypíše **kto a kedy** vstupenku označil — pri dvoch telefónoch na dverách to znamená „pustil ho kolega", nie „niekto to skúša druhýkrát". Popis: [api/docs/offline-checkin.md](api/docs/offline-checkin.md) |
| 3.8 | ~~**Otázky z publika**~~ **HOTOVÉ** | QR snímka na plátno → `/q/{token}` → otázka bez registrácie. Nástenka je polymorfná (podujatie aj workshop), moderovanie, hlasovanie a premietacia stena s pollingom. Snímka sa vykresľuje v GD a **nikde sa neukladá** — vzniká pri každom stiahnutí, aj ako `.pptx` s jednou snímkou. Popis: [api/docs/questions-qa.md](api/docs/questions-qa.md) |

### Fáza 4 — Diferenciácia

| # | Práca |
|---|---|
| 4.1 | **Embed widget + API** — ~~widget~~ **prvá polovica HOTOVÁ** (5.7): program organizátora a registrácia na bezplatné podujatie bežia na jeho webe. Zostáva predaj vo widgete (čaká na bránu), vzhľad na mieru a rozlíšenie, koľko registrácií prišlo cez widget |
| 4.2 | Mikrostránka kanála (vlastná doména / podstránka) |
| 4.3 | Reporting predaja pre organizátora: lievik zobrazenie → objednávka → check-in | `View` model a konverzia už existujú |

### Fáza 5 — Nálezy z auditu 3. 9. 2026

Prvé tri body sú chyby, nie funkcie — sú malé a uzavreté. Zvyšok sú príležitosti,
ktoré stoja na dátach, čo už v systéme sú, takže pomer prínos/práca je najlepší
v celom dokumente.

#### 5.1 ~~Obnova hesla vôbec neexistuje~~ **HOTOVÉ**

V repozitári nie je routa, controller, notifikácia ani UI pre „zabudnuté heslo".
Tabuľka `password_reset_tokens` je len prázdny zvyšok z Laravel skeletonu
([create_users_table](api/database/migrations/2025_06_18_105905_create_users_table.php)).

Kto si účet založil e-mailom a heslo zabudne, je **natrvalo mimo** — prihlásenie
cez Google/Facebook ho nezachráni, lebo `registered_via` je `local`. Pri portáli,
kde má používateľ lístky na zaplatené podujatie, je to podpora navyše aj strata
zákazníka. Pred fázou 2 to musí byť vyriešené: v momente, keď účet drží peniaze,
už „napíšte nám a resetneme to ručne" nie je odpoveď.

**Vyriešené 3. 9. 2026.** `POST /api/password/forgot` + `POST /api/password/reset`
([`PasswordResetController`](api/app/Http/Controllers/Auth/PasswordResetController.php))
nad Laravelovým `Password` brokerom; odkaz vedie na stránku SPA `/obnova-hesla/{token}`
([`PasswordResetLink`](api/app/Notifications/PasswordResetLink.php),
[`PublicUrl::passwordReset()`](api/app/Support/PublicUrl.php)), nie na routu API.
Odpoveď na `forgot` je rovnaká pre existujúcu aj neexistujúcu adresu vrátane stavu
„throttled", úspešná obnova maže všetky Sanctum tokeny. Limiter `password-reset`
(3/min na IP+e-mail, 10/h na IP) beží nad brokerovým throttlingom.
Rozhodnutia a čo tým **nie je** vyriešené (zmena hesla prihláseným, účty bez hesla):
[api/docs/password-reset.md](api/docs/password-reset.md).

#### 5.2 ~~Profil sa nedá uložiť s nezmeneným e-mailom~~ **HOTOVÉ**

[`UserUpdateRequest`](api/app/Http/Requests/UserUpdateRequest.php) má
`'email' => 'required|string|email|max:255|unique:users'` **bez `Rule::unique()->ignore($id)`**.
Každé PUT na `/api/dashboard/users/{id}`, ktoré pošle vlastnú (nezmenenú) adresu,
skončí na 422 „e-mail je už obsadený" — teda každé uloženie profilu, pri ktorom
používateľ menil čokoľvek iné.

Test [`DashboardUserUpdateTest`](api/tests/Feature/Users/DashboardUserUpdateTest.php)
to nechytal, lebo v oboch prípadoch posielal **nový** e-mail.

**Vyriešené 3. 9. 2026** — `Rule::unique('users', 'email')->ignore($this->route('user'))`
plus dva testy: uloženie s vlastnou adresou prejde a cudzia adresa naďalej padá na 422.

#### 5.3 ~~`DashboardUserController::show($id)` ignoruje `$id`~~ **HOTOVÉ**

Endpoint volal `dashboardShow((int) request()->user()->id)` — parameter z URL sa
zahodil, takže `GET /api/dashboard/users/{čokoľvek}` vrátil prihláseného
používateľa.

**Vyriešené 3. 9. 2026** — routa rešpektuje `$id`, prístup rieši `UserPolicy::view()`
(sám seba alebo člen spoločného kanála) a cudzí účet končí na 404. Vlastný profil
má vlastnú adresu `GET /api/user`, takže tu nič nechýba. Odpoveď je odteraz
`UserResource` (rovnako ako pri `update()`), nie surový model v poli — v UI
endpoint nikto nevolal, takže to nič nerozbíja. Kryje
[`DashboardUserShowTest`](api/tests/Feature/Users/DashboardUserShowTest.php).

#### 5.4 ~~„Moje lístky" — účet návštevníka~~ **HOTOVÉ**

K vydanému lístku sa dnes dá dostať **len cez `/tickets/{uuid}` z e-mailu**. Kto
si e-mail zmaže, nemá lístok. Prihlásený používateľ nemá v aplikácii ani jednu
stránku, ktorá by mu patrila — dashboard je celý o organizovaní.

**Vyriešené 3. 9. 2026.** Stránka [`/moje-listky`](ui/src/pages/tickets/MyTicketsPage.vue)
nad `GET /api/me/tickets` a `GET|DELETE /api/me/subscriptions`
([Me\TicketController](api/app/Http/Controllers/Me/TicketController.php),
[Me\SubscriptionController](api/app/Http/Controllers/Me/SubscriptionController.php)):
nadchádzajúce a história, odkaz na detail s QR, „do kalendára", zrušenie
registrácie a moje odbery s odhlásením.

Kľúčové rozhodnutie: vlastníctvo lístka **nie je len `user_id`** — objednať sa dá
bez účtu, takže sa páruje aj cez `holder_email`. Odpovedá na to jedno miesto,
[`TicketOwnership`](api/app/Services/Tickets/TicketOwnership.php). Prefix je `me/`,
nie `dashboard/`, lebo sem patrí každý prihlásený bez ohľadu na kanál a rolu.
Rozhodnutia a dôsledky (napr. že zmena e-mailu mení, čo je vo výpise vidieť):
[api/docs/my-tickets.md](api/docs/my-tickets.md).

Pri tom sa opravila aj tichá chyba na detaile vstupenky: `mapTicket` pretypúval
vnorené podujatie z raw dát, takže camelCase polia (`dateRangeLabel`) zostávali
prázdne a na `/tickets/{uuid}` sa nikdy nezobrazil termín.

Naviaže sa na to fáza 2: história objednávok a dokladov už má kde bývať.

#### 5.5 ~~Vyhľadávanie v okolí + mapa zoznamu~~ **HOTOVÉ**

Miesta majú súradnice ([add_coordinates_source_to_venues_table](api/database/migrations/2026_08_20_110000_add_coordinates_source_to_venues_table.php),
[VenueCoordinateResolver](api/app/Services/Geocoding/VenueCoordinateResolver.php))
a Leaflet je v projekte ([MapPicker](ui/src/components/MapPicker.vue)), ale verejný
filter pozná len obec a štítok — teda len to, čo si človek vyberie zo zoznamu.
Na mobile je prvá otázka „čo je dnes pri mne", nie „ktorá obec sa volá ako tá,
v ktorej práve som".

**Vyriešené 4. 9. 2026.** `latitude`/`longitude`/`radius_km` na verejnom výpise;
filter je [`HasCommonFilters::byDistance()`](api/app/Models/Traits/HasCommonFilters.php) —
hrubý obdĺžnik nad indexom `venues_coordinates_index` a až na jeho výsledku
haversine (v rohoch obdĺžnika je bod ďalej než polomer, na to je vlastný test).
V UI tlačidlo „V mojom okolí" s výberom okruhu, vzdialenosť na karte a **tretí
pohľad — mapa** (Leaflet, načítaný `defineAsyncComponent`om, aby nerástol balík
homepage).

Súradnice sú na **mieste**, nie na podujatí (`events` ich nemá), takže podujatie
bez miesta do okruhu nespadne — mapa to hovorí nahlas riadkom „N podujatí nie je
na mape". Poloha sa **neukladá** ani do adresy, ani do `localStorage`; dôsledky
a zvyšné rozhodnutia: [api/docs/nearby-search.md](api/docs/nearby-search.md).

Landing stránky obcí z 1.5 zostávajú — sú zdieľateľné a indexovateľné, čo
„v mojom okolí" zámerne nie je.

#### 5.6 ~~Check-in offline + PWA~~ **HOTOVÉ**

[`EventCheckinScannerPage`](ui/src/pages/events/EventCheckinScannerPage.vue) je
197 riadkov čisto online kódu: každý sken je HTTP request a pri výpadku signálu
sa pri vstupe stojí. V sále, pivnici klubu alebo na lúke to nie je hraničný prípad,
to je bežný večer.

**Vyriešené 4. 9. 2026.** Fronta skenov v IndexedDB s prehraním po obnovení
spojenia; do fronty ide sken aj vtedy, keď požiadavka zlyhá **bez odpovede
servera** — `navigator.onLine` hlási aj sieť bez internetu, teda presne to, čo
býva v sále. Záznam sa maže až po odpovedi (radšej dvakrát než ani raz), čo
unesie idempotentný `checkIn()`. Nový nepovinný `scanned_at` zapíše skutočný čas
príchodu namiesto času, keď sa vrátil signál.

PWA: `manifest.webmanifest`, ikony generované z `favicon.svg` a service worker
so **zámerne opatrnou** stratégiou — HTML najprv zo siete (inak by `git pull`
deploy zasekol ľudí na starom builde), `/assets/*` cache-first (hashované
názvy), `/api` nikdy. Rozhodnutia a čo tým **nie je** vyriešené (offline sa
neoverí platnosť, zoznam prihlásených sa necachuje):
[api/docs/offline-checkin.md](api/docs/offline-checkin.md).

#### 5.7 ~~Embed widget sa dá začať pred platbami~~ **HOTOVÉ**

**Vyriešené 4. 9. 2026.** `/embed/organizator/{slug}-{id}` (program kanála)
a `/embed/podujatie/{slug}-{id}` (jedno podujatie **aj s registráciou**) vo
vlastnom holom layoute, plus loader [`embed.js`](ui/public/embed.js), ktorý iframe
vloží a drží mu výšku podľa obsahu (`postMessage`). V dashboarde na detaile kanála
je panel s hotovým kódom na skopírovanie a náhľadom.

Beží to nad **verejným** API, bez prihlásenia a bez tokenu — cez iframe sa nedá
vytiahnuť nič, čo nie je verejné aj na portáli. Registrácia v ráme je pre
prehliadač tretia strana, takže cookies môžu byť blokované; nevadí, verejná
objednávka lístka je stateless.

**Pri tom sa našla otvorená diera:** portál nemal `X-Frame-Options` ani
`frame-ancestors` **nikde**, takže sa dal dashboard aj prihlásenie natiahnuť do
priehľadného rámu na cudzej stránke (clickjacking). `.htaccess` to odteraz
zakazuje a uvoľňuje len pre `/embed/`. Súbor je mimo gitu — po nasadení sa musí
preniesť ručne, inak zostane widget prázdny **aj** diera otvorená.

Rozhodnutia a čo tým nie je vyriešené (platba vo widgete, vlastné farby,
štatistika zdroja registrácie):
[api/docs/embed-widget.md](api/docs/embed-widget.md).

#### 5.8 Drobnosti

| Čo | Kde | Poznámka |
|---|---|---|
| Fulltext má len `Event` | [`HasCommonFilters::bySearch`](api/app/Models/Traits/HasCommonFilters.php), migrácia `add_fulltext_search_indexes` | Podujatia hľadajú cez `FULLTEXT`, miesta a kanály stále cez `LIKE '%…%'` (`usesFulltextSearch()` vracia `false`). Pri dnešnom objeme to stačí; zapnúť to inde je otázka indexu, nie kódu |
| `ui/index.html` má stále `<title>Event</title>` | [ui/index.html](ui/index.html) | Používateľ vidí „Event" v záložke, kým sa nenačíta JS a `useHead` nedoplní titulok. Crawlerov to netrápi (dostanú prerender), ľudí na pomalej linke áno |
| Nula E2E testov | `ui/` | Komponentových testov je už 16, ale objednávka lístka ani check-in nemajú test cez celý tok. Prvý kandidát na Playwright je práve nákupný lievik — pred fázou 2 |

---

---

## 4. Odporúčanie

Pôvodné poradie bolo **Fáza 0 → 1.1–1.4 → 3.3 → Fáza 2.** Z toho je hotové všetko okrem
Fázy 2 a dvoch zvyškov: **0.4** (prepnúť frontu na produkcii — čistá zmena `.env`) a
**0.5** (2FA pre super-admina). Spolu s nasadením Apache pravidiel z 1.1 sú to posledné
veci, ktoré držia „prevádzkovú podlahu" pod úrovňou, na akej sa dajú prijímať cudzie peniaze.

Ďalej v poradí: **Fáza 2** (najväčšia a má právnu časť mimo kódu — rozbehnúť skoro, aj keď
sa dokončí neskôr) a paralelne **3.2** (série termínov), čo je z Fázy 3 jediná vec, ktorú
organizátori pýtajú denne.

**Doplnenie 3. 9. 2026.** Pred fázu 2 sa predsunuli **5.1–5.4** a všetky štyri sú
**hotové**: obnova hesla, `unique` na profile, `show($id)` a „Moje lístky".

**3.2 (série termínov) je hotová** — bola to posledná vec z fázy 3, ktorú
organizátori pýtali denne.

**Fáza 5 je celá hotová.** Zostáva prevádzková podlaha — **0.4** (fronta na
produkcii) a **0.5** (2FA) — a potom už len **fáza 2 (peniaze)**, ktorá má aj
právnu časť mimo kódu, takže sa oplatí rozbehnúť skoro.

Pri najbližšom nasadení treba do docrootu preniesť nový `.htaccess`: pribudla
prípona `webmanifest` (5.6) a hlavičky proti rámovaniu (5.7). Bez toho zostane
clickjacking otvorený a widget na cudzom webe prázdny.

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

- **Widget (5.7):** frontend, takže ho nekryje PHPUnit. Vložiť `embed.js` do
  ľubovoľnej HTML stránky a overiť, že sa výška iframe dopasuje obsahu. Po
  nasadení `.htaccess`:
  ```bash
  curl -sI https://event.hlascirkvi.sk/podujatia | grep -i x-frame          # SAMEORIGIN
  curl -sI https://event.hlascirkvi.sk/embed/organizator/x-1 | grep -i x-frame  # nič
  ```
- **Offline check-in (5.6):**
  ```bash
  cd api && php artisan test tests/Feature/Events/CheckinOfflineQueueTest.php
  ```
  Ručne: v DevTools prepnúť na Offline, naskenovať — výsledok musí byť modrý
  „Uložené" a v páse pribudne počet čakajúcich. Po prepnutí na Online sa fronta
  odošle sama. Po nasadení overiť, že `/manifest.webmanifest` vracia JSON aj
  crawlerovi (prípona je v `.htaccess`, ktorý je mimo gitu).
- **V okolí (5.5):**
  ```bash
  cd api && php artisan test tests/Feature/Events/PublicEventNearbyTest.php
  ```
  Ručne: zapnúť „V mojom okolí" a skontrolovať, že sa **nič neobjaví v adrese** —
  súradnice v URL sú chyba, nie funkcia.
- **Série termínov (3.2):**
  ```bash
  cd api && php artisan test tests/Feature/Events/EventSeriesTest.php
  ```
  Ručne: publikovať dva termíny série a overiť, že vo verejnom výpise je jedna
  karta s odznakom „a ďalších N termínov", ale **obidve** adresy sú v
  `sitemap.xml` — zbalenie je len vo výpise, nie v indexovaní.
- **Fáza 5 (5.1–5.4):**
  ```bash
  cd api && php artisan test tests/Feature/Auth/PasswordResetTest.php tests/Feature/Users tests/Feature/Tickets
  ```
  Ručne pri 5.4: objednať lístok **bez prihlásenia** na adresu, ktorou sa potom
  zaregistrujete — objednávka musí byť v `/moje-listky` (páruje sa cez
  `holder_email`, nielen `user_id`).
- **Fáza 5:** po oprave 5.2 musí prejsť nový test „uloženie profilu s vlastným
  e-mailom vráti 200" — dnešný `DashboardUserUpdateTest` chybu prehliadne, lebo
  vždy posiela novú adresu:
  ```bash
  cd api && php artisan test tests/Feature/Users
  ```
  Pri 5.1 overiť aj to, že `POST /api/password/forgot` odpovedá rovnako na
  existujúci aj neexistujúci e-mail (inak sa z formulára stane zoznam
  registrovaných) a že platí rate limit.

Po každej fáze: `cd ui && npm run build` a **commitnúť `ui/dist`** — inak sa na produkciu
nasadí starý frontend (pozri [README.md](README.md)).
