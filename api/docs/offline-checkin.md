# Offline check-in a PWA

## Prečo to vzniklo

Skener bol čisto online: každý sken jeden HTTP request. V sále, v pivnici klubu
alebo na lúke — teda tam, kde sa check-in naozaj robí — to znamená, že pri
výpadku signálu sa pri vchode stojí. Nie je to hraničný prípad, je to bežný večer.

Druhá polovica toho istého problému je na strane návštevníka: vstupenka s QR
kódom je stránka, a stránka sa bez signálu nenačíta.

## Fronta skenov

[`utils/checkinQueue.ts`](../../ui/src/utils/checkinQueue.ts) drží neodoslané
skeny v IndexedDB. Sken do nej ide v dvoch prípadoch:

- prehliadač hlási `navigator.onLine === false`,
- alebo požiadavka zlyhala **bez odpovede servera** (žiadny stavový kód).

To druhé je podstatné. `navigator.onLine` je len hrubá nápoveda — hlási aj sieť
bez internetu, teda presne to, čo býva v sále s wifi bez uplinku. Naopak
odmietnutie so stavovým kódom (403, 422) je skutočná odpoveď a do fronty
nepatrí: server povedal nie.

Kľúč záznamu je `${eventId}:${token}`, takže dvakrát priložený ten istý kód je
jeden záznam a nie dva.

IndexedDB, nie `localStorage`: fronta musí prežiť zavretie karty aj pád
prehliadača, a zápis do `localStorage` je synchrónny — pri sérii skenov by
blokoval vlákno, ktoré práve dekóduje obraz z kamery.

## Prehratie stojí na idempotencii

Záznam sa z fronty maže **až po odpovedi servera**. Keď spojenie vypadne uprostred
odosielania, sken sa pošle znova — radšej dvakrát než ani raz, lebo stratený sken
znamená človeka, ktorý pri vchode „nebol".

Unesie to
[`EloquentTicketRepository::checkIn()`](../app/Repositories/Eloquent/EloquentTicketRepository.php):
druhý sken tej istej vstupenky nič neprepíše a vráti `already_checked_in`.
Nebola to nová vlastnosť — fronta sa o ňu len oprela a test ju odteraz stráži.

## Čas skenu, nie čas signálu

`POST /dashboard/tickets/checkin` prijíma nepovinné `scanned_at`. Bez neho by
všetci, čo prišli počas hodinového výpadku, mali v zozname jeden a ten istý
okamih — ten, v ktorom sa vrátil signál.

Hodnotu posiela zariadenie, takže sa jej verí len v rozsahu, ktorý pustí
validácia: minulosť, najviac deň dozadu. Nové oprávnenie tým nevzniká — obsluha
môže označiť príchod komukoľvek aj ručne.

## Čo vidí obsluha

Pri vchode musí byť na prvý pohľad jasné, či sken doletel, alebo len čaká — inak
obsluha nevie, či môže pustiť ďalšieho. Preto pribudol pás so stavom spojenia
a počtom čakajúcich skenov a **vlastný stav výsledku** `queued` s modrou, nie
zelenou farbou: vstupenka ešte nie je overená, len uložená.

`queued` je jediný stav výsledku, ktorý nechodí zo servera — nastavuje si ho
skener sám.

## PWA

`manifest.webmanifest` + [`sw.js`](../../ui/public/sw.js) v `ui/public/`.
Ikony sa generujú z `favicon.svg`, aby bola značka na ploche telefónu tá istá
ako v záložke.

Stratégia service workera je zámerne opatrná, lebo produkcia sa nasadzuje
`git pull`-om a `ui/dist` je verzovaný: **zlá cache by znamenala používateľov
zaseknutých na starom builde**, čo je horšia porucha než chýbajúci offline režim.

| Čo | Ako | Prečo |
|---|---|---|
| HTML (navigácia) | najprv sieť, cache len ako záchrana | online teda vždy príde čerstvý `index.html` a s ním čerstvé hashe assetov |
| `/assets/*` | cache-first | Vite dáva do názvu hash obsahu, takže súbor pod danou adresou sa nikdy nezmení |
| `/api`, `/sanctum`, `/storage` | nikdy | odpovede sú per používateľ a per moment |

Registruje sa až po `load` (aby nesúťažil o sieť s prvým vykreslením) a len
v produkčnom builde — v dev režime by cachoval moduly, ktoré Vite práve
prepisuje, a HMR by prestalo dávať zmysel.

Pri zmene `sw.js` treba **zvýšiť `CACHE`** — starý obsah sa zmaže pri aktivácii.

### Nasadenie

`public/.htaccess` dostal `webmanifest` do zoznamu prípon, ktoré sa neprepisujú
na prerender — inak by crawler dostal namiesto manifestu HTML. Súbor je mimo
gitu, takže sa musí preniesť ručne ([deploy/htaccess.md](../../deploy/htaccess.md)).

## Viac zariadení pri dverách

Súbežné skenovanie fungovalo od začiatku: `checkIn()` beží pod `lockForUpdate`
a je idempotentný, takže dva telefóny nad tou istou vstupenkou si neublížia.
Chýbali dve veci okolo toho.

**Počty boli zastarané.** Obnovovali sa len po vlastnom skene, takže každé
zariadenie ukazovalo iné číslo a ani jedno nebolo pravdivé. Skener si ich teraz
ťahá každých 20 sekúnd — a len keď je karta viditeľná a je spojenie, aby telefón
v kešeni medzi príchodmi nestrieľal dotazy. Polling, nie websocket: portál žiadny
nemá a kvôli jednému číslu sa neoplatí.

**„Už použité" nepovedalo kým.** Pri jednom zariadení to stačí, pri dvoch je to
rozdiel medzi „pustil ho kolega pred minútou" a „niekto to skúša druhýkrát".
`AdmissionResource` preto vracia pri `checked_in_by` aj meno obsluhy.

Meno skladá `User::displayName()`, čo sú dva dotazy (osobný kanál +
`PendingProfile`). V zozname prihlásených by to bola N+1, preto má resource
cache podľa id v rámci požiadavky — obsluhy sú aj na veľkom podujatí traja ľudia,
takže má rádovo jednotky položiek.

## Čo tým nie je vyriešené

- **Offline sa dá overiť len to, čo server pozná.** Skener neukáže meno ani typ
  lístka — vstupenku overuje server, nie zariadenie. Fronta hovorí „uložené",
  nie „platné".
- **Zoznam prihlásených offline nefunguje.** Cachovať ho by znamenalo držať
  v telefóne osobné údaje účastníkov.
- **Počty sa počas výpadku nehýbu.** Čo naskenovali ostatní, sa dozvie
  zariadenie až po obnovení spojenia — jeho vlastná fronta sa do čísla nezaráta,
  lebo o nej server ešte nevie.

## Overenie

```bash
cd api && php artisan test tests/Feature/Events/CheckinOfflineQueueTest.php
```

Ručne: otvoriť skener, v DevTools prepnúť na Offline, naskenovať — výsledok musí
byť modrý „Uložené" a v páse pribudne počet. Po prepnutí späť na Online sa fronta
odošle sama a číslo príchodov narastie.
