# Event API

REST API portálu Event — podujatia, miesta, kanály, vstupenky s QR check-inom,
import z externých zdrojov a spracovanie cez OpenAI. Frontend je samostatná
SPA v [`../ui`](../ui); prehľad celého projektu je v [koreňovom README](../README.md).

**Stack:** Laravel 12, PHP 8.3, MySQL 8, Sanctum (auth), spatie/laravel-permission
(role a oprávnenia).

## Inštalácia

```bash
composer install && cp .env.example .env && php artisan key:generate && php artisan migrate --seed
```

`.env.example` je komentovaný vrátane neštandardných kľúčov (`CRON_SECRET`,
`IMPORT_SOURCE_URLS`, `PDF_CONVERTER_URL`, prepnutie úložiska na S3).

## Testy

```bash
php artisan test tests/Feature/Auth
```

Testy bežia proti MySQL databáze `event-test` (pozri [phpunit.xml](phpunit.xml)),
nie proti sqlite. **Celá suite trvá ~10 minút**, preto pri vývoji púšťaj konkrétne
cesty. Rate limity sú v testoch globálne vypnuté v [tests/TestCase.php](tests/TestCase.php);
test, ktorý ich overuje, si ich zapne cez `$this->withMiddleware(ThrottleRequests::class)`.

Časť testov v `tests/Feature/Events/` je náhodne flaky, a to z dvoch dôvodov:

- `EventFactory` generuje dátumy a keď hodnota padne do hodiny, ktorá pri prechode
  na letný čas neexistuje (posledná nedeľa v marci), MySQL insert odmietne.
- `EventFactory` losuje `status` zo **všetkých** stavov, takže `futureEvent` môže byť
  aj archivovaný či blokovaný. Testy, ktoré nad ním niečo publikujú, mažú alebo
  upravujú, si stav musia pripnúť samy (`$this->futureEvent->update(['status' => …])`).
  `DashboardEventDestroyTest` a `EventTagAssignmentTest` to zatiaľ nerobia.

Pri takom páde test zopakuj — alebo mu doplň pripnutie stavu.

## Rate limiting

Limity sú definované v [AppServiceProvider](app/Providers/AppServiceProvider.php)
a priradené v [routes/api.php](routes/api.php) cez `throttle:<meno>`:

| Limiter | Limit | Kde |
|---|---|---|
| `api` | 300/min | globálne (`throttleApi()` v bootstrap/app.php) |
| `auth` | 5/min na IP+e-mail | prihlásenie |
| `register` | 3/min, 10/hod | registrácia a overovacie e-maily |
| `public-write` | 10/min | rezervácia vstupeniek, RSVP |
| `messages` | 10/hod | odosielanie správ |
| `ai` | 10/min, 100/deň | endpointy volajúce OpenAI (platené) |
| `ops` | 6/min | údržbové endpointy |

## Scheduler a cron

Úlohy sú v [routes/console.php](routes/console.php) — tam je aj vysvetlenie,
prečo bežia cez `Schedule::call` a prečo má každý importný zdroj vlastný čas
(od 16:00 `Europe/Bratislava` po 20 minútach). Aktuálny zoznam:

```bash
php artisan schedule:list
```

Hosting nemá shell ani systémový cron. `schedule:run` spúšťa webcron, ktorý
každú minútu volá `GET /cron/schedule-run?token=<CRON_SECRET>` (pozri
[koreňové README](../README.md#nasadenie)). Lokálne stačí
`php artisan schedule:run` alebo `schedule:work`.

### Fronta bez shellu

`QUEUE_CONNECTION` je predvolene `sync`, čiže generovanie variantov obrázkov aj
odosielanie e-mailov beží priamo v HTTP requeste a spomaľuje odpoveď. Keďže
klasický `queue:work` daemon bežať nemôže, scheduler každú minútu spustí krátky
`queue:work --stop-when-empty`. Prepnutie je len v `.env`:

```
QUEUE_CONNECTION=database
```

## Interná dokumentácia

Tematické poznámky sú v [docs/](docs) — napr. import
([pipeline](docs/import-extraction-pipeline.md),
[časové pásma zdrojov](docs/import-source-timezones.md),
[Výveska RSS fallback](docs/vyveska-rss-fallback.md)),
[filtre index endpointov](docs/index-filters.md),
[detekcia miesta](docs/venue-detect.md),
[varianty súborov](docs/file-variant-pruning-behavior.md) a
[napojenie na Account](docs/account-integration.md).
