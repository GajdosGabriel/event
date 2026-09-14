#!/usr/bin/env bash
# Nasadenie na produkciu. Spúšťa ho GitHub Actions (job `deploy` v ci.yml)
# po každom pushi na main, keď prejdú testy. Dá sa spustiť aj ručne na serveri.
#
# UI sa na serveri nebuilduje — na to tu nie je dosť pamäte. Build robí
# GitHub Actions a pred spustením skriptu ho nahrá do ui/dist.new.
set -euo pipefail

cd "$(dirname "$0")/.."

echo "==> git pull"
git pull --ff-only origin main
git log --oneline -1

# Až po pulle: ui/dist bol kedysi verzovaný a pull, ktorý ho z gitu vyradil,
# by zmazal aj práve nahraté súbory s rovnakým názvom.
if [ -d ui/dist.new ]; then
    echo "==> nový build UI"
    rm -rf ui/dist.old
    if [ -d ui/dist ]; then
        mv ui/dist ui/dist.old
    fi
    mv ui/dist.new ui/dist
    rm -rf ui/dist.old
fi

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
