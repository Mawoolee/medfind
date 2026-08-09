# MedFind Pharmacy Inventory Management — Implementation Plan

## Phase 1 — Core Foundation
- [x] Fix missing supplier routes (edit/update/destroy) in `routes/web.php` — Verified present: index, create, store, edit, update, destroy
- [x] Create models: ControlledSubstanceLog, CycleCount, CycleCountItem, ReturnRecall, InventoryAudit
- [x] Add helper logic to InventoryItem model (FEFO scopes, low-stock, ABC/VED, segregation)

## Phase 2 — Receiving & Shelving
- [x] Enhance ReceivingController: PO verification, barcode, controlled-substance flagging
- [x] Create controlled-substance log entries on receive
- [x] Enhance /pharmacy/receiving/create view

## Phase 3 — Inventory Management
- [x] Enhance InventoryController: category filter, FEFO sort, cold-chain filter, low-stock filter, CSV export
- [x] Enhance inventory views: expiry/batch/cold-chain/par-level display, FEFO highlight, low-stock badges
- [x] Add stock-level alerts on dashboard

## Phase 4 — Categorization (ABC/VED)
- [x] New AnalysisController + /pharmacy/analysis page (ABC, VED, ABC-VED matrix)

## Phase 5 — Monitoring & Audits
- [x] Cycle count module (schedule, count, discrepancies)
- [x] Inventory audit log (before/after quantity) via recordAudit
- [x] Returns & recalls module
- [x] Controlled substance logbook view

## Phase 6 — Pharmacy Profile + Dashboard
- [x] Pharmacy profile page (name, address, contact, hours, logo, location)
- [x] Dashboard: low-stock alerts, quick actions, FEFO-expiring panel

## Phase 7 — Messages Polishing
- [x] Add filter by status (unread/read/all)

## Phase 9 — Feature Cleanup & Dashboard Search Tracking (align with thesis)
- [x] Aligned pharmacy navigation with thesis feature list (Dashboard, Inventory, Messages, Pharmacy Profile)
- [x] Removed 6 non-thesis items from nav + dashboard (Receive Shipment, Suppliers, ABC/VED, Cycle Counts, Returns & Recalls, Controlled Logbook); routes/controllers kept intact
- [x] Added "Add New Medicine" quick action to dashboard (matches thesis Quick Actions)
- [x] Fixed pharmacy dropdown not closing (added Alpine.js CDN to layouts/app.blade.php)
- [x] Fixed login page design (added Tailwind CDN fallback to guest layout + removed stale `public/hot`)
- [x] Added search tracking: `search_logs` table migration + `SearchLog` model
- [x] `ConsumerController@search` and `MedicineSearchController@search` now log a row per matched pharmacy per search
- [x] `PharmacyDashboardController@index` computes searchCountToday/Week/Total + topSearchQueries
- [x] Dashboard added "Searches Today" + "Total Searches" stat cards and "Most Searched Medicines" panel

## Phase 8 — Supplier CRUD Verification (this session)
- [x] Verified supplier routes present (index/create/store/edit/update/destroy)
- [x] Fixed `returns_index.blade.php` to use `route('pharmacy.returns.status')` (was `pharmacy.returns.update` → RouteNotFoundException)
- [x] Fixed `CycleCountController@store` validation (`items.*` now validates scalar checkbox ids, not `items.*.id`)
- [x] Fixed `AnalysisController@index` undefined-variable references (`&$countA/$countB/$countC` removed)
- [x] Added `@stack('scripts')` to `layouts/app.blade.php` so `receiving_create` `@push('scripts')` renders (add-item/barcode/controlled-toggle)
- [x] PHP syntax checks pass (`php -l`) on all modified PHP files
- [x] Route list verified: all dashboard quick-link + view-referenced routes exist
- [x] Fixed `ReturnRecall` model table name: Eloquent defaulted to `return_recalls` but the migration creates `returns_recalls` → added `protected $table = 'returns_recalls'` (fixes SQLSTATE[42P01] Undefined table on `/pharmacy/returns`)
- [x] Verified all Eloquent models map to correct tables (`ReturnRecall`, `CycleCount`, `CycleCountItem`, `InventoryAudit`, `ControlledSubstanceLog`)
