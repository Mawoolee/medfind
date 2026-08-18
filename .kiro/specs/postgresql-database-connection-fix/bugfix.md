# Bugfix Requirements Document

## Introduction

This bugfix ensures that MedFind actually uses a reachable PostgreSQL database at runtime rather than treating the `DB_CONNECTION=pgsql` declaration or prior PostgreSQL-labeled query logs as sufficient proof. The fix must verify the effective live connection, preserve all existing data, safely handle configuration caching, confirm migration status, and avoid any cross-database data migration unless a different active source database is first proven to exist.

## Bug Analysis

### Current Behavior (Defect)

The repository declares PostgreSQL for the root application, but declarations and log labels alone do not establish that the running application can successfully use the intended PostgreSQL database.

1.1 WHEN the database switch is considered complete solely because configuration text declares `pgsql` or prior query logs identify `pgsql` THEN the system reports or assumes PostgreSQL readiness without proving the effective runtime driver and a successful live database operation.

1.2 WHEN stale cached configuration, an unavailable PostgreSQL driver, invalid credentials, or an unreachable PostgreSQL service prevents the declared connection from being used THEN the system fails database operations even though the environment file names PostgreSQL.

1.3 WHEN another database configuration or database file exists in the repository but has not been proven to be the active source used by the application THEN the system risks treating that artifact as migration input and modifying or replacing data unnecessarily.

1.4 WHEN PostgreSQL connectivity is assessed without checking migration status on the live target database THEN the system leaves the target schema state and pending-migration state unverified.

1.5 WHEN switching or verifying the database involves destructive migration, table truncation, or reseeding THEN the system can erase, replace, or duplicate existing application data.

### Expected Behavior (Correct)

PostgreSQL readiness must be established from the running Laravel application's effective connection and a non-destructive live operation, with all data-protection constraints enforced.

2.1 WHEN the PostgreSQL switch is verified THEN the system SHALL confirm at runtime that the effective default connection driver is `pgsql`, that it targets the intended PostgreSQL database, and that a non-destructive live query succeeds before reporting the switch as complete.

2.2 WHEN cached configuration could differ from the current database settings THEN the system SHALL clear only the Laravel configuration cache safely, without clearing database-backed application data or invoking destructive operations, and SHALL repeat runtime connection verification afterward.

2.3 WHEN a different database configuration or database file is discovered THEN the system SHALL determine through runtime or otherwise conclusive evidence whether it is an active source database before performing any cross-database data migration; repository presence or configuration text alone SHALL NOT qualify as proof.

2.4 WHEN the live PostgreSQL connection succeeds THEN the system SHALL verify migration status against that exact live connection and report whether the schema is current without using migration-status inspection to modify data.

2.5 WHEN no different active source database is proven to exist THEN the system SHALL perform no cross-database data migration, import, copy, destructive migration, table truncation, or reseeding.

2.6 WHEN a different active source database is conclusively proven and data migration becomes necessary THEN the system SHALL preserve every existing record, relationship, identifier, and applicable data value, SHALL use a recoverable non-destructive process, and SHALL verify source-to-target data integrity before the switch is considered complete.

### Unchanged Behavior (Regression Prevention)

Existing PostgreSQL usage, application data, and unrelated application behavior must remain intact while the runtime connection is verified.

3.1 WHEN the application is already using the intended reachable PostgreSQL database at runtime THEN the system SHALL CONTINUE TO use that database without copying, resetting, recreating, or reseeding it.

3.2 WHEN existing users, pharmacies, medicines, inventories, messages, transactions, logs, or other application records are present THEN the system SHALL CONTINUE TO retain those records and their relationships exactly as they existed before verification.

3.3 WHEN seeders or commands capable of truncating, refreshing, resetting, or rebuilding tables are available THEN the system SHALL CONTINUE TO leave them unexecuted as part of this bugfix.

3.4 WHEN a repository artifact such as the root SQLite file or the nested Laravel application's MySQL configuration is not proven to be an active source database THEN the system SHALL CONTINUE TO leave that artifact unchanged and SHALL NOT migrate data from it.

3.5 WHEN automated tests intentionally use an isolated SQLite in-memory database THEN the system SHALL CONTINUE TO use that isolated test configuration without treating it as the application's active source database.

3.6 WHEN application features operate correctly with the verified PostgreSQL connection THEN the system SHALL CONTINUE TO provide their existing behavior and outputs without unrelated functional changes.
