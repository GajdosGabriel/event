# Event — pokyny pre Claude

## Lokálne prihlásenie pri overovaní v prehliadači

- Na localhoste sa prihlasuj testovacím super-adminom z `api/.env`
  (`DEV_ADMIN_EMAIL`, `DEV_ADMIN_PASSWORD`), nie účtom používateľa.
  Účet zakladá/obnovuje `php artisan db:seed --class=LocalDevAdminSeeder`
  (len v `APP_ENV=local`). Heslo nevypisuj do chatu ani necommituj —
  `api/.env` je mimo gitu.

## Backend (`api/`)

- Testy bežia na MySQL `event-test`, celá suite ~10 min — púšťaj cielene.
  Ak DB chýba, povedz to, nemaskuj pád.
- Permission middleware a policy sú vrstvené zámerne — neodstraňuj jedno
  preto, že existuje druhé.
- Repozitáre (`dashboardShow`, `adminShow`, `dashboardIndexWithFilters`…)
  držia scoping dashboard/admin; neobchádzaj ho surovým Eloquentom v kontroleri.
- Pri zmene verejného API kontraktu (request/response atribúty) to výslovne
  uveď — konzumuje ho `ui/` aj embed widget.
- Formátovanie PHP opravuje Pint v git hooku `.githooks/pre-commit` pri commite,
  ručne ho spúšťaj len pri súbore s nestagovanými zmenami (hook ho preskočí).
  CI inak spadne na Pinte a testy vôbec nespustí.
- Pri zmene v `resources/lang` spusti `php artisan test --filter=LocaleTest`:
  kontroluje úplnosť prekladov vo všetkých jazykoch a cielené testy ju nepokryjú.

## Frontend (`ui/`)

- Produkčný build (`npm run build` v `ui/`) spúšťa git hook
  `.githooks/pre-commit` pri commite so zmenou v `ui/` a `ui/dist` pridá do
  toho istého commitu. Po úprave frontendu build nespúšťaj sám.
  Dôvod: produkcia sa nasadzuje `git pull`-om a `ui/dist` je verzovaný v gite —
  bez buildu sa nasadí starý frontend.
  Hook treba na každom počítači raz zapnúť: `git config core.hooksPath .githooks`.
