#!/usr/bin/env bash
# Nasadenie na produkciu. Spúšťa sa ručne na serveri (Websupport mení SSH port,
# automatické nasadenie z GitHub Actions preto nefunguje).
#
# ui/dist je commitnutý, takže sa tu nebuilduje — build treba spraviť pred pushom.
set -euo pipefail

cd "$(dirname "$0")/.."

# Údržbový režim počas pull/composer/migrácií: kým composer vymieňa vendor/,
# autoloader odkazuje na súbory, ktoré práve nie sú, a požiadavky padali na
# PHP Fatal error. public/index.php kontroluje maintenance.php ešte pred
# autoloaderom, takže klient dostane 503 s Retry-After. `up` beží aj pri
# chybe nasadenia; ak aplikácia nenabehne, ostane stránka v údržbe.
echo "==> údržbový režim"
(cd api && php artisan down --retry=15)
trap '(cd "$(git rev-parse --show-toplevel)/api" && php artisan up) || echo "!! php artisan up zlyhal — stránka ostáva v údržbe." >&2' EXIT

echo "==> git pull"
git pull --ff-only origin main
git log --oneline -1

cd api

echo "==> composer install"
composer install --no-dev --optimize-autoloader --no-interaction --no-progress

echo "==> migrácie"
php artisan migrate --force

echo "==> cache"
# Zámerne bez `cache:clear`: cache beží v DB (CACHE_STORE=database) a drží aj
# stav, ktorý nasadenie musí prežiť — zámky withoutOverlapping (bežiaci import
# či queue:work by sa spustil druhýkrát), ochranu proti duplicitám v CanalRelay
# (zopakovaný webhook by odpoveď preposlal znova) a cooldowny upozornení.
# config:cache a route:cache staré súbory aj tak prepíšu.
php artisan view:clear

php artisan config:cache
php artisan route:cache

# Trvalý queue worker na hostingu nebeží (fronta ide cez webcron s
# --stop-when-empty), takže queue:restart tu nemá koho reštartovať.

echo "==> Emailing: udalosti a štítky v Accounte"
# Po pridaní case do MailingTrigger (docs/canal-ownership.md). Pri vypnutom
# Emailingu nič nerobí; výpadok Accountu nasadenie nezastaví.
php artisan emailing:register-triggers || echo "!! emailing:register-triggers zlyhal — spusti ho neskôr ručne." >&2

echo "==> hotovo"
