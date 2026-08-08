# User → Canal → Organization → Account

Kto kam patrí a kde sa rozhoduje o platených funkciách.

## Reťazec

```
User ──canal_user(role)── Canal ──organization_id──▶ Organization ──account_uuid──▶ Account
```

| Vrstva | Čo je | Kde býva |
|---|---|---|
| **User** | prihlásenie, e-mail, blokovanie | `users` |
| **Canal** | tenant — vlastní podujatia, miesta, lístky a tím | `canals`, `canal_user` |
| **Organization** | fakturačná identita kanála | `organizations` |
| **Account** | firemné údaje spoločné pre všetky projekty | externá služba |

## Dve pravidlá, z ktorých plynie zvyšok

**1. Členstvo drží kanál, nie organizácia.**
Kto čo smie, hovorí `canal_user.role` (`CanalRole`) a kontroluje sa cez
`User::canInCanal()`. Organizácia vlastný tím **nemá** — druhý paralelný členský
systém by znamenal dva zdroje pravdy, dva pozvánkové flowy a dve miesta na
kontrolu práv.

> Historická poznámka: `Organization::users()` kedysi ukazovala na tabuľku
> `organization_user`, ktorú nikdy žiadna migrácia nevytvorila. Volanie tej
> relácie padalo. Nahradili ju `Organization::members()` a `owners()`, ktoré
> čítajú ľudí cez kanály firmy.

**2. Organizácia je nepovinná.**
`canals.organization_id` je nullable a `null` je bežný, nie chybový stav —
osobný kanál z registrácie (`PersonalCanalProvisioner`) žiadnu firmu nemá.
Znamená to neplatený režim.

## Ako sa to má k zadaniu

| Požiadavka | Ako je vyriešená |
|---|---|
| User nemusí patriť pod organizáciu | osobný kanál s `organization_id = NULL` |
| Platené služby vyžadujú organizáciu | `User::hasPaidAccessTo()` — reťazec musí byť celý |
| Jedna organizácia, viac userov | vzniká samo cez `canal_user` |
| Jedna organizácia, viac kanálov | divízie a značky pod jednou faktúrou |
| User vo viacerých organizáciách | členstvo vo viacerých kanáloch |

## Kde sa kontroluje nárok na platené funkcie

Jediné správne miesto:

```php
$user->hasPaidAccessTo($canalId);   // členstvo + fakturovateľná firma
$user->paidCanalIds();              // to isté pre výpisy, bez N+1
```

Pod tým:

```php
Canal::hasPaidAccess()        // má kanál fakturovateľnú firmu?
Organization::canBill()       // account_uuid + published + nezmazaná
```

Reťazec sa pretrhne kdekoľvek — kanál bez firmy, firma bez Accountu,
archivovaná alebo zmazaná firma — a kanál spadne na neplatený režim.
**Nikdy nekontroluj len `account_uuid`**: archivovaná firma by tíško
odomykala platené funkcie bez protistrany na faktúre.

## Prístup k organizáciám v dashboarde

Globálne spatie právo `organization.view` **nestačí** — seeder ho dáva aj
rolám `canal-owner` a `canal-editor`. Rozhoduje až to, či firma visí na
niektorom z kanálov používateľa:

- `EloquentOrganizationRepository::dashboardIndexQuery()` scopuje výpis cez
  `User::organizationIds()`,
- `OrganizationPolicy` overuje dosah na konkrétnu firmu,
- `OrganizationPolicy::viewBilling` (a teda aj volanie Accountu v
  `show`) je navyše len pre vlastníkov — dramaturg vidí profil organizátora,
  firemné doklady nie.

`User::organizationCanalMap()` je memoizovaná na inštancii používateľa
z rovnakého dôvodu ako `canalRoleMap()`: policy sa pýta na každý riadok výpisu.

V `/admin` tieto kontroly nebijú — `Gate::before` tam super-adminovi povolí
všetko (v `/dashboard` je bypass zámerne vypnutý, viď `AuthServiceProvider`).

## Zakladanie firmy z dashboardu

Väzba na kanál vzniká **v tej istej transakcii** ako firma
(`DashboardOrganizationController::store`). Bez `canal_id` v requeste sa berie
osobný kanál používateľa. Nenaviazaná organizácia by bola pre svojho autora
okamžite neviditeľná — prístup ide cez kanály — a nevedel by ju ani zmazať.

## Kde to vidno v UI

Detail organizácie (`/admin/organizations/{id}/edit`, rovnako v dashboarde) má
sekciu **Kanály a ľudia**: kanály, ktoré pod firmou fakturujú, a v každom jeho
tím aj s rolami. Vo výpise je len počet kanálov — načítať tímy pre každý riadok
by bol dotaz na riadok.

Väzba sa mení dvoma koncovými bodmi; **členov nespravuje organizácia**, ale tím
kanála (`canals/{canal}/team`), lebo rola platí vždy len v konkrétnom kanáli:

| Metóda | Cesta | Čo robí |
|---|---|---|
| `POST` | `{scope}/organizations/{id}/canals` | priradí kanál pod firmu |
| `DELETE` | `{scope}/organizations/{id}/canals/{canal}` | odpojí kanál (nemaže ho) |

V dashboarde nemožno odpojiť **posledný** kanál — prístup k firme vedie cez
kanály, takže by si používateľ zamkol vlastné dvere (HTTP 422). V admine to
obmedzenie neplatí, super-admin firmy vidí aj bez väzby.

## Súvisiace

- [`account-integration.md`](account-integration.md) — čo vlastní Event a čo Account
- [`canal-team.md`](canal-team.md) — role a pozvánky v tíme kanála
