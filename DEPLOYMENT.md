# Laundry POS deployment

## Requirements

- Ubuntu Server 24.04 or compatible
- NGINX
- PHP 8.3 or newer with `pdo_mysql`, `mbstring`, `xml`, `curl`, `zip`, and `bcmath`
- MySQL 8 or MariaDB 10.6+
- Node.js 20+ and npm

## Deploy

```bash
git clone <repository> /var/www/laundry
cd /var/www/laundry
composer install --no-dev --optimize-autoloader
npm ci
npm run build
cp .env.example .env
php artisan key:generate
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Set `APP_ENV=production`, `APP_DEBUG=false`, a public HTTPS `APP_URL`, and the MySQL credentials in `.env`. Set the application timezone to `Asia/Manila`.

The NGINX document root must be `/var/www/laundry/public`; never expose the project root. Configure PHP-FPM, HTTPS, automatic database backups, log rotation, and a scheduler entry:

```cron
* * * * * cd /var/www/laundry && php artisan schedule:run >> /dev/null 2>&1
```

For production, use a queue worker managed by systemd or Supervisor if queued mail, notifications, or background jobs are enabled.
