#!/bin/bash
set -e
cd /var/www/armooutdoor.fr
git pull
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
sudo systemctl restart php8.5-fpm
echo "Deploy complete."
