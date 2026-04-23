# Installation

## Requirements

- PHP 8.3+
- Composer
- Node.js
- MySQL database (shared with lintune-admin)
- Keycloak instance
- Mailcow instance (optional)
- Nextcloud instance (optional)

## Steps

### 1. Clone & install dependencies

```bash
git clone <repo-url> lintune-dash
cd lintune-dash
composer install --no-dev --optimize-autoloader
```

### 2. Configure environment

```bash
cp .env.example .env
```

Edit `.env` and fill in the required values:

| Variable | Description |
|---|---|
| `APP_URL` | Public URL of this app (e.g. `https://dash.yourdomain.com`) |
| `APP_KEY` | Must match the `APP_KEY` in lintune-admin — both apps share encrypted values in the database |
| `DB_HOST` / `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | Same MySQL database as lintune-admin |
| `KEYCLOAK_BASE_URL` | Public URL of your Keycloak instance |
| `KEYCLOAK_CLIENT_ID` | Keycloak client ID (default: `lintune-frontend`) |
| `KEYCLOAK_ALLOWED_GROUPS` | Keycloak group required for access (default: `realm-admin`) |
| `KEYCLOAK_ADMIN_CLI_CLIENT` | Keycloak admin CLI client (default: `admin-cli`) |

> **Important:** Do not run `php artisan key:generate` in lintune-dash. Copy the `APP_KEY` from lintune-admin's `.env` instead. Both apps must share the same key to encrypt and decrypt values stored in the shared database.

> **Note:** Database migrations are managed by lintune-admin. Do not run migrations here.

### 3. Set storage permissions

```bash
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

### 4. Optimize for production

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Keycloak client setup

1. Create client `lintune-frontend` — type `public`, enable PKCE (`S256`)
2. Add `https://dash.yourdomain.com/auth/callback` as a valid redirect URI
3. Ensure users have the `realm-admin` role under `realm-management` resource roles

## Proxy / Reverse Proxy

If running behind Cloudflare or another reverse proxy, see the Keycloak proxy configuration in the lintune-admin docs.
