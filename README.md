# Event

Portál na zverejňovanie podujatí s registráciou účastníkov, vstupenkami a QR
check-inom. Podujatia sa okrem ručného zadávania aj automaticky importujú
z externých zdrojov a spracúvajú cez OpenAI.

Repozitár obsahuje dve samostatné aplikácie:

| Priečinok | Čo to je | Stack |
|---|---|---|
| [`api/`](api) | REST API a administrácia | Laravel 12, PHP 8.3, MySQL |
| [`ui/`](ui) | Single-page aplikácia | Vue 3, TypeScript, Vite, Tailwind 4 |

Sú oddelené: `ui` komunikuje s `api` cez `/api`, autentizácia beží na Laravel
Sanctum (cookie session pre SPA + Bearer token).

## Rýchly štart

Predpoklady: PHP 8.3+, Composer, Node 22+, MySQL 8.

```bash
cd api && composer install && cp .env.example .env && php artisan key:generate && php artisan migrate --seed
```

```bash
cd ui && npm install && npm run dev
```

`api/.env.example` je komentovaný — sú v ňom vysvetlené aj neštandardné kľúče
(`CRON_SECRET`, `IMPORT_SOURCE_URLS`, `PDF_CONVERTER_URL`, prepnutie na S3).

## Vývoj

| Príkaz | Kde | Čo robí |
|---|---|---|
| `php artisan test tests/Feature/Auth` | `api/` | testy — **púšťaj cielene**, celá suite trvá ~10 min |
| `vendor/bin/pint` | `api/` | formátovanie PHP |
| `npm run dev` | `ui/` | dev server na porte 5173 |
| `npm run lint` | `ui/` | ESLint |
| `npm run typecheck` | `ui/` | kontrola typov bez buildu |
| `npm run build` | `ui/` | typová kontrola + produkčný build do `ui/dist` |

Testy bežia proti MySQL databáze `event-api-test` (pozri `api/phpunit.xml`),
nie proti sqlite — preto sú pomalé. Pri lokálnom vývoji spúšťaj konkrétne cesty.

CI ([.github/workflows/ci.yml](.github/workflows/ci.yml)) púšťa testy, Pint na
zmenených súboroch, ESLint a build UI.

## Nasadenie

Produkcia sa nasadzuje `git pull`-om, preto je **`ui/dist` verzovaný v gite**.
Po zmene frontendu treba spustiť `npm run build` a výsledok commitnúť, inak
sa nasadí starý build.

Hosting nemá shell ani systémový cron — všetko sa spúšťa cez URL:

- **Scheduler:** externá služba (napr. cron-job.org) musí každú minútu volať
  `GET /cron/schedule-run?token=<CRON_SECRET>`.
- **Čistenie cache po deployi:** `GET /api/artisan/run?token=<CRON_SECRET>`.

Oba endpointy sú chránené tokenom z `CRON_SECRET`. Bez nastaveného cronu
nefungujú importy, archivácia podujatí, expirácia vstupeniek ani fronta.

Routovanie SPA rieši `.htaccess` v docroote subdomény, ktorý **nie je v gite** —
verzovaná predloha je [`ui/public/.htaccess`](ui/public/.htaccess) a po jej zmene
sa musí preniesť ručne. Sú v nej aj pravidlá pre `sitemap.xml` a pre crawlerov;
bez nich zdieľané odkazy nemajú náhľad a portál sa neindexuje. Postup a overenie:
[`deploy/htaccess.md`](deploy/htaccess.md). `FRONTEND_URL` v `api/.env` musí byť
verejná adresa SPA hostu — skladajú sa z nej kanonické adresy a sitemap.

Podrobnosti k scheduleru sú v [api/README.md](api/README.md), tematické
poznámky (import, varianty súborov, waitlist) v [api/docs/](api/docs).

## Prevádzka a monitoring

Tieto tri veci sú čisto o `.env` na produkcii — v kóde je všetko pripravené
a bez vyplnenia zostáva ticho vypnuté.

| Kľúč | Prečo |
|---|---|
| `SENTRY_LARAVEL_DSN` | Bez neho sa o produkčnej chybe dozvieme až od používateľa. Prázdna hodnota = SDK je úplne ticho, takže lokálne netreba nič riešiť. |
| `CRON_HEARTBEAT_URL` | Po každom úspešnom `schedule:run` sa pingne watchdog (healthchecks.io, Better Stack…). Watchdog alarmuje, keď ping **neprišiel** — čiže keď webcron vypadol. Bez neho je výpadok scheduleru neviditeľný. |
| `QUEUE_CONNECTION=database` | Pri `sync` bežia joby (varianty obrázkov, e-maily) priamo v HTTP requeste a predlžujú odpoveď. Worker spúšťa scheduler (`queue:work --stop-when-empty`), takže netreba daemon — ale po prepnutí over, že sa tabuľka `jobs` nehromadí. |

Chyby zo scheduleru idú do Sentry rovnakou cestou ako chyby z HTTP —
`schedule:run` beží vo webcron requeste a jeho výnimky spracuje ten istý handler.
