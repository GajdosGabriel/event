#!/usr/bin/env bash
# Nasadenie na produkciu. Spúšťa ho GitHub Actions (job `deploy` v ci.yml)
# po každom pushi na main, keď prejdú testy. Dá sa spustiť aj ručne na serveri.
#
# ui/dist je commitnutý, takže sa tu nebuilduje — build treba spraviť pred pushom.
set -euo pipefail

cd "$(dirname "$0")/.."

echo "==> git pull"
git pull --ff-only origin main
git log --oneline -1

cd api

echo "==> composer install"
composer install --no-dev --optimize-autoloader --no-interaction --no-progress

echo "==> migrácie"
php artisan migrate --force

echo "==> cache"
php artisan config:cache
php artisan route:cache
php artisan view:clear
php artisan cache:clear

echo "==> reštart queue workera"
php artisan queue:restart

echo "==> hotovo"
