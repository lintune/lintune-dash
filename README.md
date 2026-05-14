# lintune-dash

Tenant portal for the Lintune platform — used by customer admins to manage their own organisation.

- Authenticates via Keycloak OIDC, scoped to the customer's realm (determined by email domain)
- User management — create, update, enable/disable, delete users in the tenant's Keycloak realm
- Mailbox management — enable/disable per-user mailboxes via Mailcow
- Nextcloud access — provision and revoke per-user Nextcloud accounts
- Group management — Keycloak groups synced to Mailcow aliases and Nextcloud

> Installed and configured automatically by [lintune-admin](../lintune-admin). Do not run migrations in this repo — all schema is owned by lintune-admin.

**Documentation:** [lintune.xyz/docs](https://lintune.xyz/docs)

## Stack

Laravel 11 · PHP 8.4 · MariaDB 11 (shared with lintune-admin) · Docker
