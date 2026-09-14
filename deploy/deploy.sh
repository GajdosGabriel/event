#!/usr/bin/env bash
# Nasadenie na produkciu. Spúšťa sa ručne na serveri (Websupport mení SSH port,
# automatické nasadenie z GitHub Actions preto nefunguje).
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
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

php artisan config:cache
php artisan route:cache

echo "==> reštart queue workera"
php artisan queue:restart

echo "==> hotovo"
