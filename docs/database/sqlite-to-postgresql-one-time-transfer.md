# SQLite-to-PostgreSQL One-Time Migration

## Completed Migration Summary

| Attribute | Value |
|-----------|-------|
| Source engine | SQLite |
| Source migrations applied | 7 of 17 |
| Source tables | 9 |
| Source total rows | 34 |
| Target engine | PostgreSQL 16 |
| Target host | 127.0.0.1:5432 |
| Target database | medfind |
| Canonical migrations applied on target | 17 (all) |
| Authoritative rows transferred | 27 |
| Additional tables (empty) | 10 (suppliers, notifications, etc.) |
| Source checksum (SHA-256) | b23589695ddddcc5437aed7fe75f45148f805627db39f34489c4b78ffca0617d |

### Row Distribution

| Table | Rows Transferred |
|-------|-----------------|
| users | 3 |
| pharmacies | 5 |
| medicines | 6 |
| inventory_items | 12 |
| sessions | 1 |
| messages | 0 |

---

## Ordered Procedure

The migration followed a dependency-gated workflow. Each task required all preceding gates to pass before execution.

| Task | Description | Evidence |
|------|-------------|----------|
| 3.1 | Preflight gates implemented | Driver availability, target reachability, source integrity verified |
| 3.2 | Backup, quiescence, and inventory procedures | Maintenance mode, source backup, schema classification |
| 3.3 | .env.example updated to pgsql template | PostgreSQL selected as default, placeholders for secrets |
| 3.4 | One-time migration utility | `OneTimeMigrationUtility` with transactional transfer and normalization |
| 3.5 | Isolated PostgreSQL rehearsal | Full rehearsal against disposable target passed |
| 3.6 | Production backup and inventory | Manifest: `database/backups/task_3_6_manifest_20260816_203433.json` |
| 3.7 | Schema creation and data transfer | Manifest: `database/backups/task_3_7_manifest_20260816_184435.json` |
| 3.8 | Identity sequence repair | Manifest: `database/backups/task_3_8_manifest_20260817_025014.json` |
| 3.9 | Pre-cutover verification | Manifest: `database/backups/task_3_9_verification.json` |
| 3.10 | Runtime cutover | `.env` updated to `DB_CONNECTION=pgsql`, config cache rebuilt |
| 3.11 | Bug condition test now passes | Original exploration property passes on fixed code |
| 3.12 | Preservation tests pass | No regressions, data integrity confirmed |

---

## PostgreSQL Configuration

All sensitive values must be supplied through the deployment secret mechanism. Never commit actual credentials to source control.

```dotenv
DB_CONNECTION=pgsql
DB_HOST=<postgres-host>
DB_PORT=5432
DB_DATABASE=<postgres-database>
DB_USERNAME=<postgres-username>
DB_PASSWORD=<replace-with-secret>
```

### DB_URL Precedence Rule

If `DB_URL` is set to a non-empty value, it overrides individual `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` fields. Operators must ensure `DB_URL` and individual `DB_*` values do not conflict. Either use `DB_URL` exclusively or leave it unset/empty and rely on the individual fields.

### Configuration Cache Rebuild

After any environment change (cutover, rollback, credential rotation):

```bash
php artisan config:clear
```

This clears the compiled configuration cache and forces Laravel to re-read `.env` on the next request. Rebuild the cache for production with `php artisan config:cache` once the new values are confirmed correct.

---

## Test Database Isolation

PHPUnit is configured to use an isolated SQLite in-memory database. Tests never connect to PostgreSQL.

### phpunit.xml Overrides

```xml
<env name="APP_ENV" value="testing"/>
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
<env name="DB_URL" value=""/>
```

### Safety Guard

`MigrationPreflight::assertSafeTestEnvironment()` rejects execution when the resolved database is not SQLite `:memory:`. This prevents `RefreshDatabase` from accidentally targeting PostgreSQL in test mode.

### Guarantees

- Tests always use SQLite `:memory:` regardless of `.env` values
- Empty `DB_URL` prevents URL-based override in the test environment
- No test can alter PostgreSQL row counts, hashes, or sequences

---

## Rollback Procedure

### SQLite Backup Location

```
database/backups/database_20260817_023155.sqlite
```

### Steps to Rollback (before PostgreSQL accepts writes)

1. Enter maintenance mode: `php artisan down`
2. Update `.env`:
   ```dotenv
   DB_CONNECTION=sqlite
   DB_DATABASE=database/database.sqlite
   ```
3. Clear configuration cache: `php artisan config:clear`
4. Restart application and worker services
5. Verify SQLite integrity and checksum match the original
6. Run smoke checks against the restored SQLite database
7. Exit maintenance mode: `php artisan up`

### After PostgreSQL Accepts Writes

Once PostgreSQL has accepted write traffic, the SQLite backup becomes stale. A blind revert would lose new PostgreSQL transactions.

Before reverting:

1. Re-enter maintenance mode
2. Preserve a PostgreSQL dump of the current state
3. Quantify the post-cutover delta (new rows, updated records)
4. Choose an approved reconciliation path:
   - Reverse-migrate new PostgreSQL records back to SQLite, or
   - Forward-repair the PostgreSQL issue without reverting
5. A rollback to SQLite is permitted only after new records are safely reverse-migrated and verified, or business owners explicitly accept their loss

---

## Production Warnings and Stop Gates

### Prohibited Commands

The following commands must NEVER be run against existing application data:

- `php artisan migrate:fresh` (drops all tables and re-runs migrations)
- `php artisan migrate:refresh` (rolls back and re-runs migrations)
- `php artisan db:wipe` (drops all tables)
- Any form of table truncation against authoritative tables
- Any seeder (`--seed`, `db:seed`, `DemoSeeder`, etc.)

### Explicit Operator Approval Required

The following tasks require independent operator approval before execution:

| Task | Reason |
|------|--------|
| 3.6 | Production backup and quiescence (initiates maintenance window) |
| 3.7 | Production schema creation and data transfer (modifies target) |
| 3.10 | Runtime cutover (changes active database engine) |

### Stop Gate Behavior

- Any failed gate aborts the migration without cutover
- The SQLite source remains unchanged and operational
- The unaccepted PostgreSQL target is discarded or recreated from canonical migrations
- No partial target is ever resumed or merged
- Maintenance mode remains active until rollback smoke checks pass

### Maintenance Mode Requirements

The entire transfer window (tasks 3.6 through 3.10) requires:

- Maintenance mode active (`php artisan down`)
- All queue workers stopped
- All schedulers stopped
- All Reverb/event consumers that persist state stopped
- No manual database writers active
- No SQLite write transactions remaining

---

## Reverb/Broadcasting

### Scope

Reverb, Nginx, WebSocket, and TLS implementation are **OUT OF SCOPE** for this database migration increment.

### Regression Verification

The migration verified no regression to broadcasting:

- Configuration hashes unchanged before and after migration
- Event contracts preserved (channels, payloads, authorization)
- Broadcasting connection remains `reverb` (configured in `.env`)
- No broadcaster declarations were added, removed, or duplicated
- Client-visible real-time behavior is unaffected by the database engine change

### Future Work

Secure WebSocket proxying, Nginx configuration, and TLS setup for Reverb remain follow-up implementation increments under the same spec.

---

## Artifact Retention

All artifacts must be retained until explicitly approved for deletion by the change owner.

| Artifact | Location | Purpose |
|----------|----------|---------|
| Original SQLite database | `database/database.sqlite` | Rollback source, keep until deletion approved |
| Quiesced SQLite backup | `database/backups/database_20260817_023155.sqlite` | Verified restoration artifact |
| Task 3.6 manifest | `database/backups/task_3_6_manifest_20260816_203433.json` | Production inventory evidence |
| Task 3.7 manifest | `database/backups/task_3_7_manifest_20260816_184435.json` | Transfer evidence |
| Task 3.8 manifest | `database/backups/task_3_8_manifest_20260817_025014.json` | Sequence repair evidence |
| Task 3.9 verification | `database/backups/task_3_9_verification.json` | Pre-cutover acceptance evidence |
| Transfer harness | `database/backups/task_3_7_transfer.php` | One-time transfer execution script |

### Retention Rules

- Do not delete the SQLite file or backup as part of the migration
- Retirement is a separate, future approved operation after the observation period
- Manifests contain no secrets (passwords, keys, tokens, or sensitive row data)
- Backups should have restrictive file permissions
- No artifact filename contains credentials or secrets

---

## Transfer Utility Reference

### Class

```
App\Database\Migration\OneTimeMigrationUtility
```

### Dependency-Safe Load Order

1. `users` (with `pharmacy_id` deferred to NULL)
2. `pharmacies` (with `user_id` referencing already-loaded users)
3. Restore `users.pharmacy_id` original values
4. `medicines` and `suppliers`
5. `inventory_items`
6. `messages`
7. `cycle_counts`, then `cycle_count_items`
8. `controlled_substance_logs`, `returns_recalls`, `inventory_audits`
9. `search_logs`, `notifications`, `activity_logs`, `survey_responses`
10. `sessions` (only under approved continuity with same `APP_KEY`)

### Excluded from Transfer

| Table | Reason |
|-------|--------|
| `migrations` | Generated by Laravel on target; name parity verified |
| `cache` | Transient operational data; documented empty exception |
| `cache_locks` | Transient operational data; documented empty exception |

### Normalization Rules Applied

- SQLite `0`/`1` booleans converted to PostgreSQL `true`/`false`
- JSON columns validated before insertion; SQL NULL preserved as NULL
- Decimal values transferred as exact strings (no floating-point formatting)
- Timestamps validated with declared timezone interpretation
- UUIDs validated for correct syntax
- UTF-8 validated; invalid encoding rejected with diagnostic
- Empty strings preserved separately from NULL
- Password hashes, tokens, and opaque payloads preserved byte-for-byte

### Identity Sequence Repair

After transfer, each integer identity sequence was reset:

```sql
SELECT setval(
  pg_get_serial_sequence('<table>', 'id'),
  COALESCE((SELECT MAX(id) FROM <table>), 1),
  EXISTS (SELECT 1 FROM <table>)
);
```

Tables with sequence repair: `users`, `pharmacies`, `medicines`, `inventory_items`, `messages`, `suppliers`, `controlled_substance_logs`, `cycle_counts`, `cycle_count_items`, `returns_recalls`, `inventory_audits`, `search_logs`, `activity_logs`, `survey_responses`.

Excluded: `notifications` (UUID key), `sessions` (string key), `cache`/`cache_locks` (string key), `migrations` (Laravel-managed).

---

## Preflight Gate Summary

The `MigrationPreflight` class verifies the following before any transfer:

- PHP extensions: `PDO`, `pdo_pgsql`, `pgsql`, `pdo_sqlite`, `sqlite3`
- PostgreSQL server reachable and authorized
- Target database is empty or has explicit replacement approval
- Source SQLite file readable and passes integrity check
- No conflicting `DB_URL` and `DB_*` values
- Sufficient disk space for backup and transfer artifacts
- Canonical migration files available
- Test environment resolves to SQLite `:memory:` (safety guard)

Any failed check aborts the process and preserves the source unchanged.
