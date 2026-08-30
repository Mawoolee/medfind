# Implementation Plan: Pharmacy Medicine Batch Stock Management

## Overview

Implement the redesign incrementally in PHP 8.2+/Laravel 12. Preserve `InventoryItem` as the compatibility aggregate, make `InventoryBatch` the authoritative stock source, and route every quantity change through transactional services. Each prompt below builds on completed dependencies and ends in integrated behavior; no required implementation or test task is optional.

## Tasks

- [ ] 1. Establish test and preservation guardrails
  - [x] 1.1 Add the pinned property-testing dependency and shared test support
    - Add `giorgiosironi/eris` version `1.1.0` to `require-dev` without changing unrelated dependency versions.
    - Create shared generators/reference helpers under `tests/Support` for dates, batch vectors, identities, and pharmacy ownership graphs.
    - Preserve PHPUnit's SQLite `:memory:` default and configure every property to run at least 100 iterations.
    - Dependencies: none.
    - _Requirements: 13.4, 13.7, 13.8_
  - [x] 1.2 Adapt existing regression tests to protect approved uncommitted behavior
    - Update baseline tests before production behavior changes so Brand Name, Lot Number, editable supplier text, scoped autofill, old input, consumer deployment behavior, and unrelated Railway/PostgreSQL/Reverb fixes remain protected.
    - Do not revert or replace unrelated working-tree hunks.
    - Dependencies: none.
    - _Requirements: 13.1, 13.2, 13.3_

- [ ] 2. Add the batch schema and data migration
  - [x] 2.1 Create the cross-database batch and movement schema migration
    - Add `medicines.cold_chain_required`, `inventory_batches`, `stock_movements`, and nullable operation correlation fields.
    - Add foreign keys and indexes from the design while retaining every legacy aggregate column.
    - Use Laravel Schema APIs supported by SQLite and PostgreSQL.
    - Dependencies: none.
    - _Requirements: 2.2, 8.11, 8.13, 12.4_
  - [x] 2.2 Implement the deterministic legacy inventory backfill
    - Add migration-safe PHP backfill logic using source markers, deterministic legacy batch numbers/keys, exact metadata copying, verification, and rollback-on-failure.
    - Make repeated or partially completed execution idempotent for batches and movements.
    - Dependencies: 2.1, 3.2.
    - _Requirements: 8.1-8.12_
  - [x] 2.3 Add InventoryBatch and StockMovement models and extend existing relationships
    - Define fillable/cast fields, immutable movement behavior, FEFO/availability scopes, and `Medicine`, `InventoryItem`, `Supplier`, and `User` relationships.
    - Keep existing aggregate audit/domain relationships compatible.
    - Dependencies: 2.1.
    - _Requirements: 3.10, 4.7, 5.4, 9.9-9.11_
  - [x] 2.4 Create batch-aware factories
    - Add `InventoryBatchFactory` and `StockMovementFactory`; update `InventoryItemFactory` and `MedicineFactory` to produce synchronized aggregate/batch states and cold-chain variants.
    - Dependencies: 2.3.
    - _Requirements: 9.13_
  - [x] 2.5 Convert inventory seeders to aggregate-plus-batch data
    - Update DatabaseSeeder, DemoSeeder, InventoryTestSeeder, and SuppliersAndReceivingSeeder to create distinct batches without duplicate identities.
    - Preserve current demo identities and supplier behavior where practical.
    - Dependencies: 2.4, 3.6.
    - _Requirements: 3.11, 9.13_
  - [x] 2.6 Add focused SQLite migration and backfill feature tests
    - Cover null fields, zero and expired stock, Unicode, sparse IDs, partial backfill, rerun idempotence, exact copied values, foreign keys, and injected verification rollback.
    - Dependencies: 2.2, 2.3.
    - _Requirements: 8.1-8.13, 13.5, 13.7_
  - [x] 2.7 Extend canonical transfer and preservation utilities
    - Add new fields/tables to migration inventory, normalization, dependency ordering, source preparation, target transfer, canonical hashing, and preservation fixtures.
    - Keep all source-read-only, test-isolation, and secret-redaction protections.
    - Dependencies: 2.1, 2.2.
    - _Requirements: 8.14, 13.3, 13.7_
  - [x] 2.8 Extend the guarded PostgreSQL migration rehearsal
    - Rehearse fresh migration, legacy backfill, transfer, sequence repair, dump/restore, idempotence, and rollback in the existing proven-disposable environment.
    - Do not weaken any opt-in or loopback/disposable-cluster guard.
    - Dependencies: 2.6, 2.7.
    - _Requirements: 8.8, 8.13, 8.14, 13.6_

- [ ] 3. Implement the stock domain services and properties
  - [x] 3.1 Create stock operation DTOs, results, context, and typed exceptions
    - Add receipt, metadata, operation context/result objects and domain exceptions for duplicate identity, insufficient stock, cold-chain requirement, untraceable increase, ownership, and historical deletion.
    - Dependencies: none.
    - _Requirements: 3.1-3.5, 5.7, 6.6, 7.2, 10.3-10.5, 11.7_
  - [x] 3.2 Implement deterministic BatchIdentity normalization
    - Implement Unicode-aware trim, whitespace collapse, lowercase normalization, unambiguous batch/lot encoding, and deterministic legacy keys.
    - Dependencies: none.
    - _Requirements: 3.12, 5.9, 8.6, 8.8_
  - [x] 3.3 Implement MedicineMasterService
    - Create/update medicine master fields and pharmacy par level while idempotently ensuring one aggregate and rejecting stock fields.
    - Preserve `medicine_name` compatibility and all existing prescription classification behavior.
    - Dependencies: 2.3, 3.1.
    - _Requirements: 1.1, 1.5, 2.1-2.7_
  - [x] 3.4 Implement InventoryAggregateQuery and AggregateSynchronizer
    - Add reusable available/physical/price/expiry projections, aggregate status mapping, FEFO ordering, locked synchronization, and chunk reconciliation.
    - Ensure public reads remain correct across date-boundary expiry before cache refresh.
    - Dependencies: 2.3.
    - _Requirements: 4.1-4.10, 9.1-9.5, 9.7, 9.14_
  - [x] 3.5 Implement StockOperationRecorder
    - Create immutable per-batch movements, one aggregate audit when available quantity changes, operation correlation, domain references, and post-commit broadcast coordination.
    - Dependencies: 2.3, 3.1, 3.4.
    - _Requirements: 6.7, 9.12, 12.1-12.7_
  - [ ] 3.6 Implement InventoryBatchService
    - Receive distinct batches, resolve supplier text, enforce cold chain and unique identity, update safe metadata, correct batch quantities, record changes, and synchronize aggregates transactionally.
    - Dependencies: 3.2, 3.4, 3.5.
    - _Requirements: 3.1-3.13, 5.6-5.9, 12.1-12.5_
  - [ ] 3.7 Implement FEFOAllocator
    - Lock aggregate/batches, exclude expired stock, sort deterministically, allocate across batches, support explicit batch decreases, reject insufficiency atomically, record movements, and synchronize.
    - Dependencies: 3.4, 3.5.
    - _Requirements: 6.1-6.8_
  - [ ] 3.8 Implement StockAdjustmentService
    - Convert lower aggregate targets to FEFO deltas, require traceable adjustment batches for increases, validate complete multi-item batches before mutation, and support cycle/controlled references.
    - Dependencies: 3.6, 3.7.
    - _Requirements: 7.1-7.10_
  - [x] 3.9 Add and schedule the aggregate reconciliation command
    - Implement idempotent chunked reconciliation after date boundaries and register the schedule without changing unrelated deployment configuration.
    - Dependencies: 3.4.
    - _Requirements: 4.1-4.10, 9.4_
  - [x] 3.10 Write Property 1 test: Medicine edits preserve batch stock
    - **Property 1: Medicine edits preserve batch stock**
    - Use a dedicated property test file and the required feature/property annotation.
    - Dependencies: 1.1, 3.3.
    - **Validates: Requirements 1.1, 2.5**
  - [x] 3.11 Write Property 2 test: Medicine master round trip
    - **Property 2: Medicine master round trip**
    - Dependencies: 1.1, 3.3.
    - **Validates: Requirements 2.1, 2.2**
  - [x] 3.12 Write Property 3 test: Aggregate creation is idempotent
    - **Property 3: Aggregate creation is idempotent**
    - Dependencies: 1.1, 3.3.
    - **Validates: Requirements 2.4**
  - [ ] 3.13 Write Property 4 test: Receipt preserves submitted batch data
    - **Property 4: Receipt preserves submitted batch data**
    - Dependencies: 1.1, 3.6.
    - **Validates: Requirements 3.5, 3.10**
  - [ ] 3.14 Write Property 5 test: Supplier normalization is confluent
    - **Property 5: Supplier normalization is confluent**
    - Dependencies: 1.1, 3.6.
    - **Validates: Requirements 3.7, 3.8**
  - [ ] 3.15 Write Property 6 test: Product cold-chain requirement implies batch cold-chain
    - **Property 6: Product cold-chain requirement implies batch cold-chain**
    - Dependencies: 1.1, 3.6.
    - **Validates: Requirements 3.9**
  - [ ] 3.16 Write Property 7 test: Batch identity is unique and non-destructive
    - **Property 7: Batch identity is unique and non-destructive**
    - Dependencies: 1.1, 3.6.
    - **Validates: Requirements 3.11, 3.12, 5.9**
  - [ ] 3.17 Write Property 8 test: Aggregate quantity and status projection
    - **Property 8: Aggregate quantity and status projection**
    - Dependencies: 1.1, 3.4.
    - **Validates: Requirements 4.2, 4.6, 4.7, 4.8**
  - [ ] 3.18 Write Property 9 test: Representative price is deterministic
    - **Property 9: Representative price is deterministic**
    - Dependencies: 1.1, 3.4.
    - **Validates: Requirements 4.3, 4.4**
  - [ ] 3.19 Write Property 10 test: Nearest valid expiry follows FEFO
    - **Property 10: Nearest valid expiry follows FEFO**
    - Dependencies: 1.1, 3.4.
    - **Validates: Requirements 4.9, 5.4**
  - [ ] 3.20 Write Property 11 test: Batch metadata edits preserve immutable history
    - **Property 11: Batch metadata edits preserve immutable history**
    - Dependencies: 1.1, 3.6.
    - **Validates: Requirements 5.6**
  - [ ] 3.21 Write Property 12 test: FEFO allocation is exact and ordered
    - **Property 12: FEFO allocation is exact and ordered**
    - Dependencies: 1.1, 3.7.
    - **Validates: Requirements 6.2, 6.3, 6.4, 6.5, 7.1**
  - [ ] 3.22 Write Property 13 test: Insufficient decreases are atomic
    - **Property 13: Insufficient decreases are atomic**
    - Dependencies: 1.1, 3.7.
    - **Validates: Requirements 6.6**
  - [ ] 3.23 Write Property 14 test: Stock ledger conserves every successful operation
    - **Property 14: Stock ledger conserves every successful operation**
    - Dependencies: 1.1, 3.5, 3.7.
    - **Validates: Requirements 6.7, 12.2, 12.3, 12.4**
  - [ ] 3.24 Write Property 15 test: Bulk adjustment validation is atomic
    - **Property 15: Bulk adjustment validation is atomic**
    - Dependencies: 1.1, 3.8, 4.5.
    - **Validates: Requirements 7.5**
  - [ ] 3.25 Write Property 16 test: Aggregate increases create only traceable stock
    - **Property 16: Aggregate increases create only traceable stock**
    - Dependencies: 1.1, 3.8.
    - **Validates: Requirements 7.2, 7.7, 7.9**
  - [x] 3.26 Write Property 17 test: Legacy backfill is preserving and idempotent
    - **Property 17: Legacy backfill is preserving and idempotent**
    - Dependencies: 1.1, 2.2.
    - **Validates: Requirements 8.1-8.6, 8.9, 8.10**
  - [ ] 3.27 Write Property 18 test: Consumer stock equals available batch stock
    - **Property 18: Consumer stock equals available batch stock**
    - Dependencies: 1.1, 7.1.
    - **Validates: Requirements 9.1, 9.3**
  - [ ] 3.28 Write Property 19 test: Analysis value uses aggregate availability
    - **Property 19: Analysis value uses aggregate availability**
    - Dependencies: 1.1, 7.3.
    - **Validates: Requirements 9.7**
  - [ ] 3.29 Write Property 20 test: Pharmacy isolation
    - **Property 20: Pharmacy isolation**
    - Dependencies: 1.1, 4.4.
    - **Validates: Requirements 10.1, 10.2**

- [ ] 4. Rework inventory controllers and routes
  - [x] 4.1 Create pharmacy-scoped Form Requests and record resolvers
    - Validate medicine, nested receipts, batch metadata/corrections, aggregate adjustments, and ownership without leaking foreign records.
    - Dependencies: 2.3, 3.1.
    - _Requirements: 2.3, 3.1-3.5, 5.7, 7.2, 10.1-10.6, 11.2-11.4_
  - [ ] 4.2 Refactor InventoryController for aggregate medicine management
    - Keep named list/create/store/edit/update/delete/export routes; separate medicine/par behavior, aggregate queries, safe deletion, filters, and aggregate export.
    - Dependencies: 3.3, 3.4, 4.1.
    - _Requirements: 1.1, 1.3, 1.5, 1.6, 5.1-5.3, 9.5_
  - [ ] 4.3 Refactor ReceivingController as the official Add Stock workflow
    - Select scoped existing aggregates, preserve multi-row requests, receive each row through InventoryBatchService, and keep route names compatible.
    - Dependencies: 3.6, 4.1.
    - _Requirements: 1.2, 1.4, 3.1-3.13_
  - [ ] 4.4 Add InventoryBatchController and order new routes safely
    - Implement all-batches, aggregate batch list, edit, correction, and batch export endpoints; declare static routes before parameter routes.
    - Dependencies: 3.4, 3.6, 3.8, 4.1.
    - _Requirements: 5.4-5.9, 9.6, 10.1-10.5_
  - [ ] 4.5 Convert the legacy bulk-update endpoint to transactional adjustments
    - Remove direct aggregate assignment; validate all rows first, send decreases through StockAdjustmentService, reject untraceable increases, and route representative-price edits to the representative batch.
    - Dependencies: 3.8, 4.1.
    - _Requirements: 7.1-7.5, 7.10_
  - [ ] 4.6 Add route and medicine-controller feature tests
    - Verify named route compatibility, separated responsibilities, validation guidance, aggregate idempotence, safe deletion, filters, and cross-pharmacy 404 behavior.
    - Dependencies: 4.2, 4.4.
    - _Requirements: 1.1-1.6, 2.4-2.6, 10.1-10.5_
  - [ ] 4.7 Add receiving feature tests
    - Verify multi-row receipts, supplier text matching, cold-chain enforcement, duplicate rejection, old input, and transactional rollback.
    - Dependencies: 4.3.
    - _Requirements: 3.1-3.13, 11.3_
  - [ ] 4.8 Add batch endpoint feature tests
    - Verify FEFO order, metadata edit invariants, corrections, export, responsive data payloads, and foreign aggregate/batch denial.
    - Dependencies: 4.4.
    - _Requirements: 5.4-5.9, 9.6, 10.1-10.5_
  - [ ] 4.9 Add bulk-update compatibility feature tests
    - Verify decreases, increase rejection, all-or-nothing validation, representative-price behavior, audit/movement creation, and synchronized events.
    - Dependencies: 4.5, 6.4.
    - _Requirements: 7.1-7.5, 9.12, 12.2-12.5_

- [ ] 5. Build the separated responsive pharmacy UI
  - [ ] 5.1 Convert the existing inventory create view to Add Medicine
    - Retain Generic/Brand/Dosage/Category/Manufacturer fields, product cold-chain requirement, par level, validation styling, old input, and safe scoped selection behavior; remove batch stock inputs.
    - Dependencies: 4.2.
    - _Requirements: 1.1, 2.1-2.7, 11.2, 13.2_
  - [ ] 5.2 Redesign receiving_create as Add Stock/Receive Delivery
    - Use existing aggregate selection and repeatable rows with batch, lot, quantity, price, supplier text, expiry, cold chain, received date, and reference.
    - Preserve every row after validation and retain barcode behavior only when it resolves to an existing scoped medicine.
    - Dependencies: 4.3.
    - _Requirements: 1.2, 3.1-3.13, 11.3, 13.2_
  - [ ] 5.3 Redesign the main inventory view around aggregates
    - Render one medicine row with available total, status, representative price, nearest expiry, par, and Add Stock/View Batches/Edit Medicine-Par actions.
    - Remove direct quantity inputs while preserving filters, pagination, and CSV action.
    - Dependencies: 4.2, 4.4.
    - _Requirements: 5.1-5.3, 11.5-11.7_
  - [ ] 5.4 Create all-batch, aggregate-batch, and batch-edit views
    - Render FEFO order and all traceability fields, separate available/depleted/expired states, require reasons for corrections, and preserve old input.
    - Dependencies: 4.4.
    - _Requirements: 5.4-5.10, 11.4-11.6_
  - [ ] 5.5 Add the four approved dashboard actions and navigation links
    - Add Manage Inventory, Add New Medicine, Add Stock/Receive Delivery, and View Stock Batches without removing unrelated dashboard content.
    - Dependencies: 4.2, 4.4.
    - _Requirements: 11.1, 13.1, 13.3_
  - [ ] 5.6 Add one-shot Playwright responsive workflow tests
    - Test 320px, tablet, and desktop overflow/action visibility, repeatable receiving rows, old-input recovery, and batch readability.
    - Dependencies: 5.1, 5.2, 5.3, 5.4, 5.5.
    - _Requirements: 5.10, 11.2-11.6_

- [ ] 6. Integrate all stock-mutating operational workflows
  - [ ] 6.1 Refactor controlled-substance operations to FEFO and traceable adjustments
    - Route dispense, wastage, transfer, lower adjustment, and higher adjustment through domain services while preserving aggregate log relationships.
    - Dependencies: 3.7, 3.8.
    - _Requirements: 6.9, 7.6, 7.7, 9.10, 12.6_
  - [ ] 6.2 Refactor cycle counts to reconcile batches
    - Keep aggregate expected quantities; apply lower variances by FEFO and higher variances through referenced adjustment batches in the same transaction.
    - Dependencies: 3.8.
    - _Requirements: 7.8, 7.9, 9.8, 12.7_
  - [ ] 6.3 Refactor returns and recalls for aggregate FEFO or selected batches
    - Preserve aggregate-linked historical records; support optional scoped batch selection and record allocations transactionally.
    - Dependencies: 3.7.
    - _Requirements: 6.10, 6.11, 9.9, 12.7_
  - [ ] 6.4 Integrate audits and post-commit inventory broadcasts
    - Correlate operations, keep historical audit views readable, remove duplicate event dispatches, and emit one synchronized post-commit aggregate payload.
    - Dependencies: 3.5, 3.6, 3.7, 3.8.
    - _Requirements: 9.11, 9.12, 12.1-12.7_
  - [ ] 6.5 Add controlled-substance integration tests
    - Cover multi-batch FEFO dispensing/wastage/transfer, both adjustment directions, insufficiency rollback, log correlation, and pharmacy scoping.
    - Dependencies: 6.1, 6.4.
    - _Requirements: 6.9, 7.6, 7.7, 10.6, 12.6_
  - [ ] 6.6 Add cycle-count integration tests
    - Cover expected available quantity, lower and higher variances, expired stock, operation references, rollback, and pharmacy scoping.
    - Dependencies: 6.2, 6.4.
    - _Requirements: 7.8, 7.9, 9.8, 10.6, 12.7_
  - [ ] 6.7 Add return/recall integration tests
    - Cover aggregate FEFO, explicit batch targeting, insufficiency, historical relations, operation references, rollback, and pharmacy scoping.
    - Dependencies: 6.3, 6.4.
    - _Requirements: 6.10, 6.11, 9.9, 10.6, 12.7_
  - [ ] 6.8 Add audit and broadcast integration tests
    - Verify one audit per aggregate change, one movement per affected batch, shared operation ID, no event before rollback, and one correct post-commit payload.
    - Dependencies: 6.4.
    - _Requirements: 9.11, 9.12, 12.1-12.5_

- [ ] 7. Update all read-side integrations
  - [ ] 7.1 Update consumer map, search, and pharmacy details
    - Eager-load aggregate medicine data with batch-derived available stock and representative price; exclude expired quantities while preserving search logging and pagination.
    - Dependencies: 3.4.
    - _Requirements: 9.1-9.3_
  - [ ] 7.2 Update pharmacy dashboard and admin inventory queries/views
    - Derive counts, low-stock status, expiring/expired alerts, per-pharmacy summaries, and filters from aggregate/batch projections without Blade-side queries.
    - Dependencies: 3.4, 3.9.
    - _Requirements: 4.6-4.10, 9.4, 9.14_
  - [ ] 7.3 Update analysis and aggregate/batch CSV exports
    - Calculate ABC/VED values from available stock and representative price; keep aggregate export backward-readable and add traceability batch export.
    - Dependencies: 3.4, 4.2, 4.4.
    - _Requirements: 9.5-9.7_
  - [ ] 7.4 Add consumer compatibility feature tests
    - Verify map payloads, search results, pharmacy details, zero-stock labels, expired exclusion, representative price, pagination, and search logs.
    - Dependencies: 7.1.
    - _Requirements: 9.1-9.3_
  - [ ] 7.5 Add pharmacy/admin dashboard feature tests
    - Seed valid, expiring, expired, depleted, and no-expiry batches and assert every count, alert, filter, summary, and action.
    - Dependencies: 5.5, 7.2.
    - _Requirements: 4.6-4.10, 9.4, 9.14, 11.1_
  - [ ] 7.6 Add analysis and export feature tests
    - Parse both CSV formats, assert one aggregate row and batch traceability rows, and verify ABC/VED inputs use available quantity.
    - Dependencies: 7.3.
    - _Requirements: 9.5-9.7_
  - [ ] 7.7 Add factory and seeder integration tests
    - Run each inventory-related factory/seeder path and assert unique aggregates, valid distinct batches, synchronized totals, supplier links, and no duplicate identity failures.
    - Dependencies: 2.5, 3.4.
    - _Requirements: 9.13_

- [ ] 8. Close compatibility gaps and architecture regressions
  - [ ] 8.1 Update existing inventory and deployment regression suites
    - Rewrite old combined-form expectations around separated workflows while retaining assertions for Brand Name, Lot Number, supplier free text, scoped data, validation recovery, consumer deployment, and unrelated migration behavior.
    - Dependencies: 4.6, 4.7, 4.8, 7.4.
    - _Requirements: 1.1-1.6, 13.1-13.3_
  - [ ] 8.2 Add an architecture guard against direct aggregate stock mutation
    - Add a focused test that fails when controllers assign, increment, decrement, or bulk-update `stockQuantity` outside approved synchronizer/backfill code.
    - Dependencies: 4.5, 6.1, 6.2, 6.3.
    - _Requirements: 7.10_
  - [ ] 8.3 Add end-to-end transactional failure tests
    - Inject movement, audit, domain-log, synchronization, and duplicate-constraint failures and assert complete rollback across batches, aggregates, logs, and events.
    - Dependencies: 4.9, 6.5, 6.6, 6.7, 6.8.
    - _Requirements: 3.12, 6.6, 12.5-12.7_

- [ ] 9. Checkpoint - Ensure focused tests pass
  - Ensure migration, model, service, property, controller, UI, and integration tests pass; ask the user if questions arise.

- [ ] 10. Final checkpoint - Ensure all validation passes
  - Run the full SQLite `php artisan test` suite, guarded PostgreSQL rehearsal when the disposable environment is enabled, `vendor/bin/pint --test`, and `npm run build`.
  - Review `git status --short` and the complete diff to confirm unrelated uncommitted Railway, PostgreSQL, Reverb, JavaScript, layout, and prepared form changes remain intact.
  - Ensure all tests pass, ask the user if questions arise.

- [x] 11. Implement the approved Basic Record Sale workflow
  - [x] 11.1 Add and verify Basic Record Sale stock deduction
    - Add the fifth dashboard inventory action, named pharmacy sale routes, scoped request/controller/service, and responsive repeatable-row Blade form.
    - Process all rows in one transaction through `FEFOAllocator`, with duplicate prevention, pharmacy isolation, actionable insufficiency, one operation ID/reference/actor, synchronized aggregates, and no POS scope or manual batch input.
    - Add focused service and feature coverage for routes/UI, validation recovery, multi-line FEFO allocation, ledger correlation, ownership, and rollback.
    - Dependencies: existing FEFOAllocator and stock ledger implementation; do not execute any other incomplete task.
    - _Requirements: 14.1-14.10_
  - [x] 11.2 Write Property 21 test: Basic sales are atomic FEFO operations
    - **Property 21: Basic sales are atomic FEFO operations**
    - Exercise generated multi-item quantities and verify exact deductions plus shared operation/reference/actor metadata.
    - Dependencies: 11.1.
    - **Validates: Requirements 14.3-14.8**
  - [x] 11.3 Make every Record Sale Medicine selector searchable
    - Progressively enhance the named scoped aggregate select with a dependency-free accessible combobox that searches generic name, brand, dosage, and available-stock text while submitting only an explicit option ID.
    - Support pointer and keyboard selection, invalid-edit clearing, click-away dismissal, no-results feedback, unique dynamic-row IDs, restored validation state, removal/renumbering, and focused rendered-contract coverage.
    - Dependencies: 11.1; do not execute any other incomplete task.
    - _Requirements: 14.2, 14.3, 14.9, 14.11_
  - [x] 11.4 Prioritize Record Sale in the dashboard action grid
    - Order all eight cards with Record Sale first beside Manage Inventory and use four XL columns while preserving one phone column, two medium columns, and every existing card action.
    - Add focused dashboard assertions for the complete order and responsive grid classes.
    - Dependencies: 11.1; do not execute any other incomplete task.
    - _Requirements: 14.1_
  - [x] 11.5 Standardize dashboard action buttons and statistic colors
    - Apply one full-width, centered, wrapping-safe Admin-style size and appearance contract to all eight action links without changing their colors, content, routes, order, badge, or responsive grid.
    - Replace dynamic statistic color interpolation with explicit matching value/icon classes and add focused dashboard contract coverage.
    - Dependencies: 11.4; do not execute any other incomplete task.
    - _Requirements: 14.1, 14.12_

- [x] 12. Unify medicine category catalogs and inventory filtering
  - Introduce one canonical category source for Add/Edit Medicine and Manage Inventory; merge only the authenticated pharmacy's nonblank custom values, deduplicate case-insensitively, preserve custom selections, and use portable case-insensitive filtering.
  - Add focused unit and feature coverage for canonical rendering, tenant isolation, deduplication, legacy casing, custom filtering, and Add/Edit selection.
  - Dependencies: existing InventoryController medicine management; do not execute any other incomplete task.
  - _Requirements: 5.11-5.13_

- [x] 13. Remove the cold-chain filter from Manage Inventory
  - Remove only the visible list control and its legacy query behavior while retaining search, category, stock, sort, and all cold-chain domain fields and workflows.
  - Add focused feature coverage proving the control is absent and old bookmarked query parameters no longer narrow inventory.
  - Dependencies: existing aggregate inventory list; do not execute any other incomplete task.
  - _Requirements: 5.1, 5.10, 13.1_

- [x] 14. Remove aggregate batch counts from Manage Inventory
  - Remove the Batches count column and index count query while retaining View Batches actions, batch traceability workflows, and aggregate CSV Batch Count output.
  - Add focused inventory feature coverage for the main page and export contract.
  - Dependencies: existing aggregate inventory list and batch pages; do not execute any other incomplete task.
  - _Requirements: 5.2, 5.3, 9.5, 13.1_

- [x] 15. Standardize standalone pharmacy Back navigation
  - Add one reusable accessible MedFind-purple left-arrow and `Back` Blade component, then use deterministic named parent routes on every standalone pharmacy page while excluding the Pharmacy Dashboard root.
  - Preserve separate form `Cancel` actions and add static inventory, component-contract, and representative rendered-route coverage.
  - Dependencies: existing pharmacy pages and named routes; do not execute any other incomplete task.
  - _Requirements: 11.8, 13.1_

## Notes

- No task is optional; every implementation and test item is required for this redesign.
- Each property has one dedicated Eris test with at least 100 iterations and the exact design-property annotation.
- Existing route names are retained where useful, but Add Medicine no longer creates stock and receiving is the official stock-entry workflow.
- Legacy aggregate columns remain during the compatibility period; only domain services and migration/reconciliation code may write compatibility projections.
- Tasks must preserve unrelated uncommitted work and must never reset, checkout, or overwrite the working tree wholesale.

## Task Dependency Graph

```json
{
  "waves": [
    { "id": 0, "tasks": ["1.1", "1.2", "2.1", "3.1", "3.2"] },
    { "id": 1, "tasks": ["2.2", "2.3"] },
    { "id": 2, "tasks": ["2.4", "2.6", "2.7", "3.3", "3.4", "4.1"] },
    { "id": 3, "tasks": ["2.8", "3.5", "3.9", "3.10", "3.11", "3.12"] },
    { "id": 4, "tasks": ["3.6", "3.7", "3.17", "3.18", "3.19", "3.26"] },
    { "id": 5, "tasks": ["2.5", "3.8", "3.13", "3.14", "3.15", "3.16", "3.20", "3.21", "3.22", "3.23"] },
    { "id": 6, "tasks": ["3.25", "4.2", "4.3", "4.4", "4.5", "6.1", "6.2", "6.3", "7.1"] },
    { "id": 7, "tasks": ["3.24", "3.29", "4.6", "4.7", "4.8", "5.1", "5.2", "5.3", "5.4", "5.5", "6.4", "7.2", "7.3", "7.7"] },
    { "id": 8, "tasks": ["3.27", "3.28", "4.9", "5.6", "6.5", "6.6", "6.7", "6.8", "7.4", "7.5", "7.6"] },
    { "id": 9, "tasks": ["8.1", "8.2", "8.3"] }
  ]
}
```
