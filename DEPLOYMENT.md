# Deployment

## Requirements
- PHP 8.4+ with extensions: `mbstring`, `xml`, `dom`, `curl`, `mysql`, `zip`
- Composer
- MySQL
- Apache with `mod_rewrite` enabled

## Fresh install

```bash
git clone <repo-url> lintune-front
cd lintune-front
composer install
cp .env.example .env
php artisan key:generate
```

## Configure `.env`

```env
APP_URL=https://yourdomain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=lintune
DB_USERNAME=<db_user>
DB_PASSWORD=<db_password>

KEYCLOAK_BASE_URL=https://auth.yourdomain.com
KEYCLOAK_CLIENT_ID=lintune-frontend
KEYCLOAK_ALLOWED_GROUPS=realm-admin
```

## Database

```bash
php artisan migrate
```

Insert domain-to-realm mapping for each tenant:

```sql
INSERT INTO domain_realm_map (domain, realm, created_at, updated_at)
VALUES ('yourdomain.nl', 'your-realm', NOW(), NOW());
```

## Apache virtual host

```apache
<VirtualHost *:80>
    DocumentRoot /var/www/lintune-front/public

    <Directory /var/www/lintune-front/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Enable rewrite module:

```bash
sudo a2enmod rewrite
sudo systemctl restart apache2
```

## Permissions

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
```

## Keycloak client setup

1. Create a realm per tenant
2. Create client `lintune-frontend` — type `public`, enable PKCE (`S256`)
3. Add `https://yourdomain.com/auth/callback` as a valid redirect URI
4. Ensure the user has the `realm-admin` role under `realm-management` resource roles
