# lintune-dash rules

## Architecture
- Never create migration files in this repository. All database migrations are managed exclusively in lintune-admin.
- lintune-dash only reads from and writes to the existing schema provisioned by lintune-admin.
- lintune-dash is scoped to a single realm per session. Never allow cross-realm data access.
- Realm provisioning, domain registration, and Mailcow domain setup are lintune-admin concerns. Do not replicate that logic here.

## Security
- Never store plaintext credentials, API keys, or passwords anywhere in the codebase or database.
- The logged-in tenant admin must only ever be able to manage resources within their own realm.

## API calls
- Always use `rtrim($base, '/')` when building Keycloak or Mailcow API URLs from config. Never assume the base URL has no trailing slash.

## Settings
- Platform-wide settings (e.g. MSP sender email) are read from the shared `settings` table owned by lintune-admin. Never duplicate these values into lintune-dash config.
