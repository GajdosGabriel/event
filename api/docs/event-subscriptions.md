# „Pripomeň mi" — odber podujatia bez účtu

## Prečo to vzniklo

Na verejnom detaile bezplatného podujatia bez lístkov sa **nedalo spraviť nič**.
Registračná sekcia sa skryje (`tickets_enabled` je vypnuté) a s ňou aj mobilná
lišta, takže návštevníkovi zostalo tlačidlo „Kopírovať odkaz". Takto vyzerá
väčšina importovaného katalógu — teda väčšina stránok, na ktoré ľudia chodia
z vyhľadávača.

Odber je najmenší možný záväzok medzi „pozrel som si to" a „objednal som lístok":
jedno pole, žiadny účet.

## Sľub na tlačidle je zmena a zrušenie, nie novinky

Poradie viet nie je kozmetika. Hlavný text znie **„ozveme sa, ak sa termín alebo
miesto zmení, prípadne ak sa podujatie neuskutoční"**; pripomienka pred začiatkom
je až druhá veta. Dôvod:

- „Dáme ti vedieť, keď sa niečo zmení" ľudia prijmú bez váhania — je to služba.
  „Odoberať novinky" väčšina minie.
- Pre organizátora je to **jediná cesta, ako sa presun do inej sály dostane
  k ľuďom, ktorí sa nikde neregistrovali.** Pri bezplatnom podujatí to je každý,
  kto príde.

Preto [`EventChanged`](../app/Notifications/EventChanged.php) nie je doplnok
k pripomienke, ale hlavná funkcia.

## Tabuľka je polymorfná od začiatku

`subscriptions` má `subscribable_type` + `subscribable_id`
([`Subscription::TARGETS`](../app/Models/Subscription.php)), hoci UI dnes ponúka
len podujatie. „Daj mi vedieť o novom v Martine" je tá istá vec s iným cieľom
a doťahovať to neskôr by znamenalo migrovať už naplnenú tabuľku.

`subscribable_type` je plný názov triedy (default morph), rovnako ako
`messages.messageable_type` a `question_boards.boardable_type` — morph mapa je
zdieľaná s `files.fileable_type` a neprepisuje sa.

## Odhlásenie zahodí adresu, riadok nechá

`unsubscribe()` nastaví `unsubscribed_at` a **vynuluje `email`**. Dve veci naraz:

- odkaz z pätičky funguje aj na druhý klik (klient si ho prednačíta, človek ho
  prepošle), takže odhlásenie je idempotentné a nekončí chybou,
- nedržíme adresu niekoho, kto o nás už nestojí.

Unikátny index `(subscribable_type, subscribable_id, email)` tým nie je dotknutý —
MySQL berie NULL ako vždy odlišný, takže sa dá prihlásiť znova. Vznikne pri tom
**nový riadok s novým tokenom**; ten starý sa neoživuje, lebo už odišiel do sveta
v pätičke e-mailu.

## Opt-in

Na konkrétne podujatie je **single opt-in**: človek si ho práve vypýtal a každý
e-mail nesie odhlasovací odkaz. Pri odbere kanála (zatiaľ nie je v UI) bude
potrebné potvrdenie — je to opakovaná komunikácia s iným právnym režimom.
Stĺpec `confirmed_at` je v schéme pripravený.

## Ochrana anonymného zápisu

Rovnaké vrstvy ako pri otázkach z publika (viď [questions-qa.md](questions-qa.md)):

1. [`SubmissionTicket`](../app/Support/SubmissionTicket.php) — podpísaná známka,
   že sa formulár naozaj otvoril. Vydáva ju `GET /api/events/{event}/subscription`,
   teda až kliknutie na tlačidlo. Bot, ktorý našiel adresu POSTu, ju nemá odkiaľ
   vziať.
2. honeypot `website` — pole mimo obrazovky, `tabindex="-1"`, `aria-hidden`.
3. limiter `public-write` na IP.
4. `firstOrNew` nad unikátnym indexom — dvojklik nezaloží druhý odber ani
   nepošle druhý e-mail.

Obe pasce vracajú **tú istú hlášku**, aby chyba botovi nepovedala, ktorá ho
chytila.

## Kedy chodí pripomienka

[`SendEventReminders`](../app/Console/Commands/SendEventReminders.php) obsluhuje
dve publiká a **každé má vlastné pravidlá**, preto sú to dva prechody:

| | účastník s lístkom | odberateľ |
|---|---|---|
| podmienka | organizátor nastavil `reminder_hours_before` | vždy |
| okno | `reminder_hours_before` | to isté, inak **24 h** |
| poistka | `events.reminder_sent_at` (na podujatí) | `subscriptions.notified_at` (na riadku) |

Predvolených 24 hodín je kľúčových: importované podujatia `reminder_hours_before`
nemajú vyplnené takmer nikdy, takže bez vlastného okna by odberateľovi nikdy nič
neprišlo.

Poistka je na riadku, nie na podujatí, lebo odber môže vzniknúť aj potom, čo
podujatie svoje okno prekročilo — a taký človek pripomienku dostať nemá.

## Čo sa považuje za zmenu

[`EventObserver::updated()`](../app/Observers/EventObserver.php) sleduje len to,
čo mení plán návštevníka: **termín**, **miesto** a **to, či sa podujatie koná**.
Oprava preklepu v popise e-mail nevyvolá — organizátori podujatia upravujú často
a pár zbytočných správ stačí na to, aby sa odhlásili všetci.

Zrušenie nemá vlastný stav: je to prechod z `published` kamkoľvek inam. Pozor na
`getRawOriginal('status')` — `getOriginal()` na stĺpec aplikuje cast a vrátil by
`ModelStatus`, takže porovnanie s reťazcom by ticho zlyhalo a zrušenie by sa
nikdy neohlásilo.

Podujatie po termíne sa preskakuje — zmena jeho údajov je upratovanie v archíve.

## Pripomienka, ktorú nemusíme doručiť my

Do generovaného `.ics` pribudol `VALARM`
([`IcsGenerator`](../app/Services/Calendar/IcsGenerator.php)). Bez neho bolo
„Pridať do kalendára" iba zápisom termínu — človek ho v kalendári mal, ale nikto
mu nič nepovedal.

- predvolene **2 h** pred začiatkom,
- pri celodennom podujatí **6 h** (DTSTART je o polnoci, dve hodiny by zazvonili
  o 22:00 predošlého dňa; šesť z toho spraví 18:00),
- organizátorov `reminder_hours_before` má prednosť — je to ten istý úmysel ako
  pri e-mailovej pripomienke a dve nezávislé čísla by sa časom rozišli.

`TRIGGER;RELATED=START` sa píše naplno, hoci je to default: časť klientov pri
jeho absencii vyhodnotí trigger voči `DTEND` a pri viacdňovom podujatí by
pripomienka prišla až po jeho konci.

Toto je jediná pripomienka, ktorá nepotrebuje od návštevníka **žiadny údaj**
a funguje aj offline.

## Overenie

```bash
cd api && php artisan test tests/Feature/Subscriptions tests/Unit/Events/IcsAlarmTest.php
```
