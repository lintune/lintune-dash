# Lintune Dash

Lintune Dash is the tenant-facing portal for the Lintune platform — an open-source alternative to Microsoft Azure AD / Entra ID, Exchange, and OneDrive, built on open-source components.

| Component | Role |
|---|---|
| [Keycloak](https://www.keycloak.org/) | Identity provider — realms, users, SSO |
| [Mailcow](https://mailcow.email/) | Email server — domains, mailboxes |
| Nextcloud *(planned)* | File storage & collaboration |

This repo is the **tenant admin portal**. It is what customers use to manage their own organisation.

## What it does

- Authenticates tenant admins via **Keycloak OIDC** (PKCE), scoped to their own realm — determined by their email domain
- Allows tenant admins to **manage users** in their Keycloak realm (create, update, enable/disable, delete)
- Allows tenant admins to **manage mailboxes** in Mailcow per user (enable/disable)
- Nextcloud user management *(planned)*

## How it fits in the platform

```
[ Tenant Admin ]
      |
      | logs in via their domain (e.g. company.com)
      v
[ lintune-dash ] ──► MySQL (domain_realm_map lookup → finds their Keycloak realm)
                 ──► Keycloak OIDC (authenticates against tenant realm)
                 ──► Keycloak Admin API (manage users in tenant realm)
                 ──► Mailcow API (manage mailboxes)
```

The login flow works as follows:
1. Tenant admin enters their email address
2. lintune-dash looks up the domain in `domain_realm_map` to find the correct Keycloak realm
3. The user is redirected to Keycloak for authentication (PKCE)
4. On callback, the session is established and the admin lands on their dashboard

> The database and `domain_realm_map` table are provisioned and managed by [lintune-admin](../lintune-admin). Do not run migrations in this repo.

## How it relates to lintune-admin

| Concern | lintune-admin | lintune-dash |
|---|---|---|
| Audience | Platform operators | Tenant admins |
| Realm provisioning | ✅ | ❌ |
| User management | Initial admin only | Full CRUD within own realm |
| Mailcow domain setup | ✅ | ❌ |
| Mailbox management per user | ❌ | ✅ |
| Database migrations | ✅ | ❌ |

## Roadmap

### Planned
- [ ] **Group management** — create and manage Keycloak groups per realm, assign users to groups. Groups are mirrored to Nextcloud when enabled, and will be used for workstation login policies and Nextcloud Team Folders
- [x] **Nextcloud user management** — provision and manage Nextcloud users per realm
- [ ] **Mailbox app passwords** — create and revoke per-device app passwords for IMAP/SMTP access via Mailcow. Password is shown once on creation and never stored. Allows users to connect email clients (Thunderbird, Outlook etc.) without using their main account password
- [ ] **Welcome email on user creation** — send a welcome email when a user is created. If Mailcow is enabled for the realm, sends via the Mailcow API using the tenant's domain. If Mailcow is not enabled, falls back to the MSP sender email configured in lintune-admin's platform settings
- [ ] **Mailbox quota management** — per-mailbox quota allocation (set individually in lintune-dash, within the domain pool set in lintune-admin). Includes a quota usage overview showing used/allocated per mailbox and total domain pool usage, pulled from the Mailcow API in a single call
- [ ] **Temporary Mailcow domain admin** — tenant admin can request a temporary Mailcow domain admin account (random password, shown once) for direct Mailcow access. Account is automatically deleted after 1 hour via the Laravel scheduler. Covers the edge cases that lintune-dash doesn't expose (DKIM, spam filters, aliases, catchalls etc.)
- [ ] **Queueing API calls** — Queue any API calls using the Laravel queue worker


## Installation

See [docs/install.md](docs/install.md).
