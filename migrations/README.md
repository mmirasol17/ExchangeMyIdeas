# Database migrations

Each `.sql` file here is a schema change that runs **once**, in filename order.
`migrate.php` tracks applied files in a `schema_migrations` table.

## Naming

Prefix with a zero-padded sequence number so ordering is unambiguous:

```
001_add_likes_to_posts.sql
002_create_tags_table.sql
003_add_view_count.sql
```

## Writing one

Keep each file to a focused change. Example — `001_add_likes_to_posts.sql`:

```sql
ALTER TABLE blog_posts ADD COLUMN likes INT NOT NULL DEFAULT 0;
```

Migrations should be **additive and idempotent-friendly** where possible
(e.g. `CREATE TABLE IF NOT EXISTS ...`). Avoid destructive changes unless you
mean them — there is no automatic rollback.

## Applying

1. Add the `.sql` file, commit, and push — it auto-deploys over FTP.
2. Visit the runner once in your browser:
   `https://exchangemyideas.marinmirasol.com/migrate.php?key=YOUR_KEY`
   (the key is `$migrateKey` in the server's `config.local.php`).

The page prints which migrations it applied, or "No pending migrations."
Re-visiting is safe — already-applied files are skipped.

> Not run from CI on purpose: InfinityFree blocks remote MySQL connections and
> challenges non-browser HTTP requests, so migrations are applied by visiting
> this URL in a browser rather than from the deploy workflow.
