# lintune-dash — Claude Context

## What this repo is
Tenant admin portal. Used by realm admins (customers) to manage users, groups, mailboxes, and Nextcloud accounts within their own realm.
Each login session is scoped to a single realm — never cross-realm.

## Repo structure
```
app/Http/Controllers/         — AuthController, DashboardController, UserController, GroupController, AuditLogController
app/Http/Middleware/           — RequireAuth, RequireRealmAdmin
app/Models/                   — DomainRealmMap, Group, GroupMember, Mailbox, NextcloudUser
app/Services/                 — AuditLogger, MailcowService, NextcloudService
resources/views/               — layouts/app.blade.php, dashboard, users/, groups/, auth/
routes/web.php                 — all routes, protected by RequireAuth; groups also require RequireRealmAdmin
```

## Architecture rules
- **Never create migration files here.** All migrations live exclusively in lintune-admin. This repo has no `database/migrations/` files by design.
- This app only reads from and writes to the schema provisioned by lintune-admin.
- Every action must be scoped to `session('realm')` — never allow a tenant to access another realm's data.
- Realm provisioning, domain registration, and Mailcow domain setup are lintune-admin concerns. Do not replicate that logic here.

## Database
- Shared MariaDB with lintune-admin. Database name: `lintune`.
- Schema is owned and migrated by lintune-admin. This app never modifies schema.
- Key tables used: `domain_realm_maps`, `mailboxes`, `nextcloud_users`, `groups`, `group_members`, `audit_logs`, `sessions`.

## Session / Auth
- Auth is via Keycloak PKCE OAuth flow. Realm = domain (e.g. `company.com`).
- Session stores: `realm`, `access_token`, `token_expires_at`, `user_name`, `is_realm_admin`.
- `is_realm_admin` is set at login by checking `resource_access.realm-management.roles` in the JWT for `realm-admin`.
- Groups section is only visible/accessible to realm admins (`RequireRealmAdmin` middleware).
- Inactivity timeout: 5 minutes. Session check every 30 seconds via `/session-check`.

## Key models
- `DomainRealmMap` — read-only here; check `mailcow_enabled`, `nextcloud_enabled`, `max_*` limits before provisioning.
- `Group` — local record of groups (mailing_list or security type), with `mailcow_alias_id`, `keycloak_id`, `nextcloud_id`.
- `GroupMember` — pivot: `group_id`, `user_id` (Keycloak UUID), `email`.
- `Mailbox` — local record of mailboxes belonging to this realm.

## Group types
- **Mailing list** — backed by a Mailcow alias. Members must have an active mailbox. Alias is lazy-created on first member add, deleted when all members removed.
- **Security group** — synced to Keycloak (user-group membership) and Nextcloud (group membership).
- Member picker: option values encoded as `userId|email`, split with `explode('|', $item, 2)`.

## Services
- `MailcowService($realm)` — wraps Mailcow API. Methods: `createAlias`, `updateAlias`, `deleteAlias`. Check `isConfigured()` before calling.
- `NextcloudService($realm)` — wraps Nextcloud OCS API. Methods: `createGroup`, `deleteGroup`, `addGroupMember`, `removeGroupMember`. DELETE with body uses `deleteWithData()`.

## API conventions
- Always `rtrim($base, '/')` when building Keycloak or Mailcow API URLs.
- Nextcloud: Basic Auth + `OCS-APIRequest: true` header + `Accept: application/json`.
- Keycloak user operations use `session('access_token')` (the logged-in user's token, not an admin token).

## UI stack
- Bootstrap 5.3.3 + Bootstrap Icons 1.11.3 + AdminLTE 4.0.0-rc2.
- Layout: `resources/views/layouts/app.blade.php` — includes session timer, sidebar nav.
- Spinner overlay exists in `users/index.blade.php` — replicate this pattern (`#loadingOverlay`, shown on form submit) in other pages that need it.
- Use `&#64;` as the HTML entity for `@` when mixing Blade and email addresses in templates (e.g. `&#64;{{ session('realm') }}`).

## What NOT to do
- Do not create migration files.
- Do not allow access to data outside `session('realm')`.
- Do not replicate platform-level config — read from shared `settings` table or `DomainRealmMap`.
- Do not store plaintext credentials or API keys.
- Do not assume Mailcow or Nextcloud are configured — always check `isConfigured()` / `mailcow_enabled` / `nextcloud_enabled`.
