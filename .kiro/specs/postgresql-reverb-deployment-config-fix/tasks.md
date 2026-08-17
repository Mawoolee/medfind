# Implementation Plan

## Overview

> **Immediate scope:** Perform the controlled migration of existing MedFind data from SQLite to PostgreSQL. Reverb, Nginx, WebSocket, and TLS implementation changes are excluded; this plan only verifies that the database migration does not regress existing Reverb behavior.
>
> **Execution safety:** Tasks are dependency-ordered. Do not run a production-impacting task until all preceding gates pass, a restorable backup is proven, the maintenance window is active, and the operator gives explicit approval. Never use `migrate:fresh`, `migrate:refresh`, `db:wipe`, truncation, or seeders against existing business data.

This plan follows the bug-condition workflow: establish the failing exploration baseline and passing preservation baseline first, implement and rehearse the migration only in isolated environments, and proceed to production preparation and cutover only through the documented stop gates and explicit approvals.

## Task Dependency Graph

Dependencies are mandatory gates, not scheduling suggestions. A task may start only after every listed dependency is complete and its required evidence is available. Explicit operator approval remains an additional dependency wherever the task warning requires it.

| Task | Depends on | Gate established |
| --- | --- | --- |
| 1. Bug condition exploration test | None | Failing unfixed-code counterexample documented |
| 2. Preservation property tests | None | Passing unfixed-code behavior baseline documented |
| 3. Migration implementation (parent) | 1, 2 | Both pre-fix baselines exist before migration work |
| 3.1 Preflight and fail-closed safety gates | 1, 2 | Migration work cannot begin before exploration and preservation baselines |
| 3.2 Backup, quiescence, and source inventory procedures | 3.1 | Preflight rules exist before backup and inventory procedures |
| 3.3 Checked-in PostgreSQL configuration template | 3.1 | Environment and test-safety gates are defined before template changes |
| 3.4 One-time migration utility and runbook | 3.1, 3.2, 3.3 | Preflight, recovery, inventory, and configuration rules precede transfer implementation |
| 3.5 Isolated disposable rehearsal | 3.4 | Implementation and preflight controls are complete before rehearsal |
| 3.6 Production backup, quiescence, and inventory gate | 3.5, plus the explicit approval stated in task 3.6 | Rehearsal passes before any production preparation begins |
| 3.7 Production target schema and authoritative transfer | 3.6, plus the explicit approval stated in task 3.7 | Production preparation gate is fully green before data operations |
| 3.8 PostgreSQL identity sequence repair and verification | 3.7 | Transfer completes before sequence repair and probes |
| 3.9 Pre-cutover verification and acceptance gate | 3.8 | Schema, transfer, and sequence evidence exist before acceptance |
| 3.10 Runtime cutover and rollback protection | 3.9, plus the separate explicit approval stated in task 3.10 | Every pre-cutover predicate passes before runtime changes or writes |
| 3.11 Expected-behavior property verification | 3.10, 1 | Re-run the original exploration property only after the gated fix and cutover |
| 3.12 Preservation and regression verification | 3.10, 3.11, 2 | Re-run the original preservation baseline after expected behavior passes |
| 3.13 Migration and operator documentation | 3.12 | Document only the verified procedure and outcome |
| 4. Final checkpoint | 3.13 | All implementation, validation, evidence, and documentation are complete |

```json
{
  "waves": [
    {
      "wave": 1,
      "tasks": ["1", "2"],
      "dependsOn": []
    },
    {
      "wave": 2,
      "tasks": ["3.1"],
      "dependsOn": ["1", "2"]
    },
    {
      "wave": 3,
      "tasks": ["3.2", "3.3"],
      "dependsOn": ["3.1"]
    },
    {
      "wave": 4,
      "tasks": ["3.4"],
      "dependsOn": ["3.1", "3.2", "3.3"]
    },
    {
      "wave": 5,
      "tasks": ["3.5"],
      "dependsOn": ["3.4"]
    },
    {
      "wave": 6,
      "tasks": ["3.6"],
      "dependsOn": ["3.5"],
      "requiresExplicitApproval": true
    },
    {
      "wave": 7,
      "tasks": ["3.7"],
      "dependsOn": ["3.6"],
      "requiresExplicitApproval": true
    },
    {
      "wave": 8,
      "tasks": ["3.8"],
      "dependsOn": ["3.7"]
    },
    {
      "wave": 9,
      "tasks": ["3.9"],
      "dependsOn": ["3.8"]
    },
    {
      "wave": 10,
      "tasks": ["3.10"],
      "dependsOn": ["3.9"],
      "requiresExplicitApproval": true
    },
    {
      "wave": 11,
      "tasks": ["3.11"],
      "dependsOn": ["3.10", "1"]
    },
    {
      "wave": 12,
      "tasks": ["3.12"],
      "dependsOn": ["3.10", "3.11", "2"]
    },
    {
      "wave": 13,
      "tasks": ["3.13"],
      "dependsOn": ["3.12"]
    },
    {
      "wave": 14,
      "tasks": ["4"],
      "dependsOn": ["3.13"]
    }
  ]
}
```

## Tasks

- [x] 1. Write the bug condition exploration test before implementing the migration
  - **Property 1: Bug Condition** - Complete SQLite-to-PostgreSQL Cutover
  - **CRITICAL**: This property-based test MUST FAIL on unfixed code; failure confirms that the migration bug exists. Do not fix the test or implementation during this task.
  - Encode `isBugCondition(input)` from the design for migration contexts where PostgreSQL is intended and at least one condition is true: the runtime still selects a non-`pgsql` engine, PostgreSQL prerequisites/schema are not ready, or an existing readable SQLite source has not been proven equivalent on PostgreSQL.
  - Encode `expectedBehavior(result)` from the design: the accepted result uses `pgsql`, matches canonical migrations and authoritative data, preserves primary keys, has zero relationship/uniqueness/type violations, has collision-safe identity sequences, passes database smoke checks, and has a restorable backup.
  - Use generated sanitized migration contexts covering missing `pdo_pgsql`, unreachable or unauthorized targets, non-empty/unknown targets, conflicting `DB_URL`/`DB_*` values, invalid SQLite integrity, unknown tables/columns, malformed typed values, circular relationships, sparse IDs, and transfer or verification failures.
  - Scope deterministic checks to concrete counterexamples, including an unchanged `.env.example` resolving to SQLite, a connection-only PostgreSQL switch exposing an empty target, explicit imported IDs without sequence repair, and a test environment that does not resolve to SQLite `:memory:`.
  - Run only against the unfixed application, sanitized SQLite fixtures, and disposable PostgreSQL targets; never point exploration at the production SQLite file or any production PostgreSQL database.
  - Assert fail-closed behavior: when any prerequisite or verification predicate is false, cutover is not performed and the source checksum remains unchanged.
  - Record the minimized counterexamples and failure output in test artifacts without credentials, application keys, tokens, Reverb secrets, production hosts, or row payloads that contain sensitive data.
  - **EXPECTED OUTCOME**: At least the current template-selection/cutover property fails on unfixed code, proving the defect. Mark this task complete only after the failing counterexample is documented.
  - _Requirements: 1.1, 1.3, 2.1, 2.3, 2.6_

- [x] 2. Write preservation property tests before implementing the migration
  - **Property 2: Preservation** - Existing Data, Behavior, and Test Isolation
  - **IMPORTANT**: Follow the observation-first methodology and run these tests on unfixed code before any configuration or migration change.
  - Observe and record current behavior for cases outside the bug condition: authoritative SQLite row values and primary keys, user/pharmacy and child relationships, authentication and authorization results, storage references, representative non-real-time workflows, resolved Reverb configuration/event definitions, explicit environment overrides, and PHPUnit's resolved SQLite `:memory:` database.
  - Generate valid source rows and related entity graphs across nullability, Unicode, booleans, exact decimals, JSON, dates/timestamps, UUIDs, empty strings, sparse IDs, deleted-ID gaps, and opaque credentials/payloads; capture canonical outputs using stable ordering and explicit null/type representations.
  - Write property-based comparisons asserting that semantically unchanged inputs produce equivalent domain-visible results and relationships before and after a disposable migration, while the SQLite source checksum remains unchanged.
  - Add a hard test-safety property that rejects execution unless `APP_ENV=testing`, `DB_CONNECTION=sqlite`, and `DB_DATABASE=:memory:` resolve explicitly; verify PostgreSQL target counts/hashes are unchanged by the test suite.
  - Snapshot the existing Reverb broadcaster selection, channels, event payloads, authorization behavior, and relevant configuration so the database-only increment can prove no regression without implementing Reverb or Nginx changes.
  - Verify these baseline preservation tests PASS on unfixed code and retain the observed baseline artifacts without secrets.
  - **EXPECTED OUTCOME**: Tests pass on unfixed code and define the behavior the migration must preserve.
  - _Requirements: 3.1, 3.3, 3.4, 3.5, 3.6_

- [ ] 3. Implement and execute the gated SQLite-to-PostgreSQL migration

  - [x] 3.1 Implement migration preflight and fail-closed safety gates
    - Verify the web and CLI runtimes expose `PDO`, `pdo_pgsql`, `pgsql`, `pdo_sqlite`, and `sqlite3`; verify PostgreSQL reachability, authorization, TLS policy, encoding/timezone policy, and required backup tool availability.
    - Require the target to be dedicated and empty, or stop unless a backup plus explicit replacement approval exists; reject an unknown populated target and conflicting `DB_URL`/`DB_*` settings.
    - Verify source readability and SQLite integrity, sufficient disk space, migration-role privileges, source uniqueness/constraint compatibility, canonical migration availability, and supported PHP/PostgreSQL versions.
    - Implement a hard test guard that refuses any migration/test process resolving to PostgreSQL or a file-backed SQLite database when `APP_ENV=testing`.
    - Make every gate write a redacted result to the transfer manifest and abort before cutover on failure.
    - _Bug_Condition: `isBugCondition(input)` is true when PostgreSQL is intended but runtime selection, target readiness, or transfer proof is incomplete._
    - _Expected_Behavior: Failed prerequisites leave the active runtime unchanged; satisfied prerequisites permit only a still-unexposed disposable target to proceed toward `expectedBehavior(result)`._
    - _Preservation: Keep the SQLite source unchanged, retain explicit environment overrides, protect test isolation, and disclose no secrets._
    - _Requirements: 1.3, 2.3, 2.6, 3.1, 3.3, 3.6_

  - [x] 3.2 Implement backup, quiescence, and source inventory procedures
    - Define a maintenance/quiescence checklist that stops writable HTTP processes, queue workers, schedulers, Reverb/event consumers that can persist data, and manual writers before any source copy or production transfer.
    - Open the source through read-only SQLite mode and reject writable source access.
    - Create a quiesced filesystem backup and record its checksum, size, timestamp, restricted path, and restoration-test result; preserve deployment configuration/cache state outside source control without placing secret values in the manifest.
    - Inventory `sqlite_master`, tables, columns, indexes, foreign keys, migration names, authoritative row counts, and primary-key minima/maxima.
    - Classify every table and column as authoritative, operational, or explicitly excluded; abort on unknown schema. Treat `migrations` as canonical-schema state and `cache`/`cache_locks` as documented transient exceptions.
    - Include `sessions` only if continuity is approved and the existing `APP_KEY` is retained; otherwise require an explicitly approved forced-logout exception in the manifest.
    - _Bug_Condition: `sourceHasState` is true when a readable SQLite source exists, so transfer cannot be considered proven without an immutable inventory and restorable backup._
    - _Expected_Behavior: `expectedBehavior(result)` requires `backupIsRestorable` and complete source/target equivalence evidence before cutover._
    - _Preservation: The original SQLite file, identifiers, records, relationships, credentials, timestamps, storage references, and migration history remain recoverable._
    - _Requirements: 1.3, 2.3, 2.6, 3.1, 3.5_

  - [x] 3.3 Update the checked-in PostgreSQL configuration template without exposing secrets
    - Change `.env.example` to select `DB_CONNECTION=pgsql` and include every supported deployment setting used by guidance: host, port, database, username, password placeholder, charset, SSL mode, and the documented `DB_URL` alternative/precedence rule.
    - Use non-secret replacement placeholders for environment-specific values and keep real production values exclusively in the deployment secret mechanism outside source control.
    - Preserve `config/database.php` environment-driven overrides unless a verified incompatibility requires a narrowly scoped correction.
    - Retain `phpunit.xml` overrides for `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, and an empty `DB_URL`.
    - Do not add, remove, or duplicate Reverb broadcaster declarations in this database-only increment.
    - _Bug_Condition: The runtime is wrong when PostgreSQL is intended but the deployable template selects SQLite or omits required PostgreSQL inputs._
    - _Expected_Behavior: Fresh configuration selects `pgsql` and names every required PostgreSQL value using safe placeholders._
    - _Preservation: Explicit environment values and isolated test overrides continue to win; Reverb configuration remains unchanged; no literal secret enters source control._
    - _Requirements: 1.1, 2.1, 2.6, 3.3, 3.4, 3.6_

  - [x] 3.4 Implement the one-time migration utility and auditable runbook
    - Use parameterized raw SQLite/PostgreSQL operations and transactional target writes; do not instantiate Eloquent models, fire observers/events/broadcasts, enqueue jobs, hash credentials, or invoke factories/seeders.
    - Create the target schema only from checked-in Laravel migrations using the documented non-interactive production-safe migration command; never use destructive refresh/wipe commands.
    - Implement deterministic, bounded-batch transfer with resumability limited to a discarded/recreated unaccepted target or an explicitly proven safe checkpoint; never silently continue a partially accepted target.
    - Implement strict normalization: accepted SQLite boolean forms to PostgreSQL booleans, semantic JSON validation, exact decimal strings, declared timestamp interpretation, valid UUIDs/UTF-8, and distinct handling of SQL `NULL` versus empty strings/JSON null.
    - Preserve password hashes, remember tokens, opaque payloads, encrypted-file references, session payloads, notification data, primary keys, and source values byte-for-byte whenever PostgreSQL type/encoding rules permit.
    - Implement dependency-safe loading: users with deferred `pharmacy_id`, pharmacies, restore user/pharmacy links, medicines and suppliers, inventory, messages, cycle counts/items, controlled-substance/recall/audit data, logs/notifications/surveys, and approved sessions.
    - Keep target application traffic disabled throughout schema creation and transfer; roll back or discard the unaccepted target on any error while leaving SQLite untouched.
    - Emit a redacted transfer manifest containing schema status, table counts, key ranges, canonical hashes, normalization outcomes, sequence status, backup references/checksums, and gate decisions.
    - _Bug_Condition: `transferNotProven` is true while authoritative equivalence, valid relationships, and safe identities have not all been established._
    - _Expected_Behavior: The utility can produce a canonical PostgreSQL target satisfying all predicates in `expectedBehavior(result)` or abort without cutover._
    - _Preservation: Preserve source data semantics and application-visible behavior; do not seed, truncate, mutate SQLite, invoke Reverb, or leak secrets._
    - _Requirements: 1.3, 2.3, 2.6, 3.1, 3.4, 3.5, 3.6_

  - [x] 3.5 Rehearse the complete migration against isolated disposable PostgreSQL
    - Use a sanitized SQLite snapshot/fixture covering every authoritative table, circular and child relationships, sparse IDs, nulls, booleans, JSON, decimals, timestamps, UUIDs, Unicode, empty strings, opaque payloads, and operational-table exceptions.
    - Run preflight, backup simulation, canonical schema migration, transfer, sequence repair, verification, provisional cutover, smoke checks, pre-write rollback, and backup restoration only in the isolated environment.
    - Inject missing-driver, unreachable-target, non-empty-target, malformed-value, orphan, duplicate-key, unknown-schema, transfer-interruption, failed-verification, and unsafe-test-database conditions; assert each aborts before cutover and preserves the source checksum.
    - Confirm logs, diagnostics, commands, and the manifest redact credentials and sensitive row content while retaining actionable table/key/column context.
    - Do not proceed to production preparation until the full rehearsal passes repeatedly from a clean target and the rollback restoration is proven.
    - _Bug_Condition: Generated rehearsal contexts exercise every branch of `isBugCondition(input)` without touching production._
    - _Expected_Behavior: Successful rehearsals satisfy `expectedBehavior(result)`; failed rehearsals perform no cutover and preserve the source._
    - _Preservation: Sanitized data semantics, test isolation, non-real-time workflows, and Reverb definitions/configuration remain equivalent._
    - _Requirements: 2.3, 2.6, 3.1, 3.3, 3.4, 3.5, 3.6_

  - [x] 3.6 Perform the production backup, quiescence, and source inventory gate
    - **PRODUCTION-IMPACTING — EXPLICIT APPROVAL REQUIRED**: Do not begin until tasks 1 through 3.5 pass, the maintenance window is approved and announced, the exact source/target identities are independently checked, operators approve downtime, and rollback owners are present.
    - Enter maintenance mode and stop every writer identified by the runbook; prove no SQLite write transaction remains before copying or reading the final source snapshot.
    - Create and restoration-test the protected SQLite backup, preserve environment/config-cache state securely, and back up any pre-existing approved PostgreSQL target before replacement.
    - Run the read-only source inventory, classify all schema, and compare counts, key ranges, migration names, uniqueness, relationships, and typed-value validity with rehearsal assumptions.
    - **STOP GATE**: Abort and restore normal SQLite service without target cutover if backup restoration, integrity, inventory classification, resource capacity, target identity, or any preflight check fails.
    - _Bug_Condition: A production source with unproven backup/inventory remains inside `isBugCondition(input)` and is not safe to migrate._
    - _Expected_Behavior: Production transfer begins only from a quiesced, checksummed, restorable, fully classified SQLite snapshot._
    - _Preservation: No production source row is modified; existing data and configuration remain recoverable before target work starts._
    - _Requirements: 1.3, 2.3, 2.6, 3.1, 3.5, 3.6_

  - [x] 3.7 Create the isolated production target schema and transfer authoritative data
    - **PRODUCTION DATA OPERATION — EXPLICIT APPROVAL REQUIRED**: Continue only while maintenance/quiescence remains proven, the target is unexposed and correctly identified, the backup restore test passed, and task 3.6 has no unresolved exception.
    - Run checked-in migrations against the dedicated target with no seeders, then compare tables, columns, types, defaults, indexes, unique constraints, nullability, foreign keys, and migration names with the canonical schema.
    - Transfer authoritative tables in the dependency-safe order implemented in task 3.4, preserving explicit IDs and restoring deferred circular user/pharmacy references.
    - Keep canonical `migrations` records generated by Laravel; leave `cache` and `cache_locks` empty and record the exception; transfer sessions only under the approved continuity decision.
    - Reject invalid booleans, JSON, decimals, timestamps, UUIDs, UTF-8, duplicate unique keys, broken references, unknown schema, or any lossy conversion with redacted diagnostics.
    - **STOP GATE**: On any error, roll back/discard the unaccepted target, keep runtime on SQLite, preserve the source checksum, and do not improvise manual production edits.
    - _Bug_Condition: Existing source state with non-equivalent target rows or relationships makes `transferNotProven` true._
    - _Expected_Behavior: Target schema is canonical and every authoritative row has a semantically equivalent PostgreSQL representation with the same primary key._
    - _Preservation: Relationships, credentials, timestamps, nullable/decimal/JSON values, UUIDs, opaque payloads, sessions decision, and storage references retain their source meaning._
    - _Requirements: 1.3, 2.3, 3.1, 3.5_

  - [x] 3.8 Repair and verify PostgreSQL identity sequences
    - Reset each integer identity sequence listed in the design using the imported table maximum and correct empty-table `is_called` behavior.
    - Exclude UUID/string-key tables and framework-generated migration sequence state as specified by the design.
    - For every application identity table, obtain a default generated ID inside a transaction, assert it is greater than the imported maximum and non-colliding, then roll back the probe.
    - Record sequence names, imported maxima, repaired state, and probe results in the manifest without row payloads or secrets.
    - **STOP GATE**: Do not verify or cut over if any sequence is missing, ambiguous, not advanced, or generates a colliding value.
    - _Bug_Condition: `transferNotProven` remains true while `identitySequencesSafe` is false._
    - _Expected_Behavior: `nextIdentityValuesExceedImportedMaxima` is true for every imported integer identity table._
    - _Preservation: Imported primary keys and row counts/hashes remain unchanged; probe inserts leave no persistent records._
    - _Requirements: 2.3, 3.1, 3.5_

  - [x] 3.9 Complete the pre-cutover verification and acceptance gate
    - Verify canonical migration/schema parity and confirm no source table or column is unclassified or omitted.
    - Compare authoritative table counts, primary-key sets/ranges, and canonical per-row/per-table hashes using deterministic column/key order, explicit null markers, stable JSON ordering, and exact decimal/timestamp representations.
    - Verify zero foreign-key violations and explicit zero-orphan queries, including unconstrained `inventory_items.supplier_id`; verify circular links and unique keys match the source.
    - Confirm zero invalid typed values, collision-safe identities, and unchanged storage path references plus sampled file availability.
    - Against the unexposed target, verify Laravel resolves the intended `pgsql` database without logging credentials; run rolled-back read/write probes and representative authentication, authorization, pharmacy, inventory, messaging, notification, survey, and audit checks.
    - Verify existing password hashes authenticate unchanged and capture a no-diff check for Reverb broadcaster selection, channels, payloads, authorization code/config, and queue behavior.
    - Assert the test environment resolves only to SQLite `:memory:`, run the existing non-watch test suite once, and prove target counts/hashes remain unchanged.
    - Restore the final PostgreSQL dump into a second isolated database and repeat schema/data checks to prove restorability.
    - **FINAL STOP GATE BEFORE CUTOVER**: Any failed or waived predicate keeps runtime on SQLite. Exceptions require a new approved design decision; they must not be silently accepted.
    - _Bug_Condition: Cutover is unsafe while any target readiness, equivalence, relationship, sequence, smoke-check, or backup predicate is false._
    - _Expected_Behavior: Every predicate in `expectedBehavior(result)` is true before the runtime connection changes._
    - _Preservation: Existing records, behavior, authorization, file references, test isolation, and Reverb behavior remain equivalent._
    - _Requirements: 2.3, 2.6, 3.1, 3.3, 3.4, 3.5, 3.6_

  - [ ] 3.10 Cut over the runtime to PostgreSQL and retain rollback protection
    - **DESTRUCTIVE/PRODUCTION-IMPACTING — SEPARATE EXPLICIT APPROVAL REQUIRED**: Do not cut over unless task 3.9 is fully green, maintenance mode is still active, final source/target identifiers and backup checksums are independently confirmed, the rollback procedure has an assigned operator, and the change owner explicitly authorizes cutover.
    - Update deployment environment selection to the verified PostgreSQL target using the secret mechanism; retain the existing `APP_KEY`, clear/rebuild Laravel configuration cache, and restart application/worker services in dependency-safe order.
    - Before allowing writes, confirm web and worker runtimes resolve the same expected `pgsql` database and TLS policy, then repeat HTTP/database/authentication/authorization and representative workflow smoke checks.
    - If any pre-write active check fails, restore SQLite selection, rebuild configuration cache, restart services, verify SQLite checksum/integrity, and keep maintenance mode until rollback smoke checks pass.
    - Permit writes and exit maintenance mode only after active checks pass and explicit go-live approval is recorded.
    - After PostgreSQL accepts writes, never switch blindly to stale SQLite: preserve a PostgreSQL dump, quantify the delta, and require an approved reverse-migration/reconciliation or forward-repair decision.
    - Retain the original SQLite file, quiesced backup, manifests, environment backup, and final PostgreSQL dump under approved encryption, access, and retention controls; deletion is a separate future approval.
    - _Bug_Condition: `runtimeStillWrong` becomes false only after a verified PostgreSQL target is selected safely._
    - _Expected_Behavior: The active runtime uses `pgsql`, all `expectedBehavior(result)` predicates remain true, and rollback artifacts are restorable._
    - _Preservation: Existing data and behavior remain available after cutover, environment overrides remain effective, and no backup is deleted automatically._
    - _Requirements: 2.1, 2.3, 2.6, 3.1, 3.5, 3.6_

  - [ ] 3.11 Verify the original bug condition exploration test now passes
    - **Property 1: Expected Behavior** - Complete SQLite-to-PostgreSQL Cutover
    - **IMPORTANT**: Re-run the same property-based test from task 1; do not replace it with a new or weaker test.
    - Confirm the accepted production migration result satisfies `expectedBehavior(result)`: PostgreSQL runtime selection, canonical schema, authoritative count/hash equality, preserved keys, zero relationship/uniqueness/type violations, safe identities, smoke checks, and restorable backups.
    - Re-run fail-closed generated cases only in isolated/disposable environments and confirm invalid contexts still abort before cutover with unchanged source checksums.
    - **EXPECTED OUTCOME**: The previously failing bug-condition counterexample passes after the fix, and unsafe contexts continue to fail closed.
    - _Requirements: 2.1, 2.3, 2.6_

  - [ ] 3.12 Verify preservation properties and regression suites still pass
    - **Property 2: Preservation** - Existing Data, Behavior, and Test Isolation
    - **IMPORTANT**: Re-run the same observation-first property tests from task 2; do not replace them with tests derived only from the new implementation.
    - Compare pre-migration and PostgreSQL results for row identity/value hashes, relationships, credentials, authorization, storage references, and representative non-real-time routes/workflows.
    - Confirm PHPUnit still resolves exclusively to SQLite `:memory:` and cannot alter PostgreSQL counts or hashes.
    - Confirm database migration introduced no changes to Reverb broadcaster selection, channels, payloads, authorization, queue behavior, or client-visible behavior; do not implement Reverb/Nginx/TLS changes in this task.
    - Monitor post-cutover database/application errors and identity allocation for the approved observation window while retaining rollback/delta-protection artifacts.
    - **EXPECTED OUTCOME**: All preservation and existing regression tests pass with no PostgreSQL data drift or out-of-scope behavior change.
    - _Requirements: 3.1, 3.3, 3.4, 3.5, 3.6_

  - [ ] 3.13 Document the completed migration and operator procedures
    - Document ordered preflight, quiescence, backup/restore proof, inventory, schema creation, transfer, normalization, relationship handling, sequence repair, verification, cutover, smoke-check, and rollback procedures.
    - Document production warnings, explicit approval points, stop gates, post-write delta protection, artifact retention, and the prohibition on seeders/destructive migration commands.
    - List every PostgreSQL environment variable and `DB_URL` precedence rule using placeholders only; explain configuration-cache rebuild and test-database isolation.
    - Record the actual migration outcome through redacted manifest references, checksums, counts/hashes, approvals, and exceptions without embedding passwords, keys, tokens, Reverb secrets, production credentials, or sensitive row data.
    - State clearly that Reverb/Nginx/WebSocket/TLS implementation remains out of scope for this increment and was checked only for regression.
    - _Bug_Condition: Operators need a complete reproducible process so future migrations do not recreate an unready or unproven PostgreSQL target._
    - _Expected_Behavior: Guidance identifies every prerequisite, migration phase, validation predicate, cutover gate, and rollback rule needed to maintain `expectedBehavior(result)`._
    - _Preservation: Documentation contains no secrets and does not direct operators to modify unrelated application/Reverb behavior._
    - _Requirements: 2.1, 2.3, 2.6, 3.3, 3.4, 3.6_

- [ ] 4. Checkpoint - Confirm migration acceptance and all tests pass
  - Confirm tasks 1 through 3.13 have evidence and no unresolved stop-gate failure, waived correctness predicate, unclassified source object, or undocumented exception.
  - Confirm the bug-condition property, preservation property, existing unit/feature/integration suites, active PostgreSQL smoke checks, and restored-backup verification all pass.
  - Confirm the final transfer manifest is complete and redacted, PostgreSQL is the intended active runtime, SQLite and PostgreSQL rollback artifacts are protected, and the observation/ownership plan is active.
  - Confirm no Reverb, Nginx, WebSocket, or TLS implementation was introduced by this database-only increment and ask the user if any migration evidence or approval remains unclear.

## Notes

- The dependency graph adds ordering metadata only; it does not reduce, expand, or otherwise change any implementation task, validation requirement, migration scope, preservation requirement, or safety gate below.
- Tasks 1 and 2 are independent baselines, but both must complete before task 3 or any 3.x migration work begins.
- Tasks 3.6, 3.7, and 3.10 remain production-impacting operations that require the explicit approvals stated in their task text in addition to completion of their graph dependencies.
- Every `STOP GATE`, `FINAL STOP GATE BEFORE CUTOVER`, backup/restoration requirement, maintenance-window condition, source-preservation rule, and prohibition on destructive commands remains mandatory.
- Reverb, Nginx, WebSocket, and TLS implementation remains out of scope; Reverb behavior is covered only by preservation and regression verification.
- This document defines work to be executed later. Formatting and validating this plan does not authorize or execute any task.