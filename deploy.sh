#!/bin/bash
set -e
BRANCH=main
cd /var/www/armooutdoor.fr

php artisan down --retry=60

# Past this point the shop is in maintenance mode, so any failure has to bring
# it back up on the previous release rather than leave customers on the down
# page. The health check at the end opts out of this on purpose.
restore_site() {
    trap - ERR
    php artisan up || true
}
trap restore_site ERR

git fetch origin "$BRANCH"
git reset --hard "origin/$BRANCH"

composer install --no-dev --optimize-autoloader
# ci, not install: the lockfile is committed, so this installs a resolved tree
# instead of re-resolving against the registry on every deploy. The timeout and
# retries turn a stalled registry into a loud failure instead of a silent hang.
# The build needs vite and tailwind, which are devDependencies — no --omit=dev.
npm ci --no-audit --no-fund --no-progress --fetch-timeout=60000 --fetch-retries=2
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
    trap - ERR
    echo "Health check failed after deploy — leaving the site in maintenance mode." >&2
    exit 1
fi

php artisan up
echo "Deploy complete."
