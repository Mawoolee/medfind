# MedFind — Production Deployment Guide

This guide covers deploying MedFind to a Linux production server with Nginx, PHP-FPM, PostgreSQL, Laravel Reverb (WebSocket), and Supervisor.

---

## Prerequisites

| Component      | Version   | Purpose                          |
|----------------|-----------|----------------------------------|
| PHP            | 8.2+      | Laravel runtime                  |
| PostgreSQL     | 16+       | Primary database                 |
| Nginx          | 1.24+     | Reverse proxy & static files     |
| Certbot        | latest    | Let's Encrypt SSL certificates   |
| Supervisor     | 4.x       | Process management (queue, Reverb)|
| Composer       | 2.x       | PHP dependency manager           |
| Node.js        | 20 LTS    | Vite asset compilation           |

Required PHP extensions:
```
php8.2-fpm php8.2-pgsql php8.2-mbstring php8.2-xml php8.2-curl
php8.2-zip php8.2-bcmath php8.2-intl php8.2-readline
```

---

## 1. Server Setup

```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install PHP 8.2 + extensions
sudo add-apt-repository ppa:ondrej/php -y
sudo apt install php8.2-fpm php8.2-pgsql php8.2-mbstring php8.2-xml \
    php8.2-curl php8.2-zip php8.2-bcmath php8.2-intl php8.2-readline -y

# Install Nginx
sudo apt install nginx -y

# Install PostgreSQL 16
sudo apt install postgresql-16 postgresql-client-16 -y

# Install Supervisor
sudo apt install supervisor -y

# Install Certbot
sudo apt install certbot python3-certbot-nginx -y

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Install Node.js 20 LTS (for Vite build)
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install nodejs -y
```

---

## 2. Application Deployment

```bash
# Create application directory
sudo mkdir -p /var/www/medfind
sudo chown www-data:www-data /var/www/medfind

# Clone or upload the project
cd /var/www/medfind
git clone <your-repo-url> .

# Install PHP dependencies (no dev packages in production)
composer install --no-dev --optimize-autoloader

# Install Node dependencies and build assets
npm ci
npm run build

# Set permissions
sudo chown -R www-data:www-data /var/www/medfind
sudo chmod -R 755 /var/www/medfind
sudo chmod -R 775 /var/www/medfind/storage /var/www/medfind/bootstrap/cache
```

---

## 3. Environment Configuration

Copy `.env.example` and configure for production:

```bash
cp .env.example .env
php artisan key:generate
```

### Production .env (key differences from local)

```env
APP_NAME=MedFind
APP_ENV=production
APP_DEBUG=false
APP_URL=https://<your-domain.com>

# Database — PostgreSQL
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=medfind
DB_USERNAME=<db-user>
DB_PASSWORD=<db-password>

# Cache & Session (use Redis in production if available)
CACHE_DRIVER=file
SESSION_DRIVER=file

# Queue — use database or Redis for async processing
QUEUE_CONNECTION=database

# Broadcasting — Laravel Reverb
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=<unique-app-id>
REVERB_APP_KEY=<generate-a-secure-key>
REVERB_APP_SECRET=<generate-a-secure-secret>
REVERB_HOST=<your-domain.com>
REVERB_PORT=443
REVERB_SCHEME=https

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT=443
VITE_REVERB_SCHEME=https
```

### Local vs Production .env Differences

| Setting              | Local                  | Production                   |
|----------------------|------------------------|------------------------------|
| `APP_ENV`            | `local`                | `production`                 |
| `APP_DEBUG`          | `true`                 | `false`                      |
| `APP_URL`            | `http://127.0.0.1:8000`| `https://<your-domain.com>`  |
| `QUEUE_CONNECTION`   | `sync`                 | `database`                   |
| `REVERB_HOST`        | `127.0.0.1`            | `<your-domain.com>`          |
| `REVERB_PORT`        | `8080`                 | `443`                        |
| `REVERB_SCHEME`      | `http`                 | `https`                      |

---

## 4. Database Setup

```bash
# Create PostgreSQL user and database
sudo -u postgres psql

CREATE USER medfind_user WITH PASSWORD '<db-password>';
CREATE DATABASE medfind OWNER medfind_user;
GRANT ALL PRIVILEGES ON DATABASE medfind TO medfind_user;
\q

# Run migrations
cd /var/www/medfind
php artisan migrate --force
```

---

## 5. Nginx Configuration

```bash
# Copy the provided nginx.conf
sudo cp /var/www/medfind/docs/deployment/nginx.conf /etc/nginx/sites-available/medfind

# Edit the file: replace <your-domain.com> with your actual domain
sudo nano /etc/nginx/sites-available/medfind

# Enable the site
sudo ln -s /etc/nginx/sites-available/medfind /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default

# Test and reload
sudo nginx -t
sudo systemctl reload nginx
```

The `nginx.conf` in this directory handles:
- HTTP → HTTPS redirect (port 80 → 443)
- PHP-FPM via Unix socket for Laravel
- WebSocket proxy at `/app` for Laravel Reverb
- Security headers (X-Frame-Options, X-Content-Type-Options, Referrer-Policy)
- Blocks access to hidden files (`.env`, `.git`, etc.)

---

## 6. SSL Certificate (Let's Encrypt)

```bash
# Obtain certificate (Nginx plugin handles config automatically)
sudo certbot --nginx -d <your-domain.com>

# Verify auto-renewal
sudo certbot renew --dry-run

# Certbot auto-renewal is installed via systemd timer
sudo systemctl status certbot.timer
```

If you already have the Nginx config with SSL paths in place, use certonly:
```bash
sudo certbot certonly --nginx -d <your-domain.com>
```

---

## 7. Supervisor (Queue Worker + Reverb)

```bash
# Copy supervisor config
sudo cp /var/www/medfind/docs/deployment/supervisor.conf \
    /etc/supervisor/conf.d/medfind.conf

# Reload and start
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start medfind-queue:*
sudo supervisorctl start medfind-reverb

# Check status
sudo supervisorctl status
```

Supervisor manages two processes:
- **medfind-queue** — Processes background jobs (notifications, emails, etc.)
- **medfind-reverb** — Runs the WebSocket server on `127.0.0.1:8080` (proxied via Nginx)

---

## 8. Laravel Optimization (Production)

```bash
cd /var/www/medfind

# Cache configuration, routes, and views
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Create storage symlink
php artisan storage:link

# Ensure queue tables exist (if using database driver)
php artisan queue:table
php artisan migrate --force
```

---

## 9. Deployment Checklist

- [ ] Server meets prerequisites (PHP 8.2+, PostgreSQL 16, Nginx, Node 20)
- [ ] Application code deployed to `/var/www/medfind`
- [ ] `.env` configured with production values
- [ ] `APP_DEBUG=false` in production
- [ ] Database created, user configured, migrations run
- [ ] `composer install --no-dev --optimize-autoloader` completed
- [ ] `npm ci && npm run build` completed
- [ ] File permissions set (www-data owns storage + bootstrap/cache)
- [ ] Nginx config in place with correct domain
- [ ] SSL certificate obtained and auto-renewal verified
- [ ] Supervisor processes running (queue + reverb)
- [ ] Laravel caches generated (config, route, view)
- [ ] Storage symlink created
- [ ] Application accessible via HTTPS
- [ ] WebSocket connection working (test real-time inventory updates)
- [ ] Queue processing verified (test notification delivery)

---

## 10. Local Development Setup (Current)

For local development on Windows, no Nginx or Supervisor is needed:

```bash
# Terminal 1 — Laravel dev server
php artisan serve
# Runs at http://127.0.0.1:8000

# Terminal 2 — Reverb WebSocket server
php artisan reverb:start
# Runs at ws://127.0.0.1:8080

# Terminal 3 — Vite dev server (hot reload)
npm run dev

# Terminal 4 — Queue worker (if using database queue locally)
php artisan queue:work
```

Local `.env` uses:
- `REVERB_HOST=127.0.0.1`, `REVERB_PORT=8080`, `REVERB_SCHEME=http`
- `QUEUE_CONNECTION=sync` (jobs run immediately, no worker needed)
- `APP_DEBUG=true` for error details

---

## Troubleshooting

### WebSocket connection fails
1. Check Reverb is running: `sudo supervisorctl status medfind-reverb`
2. Check Nginx proxy: `sudo nginx -t` and review `/var/log/nginx/error.log`
3. Verify `REVERB_PORT=443` and `REVERB_SCHEME=https` in `.env`
4. Ensure the `/app` location block is present in Nginx config

### Queue jobs not processing
1. Check worker: `sudo supervisorctl status medfind-queue:*`
2. Review logs: `tail -f /var/www/medfind/storage/logs/queue.log`
3. Verify `QUEUE_CONNECTION=database` in `.env`
4. Ensure queue table exists: `php artisan queue:table && php artisan migrate`

### 502 Bad Gateway
1. Check PHP-FPM is running: `sudo systemctl status php8.2-fpm`
2. Verify socket path matches Nginx config: `ls /var/run/php/php8.2-fpm.sock`
3. Check PHP-FPM logs: `sudo tail -f /var/log/php8.2-fpm.log`

### Permission errors
```bash
sudo chown -R www-data:www-data /var/www/medfind/storage
sudo chown -R www-data:www-data /var/www/medfind/bootstrap/cache
sudo chmod -R 775 /var/www/medfind/storage /var/www/medfind/bootstrap/cache
```
