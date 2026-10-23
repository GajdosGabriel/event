# Filtre index endpointov

Index endpointy (public, dashboard, admin) zdieľajú jednu sadu query
parametrov:

```text
GET /api/{scope}/{resource}?status=&published=&unpublished=&blocked=&deleted=&search=&per_page=
```

Príklady:

```text
GET /api/admin/events?status=published&search=bratislava&per_page=20
GET /api/dashboard/canals?blocked=false&search=kultura
GET /api/dashboard/venues?deleted=true
```

| Parameter | Typ | Popis |
|---|---|---|
| `status` | `string` | Jeden z `ModelStatus`: `draft`, `pending_review`, `rejected`, `scheduled`, `published`, `archived`, `blocked` |
| `published` | `bool` | Len publikované záznamy |
| `unpublished` | `bool` | Len nepublikované záznamy |
| `blocked` | `bool` | Blokované alebo neblokované záznamy |
| `deleted` | `bool` | Pri modeloch so soft delete zmazané/nezmazané záznamy |
| `search` | `string`, max 250 | Textové vyhľadávanie v hlavných poliach modelu |
| `per_page` | `int`, 1–100 | Počet položiek na stránku, predvolene 15 |

## `search`

- Jeden query filter, nie dve oddelené volania.
- Hľadá v hlavných poliach (`name`, `title`, `email`) aj v sekundárnych
  (typicky `body`); zhody v názve sa radia pred zhody iba v `body`.
- Stĺpec, ktorý model nemá, sa preskočí.

## Kde to je

- Validácia a zostavenie filtrov:
  [IndexFilterRequest](../app/Http/Requests/IndexFilterRequest.php) (`getFilters()`).
- Scopy na modeloch: trait [HasCommonFilters](../app/Models/Traits/HasCommonFilters.php)
  (`byStatus`, `bySearch`, `byPublished`, `byBlocked`, `includeDeleted`,
  `applyCommonFilters`).
- Repozitáre: `indexWithFilters()` / `dashboardIndexWithFilters()` v
  [AbstractRepository](../app/Repositories/AbstractRepository.php).
- Testy: `tests/Unit/Filters/CommonFilterTest.php`,
  `tests/Unit/Requests/IndexFilterRequestTest.php`.

Nový spoločný filter = scope v `HasCommonFilters` + pravidlo a kľúč v
`IndexFilterRequest`; kontrolery sa meniť nemusia.
