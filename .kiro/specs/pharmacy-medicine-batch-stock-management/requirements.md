# Requirements Document

## Introduction

MedFind currently stores pharmacy-level medicine totals and batch attributes on the same `inventory_items` row. The approved redesign separates medicine master maintenance from stock receiving, preserves each delivery as a distinct batch, and retains `InventoryItem` as the unique pharmacy-and-medicine aggregate used by existing integrations. Batch records become the stock source of truth, while aggregate fields remain synchronized compatibility projections for existing consumer, pharmacy, reporting, audit, and broadcast behavior.

## Glossary

- **MedFind_System**: The Laravel application that serves pharmacy operators, consumers, and administrators.
- **Pharmacy_Operator**: An authenticated user assigned to a pharmacy and authorized by the pharmacy route middleware.
- **Medicine_Master**: A global medicine record containing generic name, brand name, dosage, category, manufacturer, prescription classification, and product cold-chain requirement.
- **Medicine_Master_Service**: The MedFind component that creates and updates Medicine_Master records.
- **Pharmacy_Inventory_Aggregate**: The unique `InventoryItem` record for one pharmacy and one Medicine_Master, containing pharmacy-level par level and synchronized compatibility projections.
- **Inventory_Batch**: A child stock record for one Pharmacy_Inventory_Aggregate and one received batch or stock adjustment.
- **Inventory_Batch_Service**: The MedFind component that validates, creates, and safely edits Inventory_Batch records.
- **Batch_Identity_Key**: A case-folded and whitespace-normalized key derived from batch number and lot number for one Pharmacy_Inventory_Aggregate.
- **Available_Stock**: The sum of positive current quantities from non-expired Inventory_Batch records.
- **Physical_Stock**: The sum of positive current quantities from all Inventory_Batch records, including expired Inventory_Batch records.
- **Representative_Price**: The price projected onto a Pharmacy_Inventory_Aggregate from the most recently received non-expired Inventory_Batch with positive current quantity, using Inventory_Batch identifier as the final tie-breaker.
- **FEFO_Allocator**: The MedFind component that allocates stock decreases to non-expired Inventory_Batch records by earliest expiry date first, placing Inventory_Batch records without expiry dates last.
- **Aggregate_Synchronizer**: The MedFind component that recalculates Pharmacy_Inventory_Aggregate compatibility fields from Inventory_Batch records.
- **Stock_Adjustment**: An audited operation that changes an aggregate target quantity or an Inventory_Batch current quantity.
- **Receiving_Workflow**: The official Add Stock / Receive Delivery form and controller flow for creating Inventory_Batch records for existing Medicine_Master records.
- **Supplier_Name**: Trimmed free-text supplier identity captured on an Inventory_Batch, with optional linkage to an existing supplier record for compatibility.
- **Received_Reference**: A purchase order, delivery reference, cycle-count reference, controlled-substance reference, or other auditable source identifier.
- **Stock_Movement**: An immutable record of a quantity change applied to one Inventory_Batch as part of a receipt, decrease, correction, return, recall, or migration.
- **Inventory_Audit**: The existing aggregate-level before-and-after stock audit record associated with a Pharmacy_Inventory_Aggregate.
- **Legacy_Inventory_Row**: An InventoryItem record created before Inventory_Batch storage exists and containing stock, batch, lot, expiry, cold-chain, price, or supplier data directly.
- **Consumer_Inventory_View**: Consumer map, medicine search, and pharmacy detail output derived from approved pharmacies.
- **FEFO_Order**: Ascending non-null expiry date, followed by no-expiry Inventory_Batch records, with received date and Inventory_Batch identifier as deterministic tie-breakers.
- **Pharmacy inventory page**: The pharmacy-facing aggregate list for medicine stock.
- **Batch detail page**: The pharmacy-facing list and edit surface for Inventory_Batch records.
- **Controlled_Substance workflow**: The existing controlled-substance receipt, dispense, wastage, transfer, and adjustment flow.
- **Return-and-recall workflow**: The existing pharmacy flow for supplier returns and medicine recalls.
- **Stock_Adjustment service**: The MedFind component that reconciles requested aggregate targets with Inventory_Batch quantities.
- **Cycle-count workflow**: The existing pharmacy stock-count and discrepancy reconciliation flow.
- **Migration**: The batch schema and Legacy_Inventory_Row backfill operation introduced by this feature.
- **PostgreSQL transfer and preservation utilities**: Existing guarded code and tests that transfer and verify canonical MedFind data between SQLite and PostgreSQL.
- **Pharmacy dashboard**: The authenticated pharmacy overview and action page.
- **CSV export**: Inventory data streamed as comma-separated values for a pharmacy.
- **Inventory analysis workflow**: The existing ABC, VED, and ABC-VED inventory analysis flow.
- **Admin inventory view**: The administrator-facing cross-pharmacy aggregate inventory page.
- **Inventory_Audit workflow**: The existing pharmacy audit-log query and display flow.
- **Implementation**: The code and database changes produced from this specification.
- **Automated test suite**: The PHPUnit, Eris, Playwright, migration rehearsal, formatting, and build checks used to verify the Implementation.

## Requirements

### Requirement 1: Separate Medicine and Stock Workflows

**User Story:** As a Pharmacy_Operator, I want separate medicine-master and stock-receiving workflows, so that product identity changes cannot overwrite delivery-specific stock data.

#### Acceptance Criteria

1. THE MedFind_System SHALL provide an Add Medicine workflow that edits only Medicine_Master fields and pharmacy-level par level.
2. THE MedFind_System SHALL provide the Receiving_Workflow separately from the Add Medicine workflow.
3. WHEN a Pharmacy_Operator opens the existing `pharmacy.inventory.create` route, THE MedFind_System SHALL display the Add Medicine workflow.
4. WHEN a Pharmacy_Operator opens the existing `pharmacy.receiving.create` route, THE MedFind_System SHALL display the Receiving_Workflow.
5. WHEN a Pharmacy_Operator submits stock fields to a medicine-only endpoint, THE MedFind_System SHALL return validation errors directing the Pharmacy_Operator to the Receiving_Workflow.
6. THE MedFind_System SHALL preserve the named routes `pharmacy.inventory`, `pharmacy.inventory.create`, `pharmacy.inventory.store`, `pharmacy.inventory.edit`, `pharmacy.inventory.update`, `pharmacy.inventory.destroy`, `pharmacy.inventory.bulk-update`, `pharmacy.receiving.create`, `pharmacy.receiving.store`, and `pharmacy.inventory.export`.

### Requirement 2: Maintain Medicine Master Data

**User Story:** As a Pharmacy_Operator, I want to maintain medicine identity independently, so that every received batch references consistent product information.

#### Acceptance Criteria

1. THE Medicine_Master_Service SHALL store Generic Name in the existing `medicine_name` field.
2. THE Medicine_Master_Service SHALL store Brand Name, Dosage, Category, Manufacturer, prescription classification, and product cold-chain requirement on the Medicine_Master.
3. WHEN a Pharmacy_Operator submits an Add Medicine request, THE Medicine_Master_Service SHALL require a Generic Name with 1 to 255 non-whitespace characters.
4. WHEN a Pharmacy_Operator creates a Medicine_Master for pharmacy inventory, THE MedFind_System SHALL create or retain one Pharmacy_Inventory_Aggregate for the pharmacy and Medicine_Master pair.
5. WHEN a Pharmacy_Operator edits a Medicine_Master or par level, THE MedFind_System SHALL leave every Inventory_Batch quantity and identity unchanged.
6. THE Pharmacy_Inventory_Aggregate SHALL store par level as a non-negative integer.
7. WHEN a product cold-chain requirement is enabled, THE Medicine_Master_Service SHALL identify the Medicine_Master as requiring cold-chain storage.

### Requirement 3: Receive Distinct Stock Batches

**User Story:** As a Pharmacy_Operator, I want each delivery stored as a distinct batch, so that quantity, price, supplier, expiry, and traceability remain accurate.

#### Acceptance Criteria

1. WHEN a Pharmacy_Operator submits the Receiving_Workflow, THE Inventory_Batch_Service SHALL require an existing Medicine_Master selected from the current pharmacy inventory catalog.
2. WHEN a Pharmacy_Operator submits the Receiving_Workflow, THE Inventory_Batch_Service SHALL require a batch number with 1 to 255 non-whitespace characters.
3. WHEN a Pharmacy_Operator submits the Receiving_Workflow, THE Inventory_Batch_Service SHALL require a positive integer quantity received.
4. WHEN a Pharmacy_Operator submits the Receiving_Workflow, THE Inventory_Batch_Service SHALL require a non-negative price with at most two decimal places.
5. THE Inventory_Batch_Service SHALL accept lot number, Supplier_Name, expiry date, cold-chain state, received date, and Received_Reference.
6. WHEN received date is omitted, THE Inventory_Batch_Service SHALL use the current application date as the received date.
7. WHEN a Supplier_Name matches an existing supplier name after case-folding and whitespace normalization, THE Inventory_Batch_Service SHALL link the Inventory_Batch to the existing supplier.
8. WHEN a non-empty Supplier_Name has no normalized supplier match, THE Inventory_Batch_Service SHALL create a supplier record and link the Inventory_Batch to the new supplier.
9. WHEN the Medicine_Master requires cold-chain storage, THE Inventory_Batch_Service SHALL require the Inventory_Batch cold-chain state to be enabled.
10. WHEN a valid delivery is received, THE Inventory_Batch_Service SHALL create an Inventory_Batch with quantity received equal to current quantity.
11. WHEN a second delivery has a different Batch_Identity_Key, THE Inventory_Batch_Service SHALL preserve the prior Inventory_Batch and create another Inventory_Batch.
12. IF a Batch_Identity_Key already exists for the Pharmacy_Inventory_Aggregate, THEN THE Inventory_Batch_Service SHALL reject the delivery without changing stock.
13. WHEN Receiving_Workflow validation fails, THE MedFind_System SHALL preserve all submitted item rows and field values for correction.

### Requirement 4: Synchronize Aggregate Inventory

**User Story:** As a MedFind user, I want aggregate inventory values derived consistently from batches, so that all screens report the same available stock.

#### Acceptance Criteria

1. WHEN an Inventory_Batch quantity, expiry date, price, or received date changes, THE Aggregate_Synchronizer SHALL recalculate the associated Pharmacy_Inventory_Aggregate within the same database transaction.
2. THE Aggregate_Synchronizer SHALL set `stockQuantity` to Available_Stock.
3. THE Aggregate_Synchronizer SHALL set `price` to Representative_Price when an Inventory_Batch is available.
4. WHEN no Inventory_Batch is available, THE Aggregate_Synchronizer SHALL retain the price from the most recently received Inventory_Batch.
5. WHEN no Inventory_Batch exists, THE Aggregate_Synchronizer SHALL retain the existing non-negative aggregate price.
6. THE Aggregate_Synchronizer SHALL derive aggregate availability and low-stock status from Available_Stock and par level.
7. THE Aggregate_Synchronizer SHALL expose Physical_Stock separately from Available_Stock.
8. WHEN an Inventory_Batch expires, THE Aggregate_Synchronizer SHALL exclude the Inventory_Batch current quantity from Available_Stock without deleting the Inventory_Batch.
9. THE Aggregate_Synchronizer SHALL select nearest valid expiry information from the first Inventory_Batch in FEFO_Order with positive current quantity.
10. WHEN no available Inventory_Batch has an expiry date, THE Aggregate_Synchronizer SHALL report no nearest expiry date.

### Requirement 5: Present Aggregate Inventory and Batch Details

**User Story:** As a Pharmacy_Operator, I want a medicine-level inventory page and a batch-level detail page, so that I can understand totals and inspect traceable stock.

#### Acceptance Criteria

1. THE pharmacy inventory page SHALL display one row per Pharmacy_Inventory_Aggregate.
2. THE pharmacy inventory page SHALL display Generic Name, Brand Name, Dosage, Available_Stock, aggregate status, Representative_Price, par level, and nearest valid expiry information.
3. THE pharmacy inventory page SHALL provide Add Stock, View Batches, and Edit Medicine/Par actions for each Pharmacy_Inventory_Aggregate.
4. WHEN a Pharmacy_Operator selects View Batches, THE MedFind_System SHALL display Inventory_Batch records for the selected Pharmacy_Inventory_Aggregate in FEFO_Order.
5. THE batch detail page SHALL display batch number, lot number, quantity received, current quantity, price, Supplier_Name, expiry date, cold-chain state, received date, Received_Reference, and expiry status.
6. WHEN a Pharmacy_Operator edits batch metadata, THE Inventory_Batch_Service SHALL preserve quantity received and Stock_Movement history.
7. WHEN a Pharmacy_Operator requests a batch quantity correction, THE Inventory_Batch_Service SHALL require a correction reason with 1 to 1000 non-whitespace characters.
8. WHEN a valid batch quantity correction is submitted, THE Inventory_Batch_Service SHALL record a Stock_Movement and an Inventory_Audit.
9. IF a batch edit would duplicate another Batch_Identity_Key in the Pharmacy_Inventory_Aggregate, THEN THE Inventory_Batch_Service SHALL reject the edit without changing stock.
10. THE pharmacy inventory page and batch detail page SHALL support phone, tablet, and desktop viewport widths without hiding required actions.

### Requirement 6: Allocate Stock Decreases by FEFO

**User Story:** As a Pharmacy_Operator, I want every stock decrease allocated by FEFO, so that usable stock is consumed before later-expiring stock and aggregates cannot diverge from batches.

#### Acceptance Criteria

1. WHEN a stock decrease is requested, THE FEFO_Allocator SHALL lock the Pharmacy_Inventory_Aggregate and eligible Inventory_Batch records within one database transaction.
2. THE FEFO_Allocator SHALL exclude expired Inventory_Batch records from eligible stock.
3. THE FEFO_Allocator SHALL order eligible Inventory_Batch records in FEFO_Order.
4. WHEN one Inventory_Batch cannot satisfy a stock decrease, THE FEFO_Allocator SHALL continue allocation across subsequent eligible Inventory_Batch records.
5. WHEN eligible Available_Stock is sufficient, THE FEFO_Allocator SHALL reduce Inventory_Batch current quantities by exactly the requested quantity.
6. IF eligible Available_Stock is less than the requested decrease, THEN THE FEFO_Allocator SHALL reject the operation without changing any quantity.
7. WHEN the FEFO_Allocator completes a decrease, THE FEFO_Allocator SHALL create one Stock_Movement for each affected Inventory_Batch.
8. WHEN the FEFO_Allocator completes a decrease, THE Aggregate_Synchronizer SHALL recalculate the Pharmacy_Inventory_Aggregate before transaction commit.
9. WHEN controlled-substance dispensing, wastage, or transfer decreases stock, THE Controlled_Substance workflow SHALL use the FEFO_Allocator.
10. WHEN a return or recall decreases stock without a selected Inventory_Batch, THE return-and-recall workflow SHALL use the FEFO_Allocator.
11. WHEN a recall identifies a specific Inventory_Batch, THE return-and-recall workflow SHALL decrease only the selected Inventory_Batch after validating sufficient current quantity.

### Requirement 7: Define Aggregate Adjustment Behavior

**User Story:** As a Pharmacy_Operator, I want direct and counted stock adjustments reconciled with batches, so that compatibility fields never become an independent stock source.

#### Acceptance Criteria

1. WHEN an aggregate target quantity is lower than Available_Stock, THE Stock_Adjustment service SHALL allocate the difference through the FEFO_Allocator.
2. WHEN an aggregate target quantity is greater than Available_Stock, THE Stock_Adjustment service SHALL require batch identity, price, received date, and correction reason before creating an adjustment Inventory_Batch.
3. IF a legacy bulk-update request increases aggregate stock without required batch metadata, THEN THE MedFind_System SHALL reject the increase without changing aggregate or batch quantities.
4. WHEN a legacy bulk-update request decreases aggregate stock, THE MedFind_System SHALL apply the decrease through the Stock_Adjustment service.
5. WHEN a multi-item bulk-update request contains any invalid adjustment, THE MedFind_System SHALL reject all quantity changes in the request.
6. WHEN a controlled-substance adjustment sets a lower target quantity, THE Controlled_Substance workflow SHALL apply the difference through the FEFO_Allocator.
7. WHEN a controlled-substance adjustment sets a higher target quantity, THE Controlled_Substance workflow SHALL require adjustment-batch metadata and create an audited adjustment Inventory_Batch.
8. WHEN a cycle count sets a lower counted quantity, THE cycle-count workflow SHALL apply the difference through the FEFO_Allocator.
9. WHEN a cycle count sets a higher counted quantity, THE cycle-count workflow SHALL create an audited adjustment Inventory_Batch linked to the cycle-count reference.
10. THE MedFind_System SHALL route every controller-level stock mutation through the Inventory_Batch_Service, FEFO_Allocator, or Stock_Adjustment service.

### Requirement 8: Backfill Existing Inventory Safely

**User Story:** As a system owner, I want existing stock converted to batches without data loss, so that production deployment preserves current inventory.

#### Acceptance Criteria

1. WHEN the batch schema migration runs, THE MedFind_System SHALL retain every Legacy_Inventory_Row primary key and pharmacy-and-medicine relationship.
2. WHEN a Legacy_Inventory_Row has no corresponding backfill Inventory_Batch, THE migration SHALL create exactly one Inventory_Batch linked to the Legacy_Inventory_Row.
3. WHEN a Legacy_Inventory_Row has a corresponding backfill Inventory_Batch, THE migration SHALL leave the existing Inventory_Batch unchanged.
4. THE migration SHALL copy legacy stock quantity to quantity received and current quantity without changing the numeric value.
5. THE migration SHALL copy legacy price, batch number, lot number, expiry date, cold-chain state, supplier linkage, creation time, and update time to the backfill Inventory_Batch.
6. WHEN a Legacy_Inventory_Row has no batch number, THE migration SHALL generate a deterministic legacy batch number from the InventoryItem primary key.
7. WHEN a Legacy_Inventory_Row has no received date, THE migration SHALL derive received date from creation time and use the migration date only when creation time is absent.
8. THE migration SHALL generate the same deterministic Batch_Identity_Key on PostgreSQL and SQLite.
9. THE migration SHALL support repeated execution without creating duplicate Inventory_Batch or Stock_Movement records.
10. WHEN backfill completes, THE Aggregate_Synchronizer SHALL produce the same non-expired available quantity as the backfilled Inventory_Batch data.
11. THE migration SHALL preserve existing aggregate batch, lot, expiry, supplier, cold-chain, quantity, price, and status columns during the compatibility period.
12. IF any Legacy_Inventory_Row cannot be backfilled or verified, THEN THE migration SHALL fail the transaction without deleting legacy data.
13. THE migration SHALL run successfully on the project SQLite test database and supported PostgreSQL deployment database.
14. WHEN the canonical schema inventory changes, THE PostgreSQL transfer and preservation utilities SHALL include Inventory_Batch and Stock_Movement tables in dependency-safe order.

### Requirement 9: Preserve Integration Behavior

**User Story:** As a MedFind stakeholder, I want existing inventory consumers to remain consistent, so that batch management does not regress public or operational workflows.

#### Acceptance Criteria

1. THE Consumer_Inventory_View SHALL expose Available_Stock instead of Physical_Stock.
2. WHEN Available_Stock is zero, THE Consumer_Inventory_View SHALL report the medicine as out of stock.
3. THE Consumer_Inventory_View SHALL exclude expired Inventory_Batch quantities from displayed and searchable stock totals.
4. THE pharmacy dashboard SHALL calculate inventory counts, in-stock counts, low-stock alerts, expired counts, and FEFO alerts from aggregate and batch source-of-truth values.
5. THE CSV export SHALL produce one aggregate row per pharmacy medicine with Available_Stock, Physical_Stock, Representative_Price, nearest valid expiry, par level, and existing medicine classification fields.
6. WHERE batch export is enabled, THE CSV export SHALL provide a separate batch-granularity export containing Inventory_Batch traceability fields.
7. THE inventory analysis workflow SHALL calculate stock value from Available_Stock and Representative_Price.
8. THE cycle-count workflow SHALL use aggregate Available_Stock as expected quantity.
9. THE return-and-recall workflow SHALL retain existing ReturnRecall records linked to Pharmacy_Inventory_Aggregate records.
10. THE controlled-substance workflow SHALL retain existing ControlledSubstanceLog records linked to Pharmacy_Inventory_Aggregate records.
11. THE Inventory_Audit workflow SHALL retain existing audit records linked to Pharmacy_Inventory_Aggregate records.
12. WHEN aggregate stock or Representative_Price changes, THE MedFind_System SHALL broadcast one post-commit inventory update containing synchronized aggregate values.
13. THE MedFind_System SHALL update factories and seeders to create consistent Pharmacy_Inventory_Aggregate and Inventory_Batch data.
14. THE admin inventory view SHALL display aggregate quantities and derive expiry filters from Inventory_Batch records.

### Requirement 10: Enforce Pharmacy Isolation and Authorization

**User Story:** As a pharmacy owner, I want batch data isolated by pharmacy, so that another pharmacy cannot view or modify my stock.

#### Acceptance Criteria

1. WHEN a Pharmacy_Operator requests an Inventory_Batch list, THE MedFind_System SHALL verify that the parent Pharmacy_Inventory_Aggregate belongs to the Pharmacy_Operator pharmacy.
2. WHEN a Pharmacy_Operator requests an Inventory_Batch mutation, THE MedFind_System SHALL verify that the Inventory_Batch parent belongs to the Pharmacy_Operator pharmacy.
3. IF a requested Pharmacy_Inventory_Aggregate belongs to another pharmacy, THEN THE MedFind_System SHALL return a not-found response without disclosing aggregate data.
4. IF a requested Inventory_Batch belongs to another pharmacy, THEN THE MedFind_System SHALL return a not-found response without disclosing batch data.
5. WHEN a Receiving_Workflow medicine selection is submitted, THE MedFind_System SHALL verify that the selected Pharmacy_Inventory_Aggregate belongs to the Pharmacy_Operator pharmacy.
6. THE MedFind_System SHALL scope Inventory_Batch, Inventory_Audit, ControlledSubstanceLog, ReturnRecall, and CycleCount lookups through the authenticated pharmacy.

### Requirement 11: Provide Clear Responsive Actions and Validation

**User Story:** As a Pharmacy_Operator, I want clear navigation and recoverable forms, so that medicine and stock tasks are efficient on any supported device.

#### Acceptance Criteria

1. THE pharmacy dashboard SHALL display Manage Inventory, Add New Medicine, Add Stock/Receive Delivery, and View Stock Batches actions.
2. WHEN validation fails on Add Medicine, THE MedFind_System SHALL preserve submitted Medicine_Master and par-level values.
3. WHEN validation fails on Receiving_Workflow, THE MedFind_System SHALL identify errors by delivery row and field.
4. WHEN validation fails on a batch edit, THE MedFind_System SHALL preserve submitted batch metadata and correction values.
5. THE Add Medicine, Receiving_Workflow, inventory, and batch detail pages SHALL render required fields and actions at a 320-pixel viewport width without horizontal page overflow.
6. THE MedFind_System SHALL display expired stock separately from Available_Stock on pharmacy-only batch views.
7. WHEN an operation fails for insufficient Available_Stock, THE MedFind_System SHALL display the requested quantity and current Available_Stock.

### Requirement 12: Preserve Auditability and Transactional Consistency

**User Story:** As an auditor, I want every stock transition attributable and atomic, so that stock history can be reconstructed.

#### Acceptance Criteria

1. WHEN an Inventory_Batch is received, THE MedFind_System SHALL create a receipt Stock_Movement containing quantity, actor, timestamp, and Received_Reference.
2. WHEN an Inventory_Batch quantity changes, THE MedFind_System SHALL create a Stock_Movement containing before quantity, after quantity, signed delta, reason, actor, and timestamp.
3. WHEN aggregate Available_Stock changes, THE MedFind_System SHALL create one Inventory_Audit containing aggregate before and after quantities.
4. WHEN one operation affects multiple Inventory_Batch records, THE MedFind_System SHALL associate the Stock_Movement records and Inventory_Audit with one operation identifier.
5. IF Stock_Movement creation, Inventory_Audit creation, aggregate synchronization, or domain-log creation fails, THEN THE MedFind_System SHALL roll back every quantity change in the operation.
6. WHEN a controlled-substance operation succeeds, THE Controlled_Substance workflow SHALL record the existing controlled-substance log and associated batch allocation details in the same transaction.
7. WHEN a return, recall, or cycle-count operation succeeds, THE MedFind_System SHALL record the existing domain record and associated batch allocation details in the same transaction.

### Requirement 13: Preserve Existing Work and Verify the Redesign

**User Story:** As a maintainer, I want the redesign introduced without reverting unrelated work, so that deployment fixes and recent form improvements remain intact.

#### Acceptance Criteria

1. THE implementation SHALL preserve unrelated uncommitted changes present before implementation begins.
2. THE implementation SHALL retain the recently prepared Brand Name, Lot Number, editable Supplier_Name, scoped form autofill, and old-input behavior while relocating stock fields to the correct workflow.
3. THE implementation SHALL retain Railway and PostgreSQL deployment fixes that are unrelated to batch stock behavior.
4. THE automated test suite SHALL verify medicine creation, batch receiving, duplicate identity rejection, FEFO allocation, expired-stock exclusion, aggregate synchronization, adjustment behavior, pharmacy isolation, and integration compatibility.
5. THE automated test suite SHALL verify migration backfill and idempotence on SQLite.
6. WHERE the disposable PostgreSQL rehearsal environment is enabled, THE automated test suite SHALL verify schema migration and backfill behavior on PostgreSQL.
7. THE automated test suite SHALL preserve the isolated SQLite in-memory default test configuration.
8. THE implementation SHALL pass focused PHP unit and feature tests, the full PHP test suite, code formatting checks, and the frontend production build.
