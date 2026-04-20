# No migrations in lintune-dash

Never create migration files in this repository. All database migrations are managed exclusively in lintune-admin. lintune-dash has no migration files by design.

The shared database and all its tables are provisioned and maintained by lintune-admin. lintune-dash only reads from and writes to the existing schema.
