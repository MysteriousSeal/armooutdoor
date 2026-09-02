#!/bin/bash
set -e
BRANCH=main
cd /var/www/armooutdoor.fr

php artisan down --retry=60

git fetch origin "$BRANCH"
git reset --hard "origin/$BRANCH"

composer install --no-dev --optimize-autoloader
npm install
npm run build

php artisan migrate --force

php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
# In place on the server only — the reset above restores readable sources
# on the next deploy, and versioned_asset() stamps by mtime.
php artisan css:minify

sudo systemctl reload php8.5-fpm

if ! curl -fsS -o /dev/null https://armooutdoor.fr/up; then
    echo "Health check failed after deploy — leaving the site in maintenance mode." >&2
    exit 1
fi

php artisan up
echo "Deploy complete."
