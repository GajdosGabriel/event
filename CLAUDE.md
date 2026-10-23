# Event — pokyny pre Claude

## Lokálne prihlásenie pri overovaní v prehliadači

- Na localhoste sa prihlasuj testovacím super-adminom z `api/.env`
  (`DEV_ADMIN_EMAIL`, `DEV_ADMIN_PASSWORD`), nie účtom používateľa.
  Účet zakladá/obnovuje `php artisan db:seed --class=LocalDevAdminSeeder`
  (len v `APP_ENV=local`). Heslo nevypisuj do chatu ani necommituj —
  `api/.env` je mimo gitu.

## Frontend (`ui/`)

- Produkčný build (`npm run build` v `ui/`) spúšťa git hook
  `.githooks/pre-commit` pri commite so zmenou v `ui/` a `ui/dist` pridá do
  toho istého commitu. Po úprave frontendu build nespúšťaj sám.
  Dôvod: produkcia sa nasadzuje `git pull`-om a `ui/dist` je verzovaný v gite —
  bez buildu sa nasadí starý frontend.
  Hook treba na každom počítači raz zapnúť: `git config core.hooksPath .githooks`.
