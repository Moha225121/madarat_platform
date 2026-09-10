web: php artisan config:clear && php artisan route:clear && php artisan view:clear && php artisan migrate --force && heroku-php-apache2 public/
worker: php artisan queue:work --tries=3 --timeout=120 --backoff=10
