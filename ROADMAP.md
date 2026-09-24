# Ďalší rozvoj aplikácie Event, zoradený podľa prínosu pre používateľa

## Kontext
Používateľ sa pýtal, čo ďalej vylepšiť, aby bola aplikácia jednoduchšia pre používateľov. Prešiel som router, stránky, komponenty a posledné commity. Podľa toho vychádza 5 oblastí, kde sa to oplatí najviac.

## Rozhodnutie používateľa
Implementovať v tomto poradí: **B → A → E → D**.
- B ide prvé: je najmenšie a výber so serverovým vyhľadávaním sa znovu použije pri zlučovaní v A.
- C (neuložené zmeny, potvrdzovacie okná) sa zatiaľ odkladá.
- Pred implementáciou A a D si najprv prečítam dotknuté súbory, t. j. kód merge príkazu, `DashboardPage.vue` a `usePublishReadiness.ts`, a spresním endpointy.
- Po každej oblasti bude samostatný commit (až keď o to používateľ požiada).

## A. Predchádzať duplicitám namiesto ich neskorého upratovania (najväčší prínos)
Doteraz sa duplicity upratujú až po fakte:
- duplicitné kanály zlúčila jednorazová migrácia (`api/database/migrations/2026_09_22_230000_merge_duplicate_organization_canals.php`),
- duplicitné akcie zlučuje príkaz `app:events-merge-duplicates`,
- miesta nejaký čas zlučovať nešlo vôbec (commit d5716a4).

Návrh:
- **Varovanie „Podobné už existuje“** vo formulároch `CanalEditPage.vue`, `VenueEditPage.vue` a `OrganizationEditPage.vue`. Po zadaní názvu (a mesta) sa zavolá nový endpoint `GET /api/{canals|venues}/similar?name=` a pod poľom sa ukáže napr. „Toto už existuje: X (Nitra) – použiť?“. Na podobnosť sa znovu použije logika z merge príkazu alebo migrácie.
- **Admin tlačidlo „Zlúčiť do…“** pre kanály a miesta v `ResourceActionsMenu.vue`, takže už netreba písať migrácie.

## B. Výbery (dropdowny) s vyhľadávaním namiesto limitu 100
Súbor `ui/src/composables/useFormOptions.ts:30,40` načíta len 100 kanálov a 100 miest. Pri viac položkách ich používateľ vo výbere jednoducho nenájde.
- Nahradiť to komponentom `ui/src/components/SearchableSelect.vue`, ktorý už existuje, a doplniť serverové vyhľadávanie (`?search=&per_page=20`).

## C. Pocit „nič sa mi nestratí“
- **Upozornenie na neuložené zmeny:** nový composable `useUnsavedGuard` (`onBeforeRouteLeave` + `beforeunload`) vo formulároch EventEdit, CanalEdit, VenueEdit a OrganizationEdit.
- **Vlastné potvrdzovacie okno namiesto `confirm()`:** týka sa 25 volaní v 17 súboroch. Vznikne `ConfirmDialog` a composable `useConfirm()`. Pri mazaní pribudne toast s tlačidlom **„Späť“**, keďže záznamy sa mažú len soft-delete a dajú sa obnoviť.
- **Načítavanie editačných stránok:** na stránkach Canal, Venue, Organization a Poster sa počas načítania zobrazí skeleton namiesto prázdneho formulára.

## D. Nástenka organizátora, ktorá poradí, čo robiť ďalej
`DashboardPage.vue` dnes ukazuje len štatistiky. Pribudnú bloky:
- „Najbližšie akcie“ (7 dní) s počtom registrácií,
- „Rozpracované koncepty“ s ukazovateľom pripravenosti z `usePublishReadiness.ts`,
- „Neprečítané správy“,
- krátky onboarding checklist pre nových používateľov: vytvor kanál → pridaj miesto → prvá akcia.

## E. Zoznamy, v ktorých sa ľahšie orientuje
V súbore `ui/src/pages/ResourceIndexPage.vue`:
- pri zmene filtra ostanú staré dáta viditeľné (len stlmené), takže zoznam neblikne,
- prázdny stav rozlíši „nič nevyhovuje filtrom → [Zrušiť filtre]“ od „zatiaľ nič → [Vytvoriť prvú]“.

Stránkovanie bude všade cez `AppPaginator`. Dnes je ručne napísané v `DashboardMessagesPage.vue`, `EventAttendeesPage.vue` a `AdminSystemLogsPage.vue`.

## Neskôr (menšie veci)
- Centrum notifikácií priamo v aplikácii (dnes je 26 notifikácií len e-mailom).
- Otestovať check-in skener a zoznam účastníkov na mobile.
- Odstrániť nepoužívaný `TagPicker.vue` a zistiť, či treba dve samostatné obrazovky pre obce.
- Pridať CHANGELOG alebo roadmapu.

## Overenie (pre každý bod, ktorý sa bude implementovať)
- `npm run test` (Vitest) na nové composables a komponenty, `php artisan test --filter` na nové endpointy.
- Preview dev servera: preklikať príslušný tok v prehliadači, overiť mobilnú šírku a skontrolovať konzolu.
- Nový text doplniť do všetkých 4 jazykov (sk, cs, de, en).
