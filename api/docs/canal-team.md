# Tím kanála a per-kanálové role

## Prečo

Do augusta 2026 sa do kanála nedal pridať druhý človek. `canal_user` vznikal len
pri založení kanála a pri auto-provisioningu osobného kanála, a práva sa
priraďovali **globálne na používateľa** (`syncRoles`). Nedalo sa byť správcom
jedného kanála a len brigádnikom na vstupe v druhom — divadlo s dramaturgom,
marketingárkou a brigádnikom muselo zdieľať jedno heslo.

## Ako to funguje teraz

Zdrojom pravdy je **rola v pivote** `canal_user.role`
([`CanalRole`](../app/Enums/CanalRole.php)):

| Rola | Čo smie |
|---|---|
| `owner` | Všetko vrátane správy tímu, archivácie a mazania kanála |
| `editor` | Podujatia, miesta, lístky, súbory. Nespravuje tím, nemaže kanál |
| `checkin` | Vidí podujatia a lístky, robí len check-in pri vchode |

Zoznam právomocí drží `CanalRole::abilities()`; policies sa pýtajú cez
`User::canInCanal($canalId, 'event.update')`. Rola mimo svojho kanála neznamená
nič — to je celý zmysel zmeny.

`is_owner` v pivote ostal a drží sa v súlade s rolou (`owner` ⇔ `is_owner = 1`),
aby staršie dotazy cez `ownedCanals()` / `owners()` fungovali bez prepisovania.

### Dve vrstvy práv

Dashboard routy sú stále chránené spatie middlewarom (`permission:event.create`).
Ten je **hrubé sito** — pýta sa len „smie to niekde". Globálnu rolu
(`canal-owner` / `canal-editor` / `canal-checkin`) preto odvodzuje
[`CanalMembership::syncGlobalRoles()`](../app/Services/Canals/CanalMembership.php)
ako zjednotenie rolí zo všetkých členstiev. O konkrétnom kanáli rozhoduje až
policy. Ručný `syncRoles` v role controlleroch odvodené role zámerne zachováva,
inak by člen prišiel o prístup do dashboardu.

Dôsledok, na ktorý treba myslieť pri pridávaní endpointov: **middleware nestačí**.
Každý zápis musí prejsť policy nad konkrétnym modelom. Preto sa doplnili aj dve
miesta, kde sa kanál nedal odvodiť z modelu:

- `EloquentEventRepository::createForUser()` — podujatie vzniká v *aktívnom*
  kanáli používateľa, ktorý môže byť iný než ten, kde má právo zakladať.
- `EloquentEventRepository::update()` — presun podujatia do iného kanála je
  fakticky založenie v cieli, ktorý policy nad pôvodným podujatím nepozná.

## Pozvánky

Tabuľka `canal_invitations`, služba
[`CanalInviter`](../app/Services/Canals/CanalInviter.php).

1. Vlastník zadá e-mail a rolu → vznikne pozvánka s tokenom (platnosť 14 dní)
   a odíde [`CanalInvitationSent`](../app/Notifications/CanalInvitationSent.php).
2. Odkaz vedie na `/pozvanka/{token}` vo frontende. Detail je verejný —
   autorizuje token, pozvaný musí vidieť, kam ho volajú, ešte pred prihlásením.
3. Prijatie vyžaduje prihlásený účet **s rovnakou adresou**. Preposlaný odkaz
   tak cudziemu účtu prístup nedá.

Účet sa pozvanému dopredu nezakladá (na rozdiel od `GuestAccountProvisioner`
pri vstupenkách) — kým pozvánku neprijme, nemá v systéme čo robiť.

Nová pozvánka na tú istú adresu zruší predchádzajúcu nevybavenú. Bez toho by
o výslednej role rozhodovalo, ktorý z odkazov obeť náhodou použije.

Prijaté pozvánky sa nemažú — sú dokladom, kto koho a kedy do kanála pustil.

## Zábradlia

- **Posledný vlastník** sa nedá odobrať ani degradovať (`guardLastOwner`) —
  kanál by ostal bez nikoho, kto ho vie spravovať alebo niekoho pozvať.
- **Vlastnú rolu** si člen meniť nemôže; odchod z tímu je akcia iného vlastníka.
- Nový člen dostane pivot status `draft`, teda **neaktívny kontext**. Aktívny
  kanál si prepína používateľ sám — pozvánka mu nemá prepnúť rozpracovanú prácu.
- Odobratému členovi sa `users.canal_id` prepne na iný jeho kanál.
- `UserPolicy::update` už nestačí „zdieľame kanál": úprava mení e-mail, takže by
  cez ňu brigádnik prevzal účet vlastníka. Vyžaduje sa právo na správu tímu.

## Číselník

Role a oprávnenia zanáša migrácia `2026_07_29_130002_seed_canal_team_roles`
(volá `RolesAndPermissionsSeeder`), nie `db:seed` — ten sa na produkcii nepúšťa.
Na rozdiel od `seed_reference_data` sa **nepreskakuje v testoch**: bez rolí by
pozvaný člen neprešiel cez `permission:` middleware a testy by overovali iný
stav než produkcia.

## Overenie

```bash
cd api && php artisan test tests/Feature/Canal/CanalTeamTest.php
```
