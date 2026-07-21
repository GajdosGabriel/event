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

Testy bežia proti MySQL databáze `event-api-test` (pozri [phpunit.xml](phpunit.xml)),
nie proti sqlite. **Celá suite trvá ~10 minút**, preto pri vývoji púšťaj konkrétne
cesty. Rate limity sú v testoch globálne vypnuté v [tests/TestCase.php](tests/TestCase.php);
test, ktorý ich overuje, si ich zapne cez `$this->withMiddleware(ThrottleRequests::class)`.

Časť testov v `tests/Feature/Events/` je náhodne flaky: `EventFactory` generuje
dátumy a keď hodnota padne do hodiny, ktorá pri prechode na letný čas neexistuje
(posledná nedeľa v marci), MySQL insert odmietne. Pri takom páde test zopakuj.

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

Laravel scheduler je zapojený v [routes/console.php](routes/console.php) a spúšťa:

- `app:ai-detector` každú minútu
- `app:events-archive-finished` každých 10 minút
- `app:tickets-expire-unconfirmed` každých 10 minút
- `app:registrations-expire-pending` každých 10 minút
- `app:import-event-sources` denne o 16:00 (`Europe/Bratislava`)
- `queue:work database --stop-when-empty` každú minútu

Poznámka k `app:import-event-sources`: pri importe sa časy zo zdrojov `ecav.sk`, `vyveska.sk` a `tkkbs.sk` interpretujú ako lokálny čas `Europe/Bratislava`, aby sa do aplikácie neukladali časovo posunuté eventy.

### Fronta bez shellu

`QUEUE_CONNECTION` je predvolene `sync`, čiže generovanie variantov obrázkov aj
odosielanie e-mailov beží priamo v HTTP requeste a spomaľuje odpoveď. Keďže
hosting nemá shell, klasický `queue:work` daemon tam bežať nemôže — scheduler
preto každú minútu spustí krátky beh, ktorý po vyprázdnení fronty skončí.

Prepnutie je vec `.env`, kód sa meniť nemusí:

```
QUEUE_CONNECTION=database
```

Scheduler sa nespúšťa sám. Na serveri musí bežať systémový cron, ktorý každú minútu zavolá Laravel scheduler.

### Linux cron

Do crontabu používateľa, pod ktorým beží aplikácia, pridaj:

```cron
* * * * * cd /var/www/event-api && php artisan schedule:run >> /dev/null 2>&1
```

Ak má server inú cestu k projektu alebo inú PHP binárku, uprav ju napríklad takto:

```cron
* * * * * cd /home/deploy/event-api && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

### Windows Task Scheduler

Ak aplikácia beží na Windows serveri, vytvor plánovanú úlohu spúšťanú každú minútu.

- Program/script: `php`
- Add arguments: `artisan schedule:run`
- Start in: `C:\www\event-api`

### Overenie

Na kontrolu registrovaných úloh použi:

```bash
php artisan schedule:list
```

Na manuálne overenie behu schedulera použi:

```bash
php artisan schedule:run
```

## Interná dokumentácia

### Výveska import

Import zo zdroja `vyveska.sk` používa ako primárny zdroj HTML detail stránky eventu. Ak sa z detailu nepodarí spoľahlivo získať `start_at`, `end_at` alebo `published_at_source`, importer použije RSS fallback z `https://www.vyveska.sk/rss.xml`, pričom RSS páruje podľa kanonického detail URL bez query parametrov.

### Timezone pri importe zdrojov

Časy z externých slovenských zdrojov `ecav.sk`, `vyveska.sk` a `tkkbs.sk` sa pri importe interpretujú ako lokálny čas `Europe/Bratislava`, aby nevznikal posun pri ukladaní event dátumov a publikovaných časov.

- Správanie generovania náhľadov a mazania redundantných originálov je popísané v [docs/file-variant-pruning-behavior.md](docs/file-variant-pruning-behavior.md).
- Timezone pravidlá importu externých zdrojov sú popísané v [docs/import-source-timezones.md](docs/import-source-timezones.md).
- Výveska RSS fallback pre dátumy importovaných eventov je popísaný v [docs/vyveska-rss-fallback.md](docs/vyveska-rss-fallback.md).

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

You may also try the [Laravel Bootcamp](https://bootcamp.laravel.com), where you will be guided through building a modern Laravel application from scratch.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
