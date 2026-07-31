# **\[PRD\] Gerai Masjid ERP (GM ERP)**

**Status:** Ready for RFC

**Author:** GM ERP Product Team

**Last Updated:** 2026-07-17

**PRD Type:** platform

## **1\. Executive Summary & Problem (PRB)**

* **Problem:** Operational activities for the "Sedekah Baju (SB) Bogor" social enterprise are currently managed through disconnected, static spreadsheets (Dashboard, Stock, Sales, Buku\_Kas, Diskon, SDM, Operasional, Detail1, Pengembangan, Mitra). This static architecture causes extreme inventory visibility friction, high data entry latency, unrecorded cash handling practices, and high financial data discrepancy risks when separating revenue splits manually into dedicated operational pools (such as HR, Operations, and Development).  
* **Target Audience:** Admin (Super Admins/Owners), Logistics & Finance Managers, Branch Supervisors, and Store Cashiers (Staff).  
* **Why Now?:** The operational footprint is expanding rapidly across multiple mosque branches and pop-up events. Managing dynamic, live retail transactions with static spreadsheet tracking causes persistent discrepancies between system data and actual on-hand balances, delays critical stock replenishment, and blocks leadership from viewing unified business health indicators.

## **2\. Objectives & Success Metrics (OBJ)**

| Goal | Metric | Baseline | Target |
| :---- | :---- | :---- | :---- |
| Eliminate Stock Discrepancies | Live Inventory Accuracy Sync Rate | 0% (Manual End-of-Month) | 100% Real-Time Core API Sync |
| Automate Ledger Entries | Bookkeeping Input Latency | 3 \- 5 Business Days | Real-time posting on checkout or manager expense logs |
| Enhance Multi-Tenant Visibility | Overhead in Branch Auditing | High manual file compilation | Zero manual compilation; Instant aggregate and branch filters |

## **3\. Scope & Boundaries (SCO)**

* **In-Scope:**  
  * **Multi-Warehouse Inventory Architecture:** Core entry flows restricted to the Central Warehouse before routing transfers down to active branch locations.  
  * **Resilient POS Checkout:** High-concurrency checkout interface with programmatic negative stock overrides to guarantee front-line transactions never stall.  
  * **Granular View Matrix:** Dashboard configurations allowing full system overview metrics for Admins/Managers, and single-tenant branch filtering for local Supervisors.  
  * **Dynamic General Ledger & Expense Management:** Flexible expense booking interface mapped to structured financial pools (HR, Store Operations, Diskon/Selisih, and Development Funds).  
  * **Centrally Hardcoded Discounts:** POS terminals restricted to using verified, admin-defined promotion schemas to mitigate unauthorized discount entry risks.  
  * **Cash Drawer Remittance (Setoran Kas):** Controlled cash transfer workflow moving storefront cash liabilities into verified central vault balances.  
* **Out-of-Scope:**  
  * Automated SMS/WhatsApp donor transaction notifications (deferred to Phase 2).  
  * Multi-currency ledger conversions (all financial structures locked to IDR).  
* **MVP Definition:**  
  * Functional Back-Office web suite (FilamentPHP components) supporting multi-warehouse mapping, general ledger accounting, fixed discount definitions, and a robust JSON API to drive branch-level POS terminals within a tight 1-month execution timeline.

## **4\. User Stories (UST \- RFC Readiness)**

### **Story 1: Centralized Product Entry and Multi-Warehouse Stock Allocation**

**As a** Logistics Manager,

**I want to** register incoming clothing donations into the Central Warehouse and later issue structural batch transfers to specific branches,

**so that** tracking records are properly logged before items hit retail storefronts.

* **Before:** Donations are recorded across generic spreadsheet cells, resulting in branch staff selling items that have not been counted or priced at the hub level.  
* **After (Delta):** Items must pass price tier matrix assignments at the Central Warehouse first, establishing a structured audit record before any inventory is visible or accessible by downstream outlets.

**Fields Data Table:**

| Field Name | Type | Purpose | Validation | Default | Dependency |
| :---- | :---- | :---- | :---- | :---- | :---- |
| id | UUID | Primary Key | Required, Unique | Generated | None |
| sku | String | Unique product stock identifier | Required, Unique | None | None |
| price\_tier | Decimal | Fixed retail price node | Required, Min: 0 | 0.00 | None |
| warehouse\_id | UUID | Active warehouse location | Required, FK | Central WH | warehouses.id |
| quantity | Integer | Volume of items | Required, Min: 0 | 0 | None |

**Business Logic:**

* **Permission:** Admin, Manager.  
* **Rules:**  
  * All new base inventory records must default to the Central Warehouse location.  
  * Inventory distribution actions can only select active, verified target branches.  
* **Side Effects:** Emits InventoryTransferred system events. Write-locks matching database records to prevent race conditions during heavy movement windows, and appends rows to the tracking ledger.

**Acceptance Criteria (GWT):**

* \[ \] Given an authenticated Manager, when they add 100 units of "Rp10.000 Tier" apparel to the Central Warehouse, then system stock levels increment by 100 units and generate an immutable inventory audit trace.  
* \[ \] Given a Manager attempts to transfer stock batches to a deleted or unverified branch ID, then the operation cancels, rolls back all parameters, and displays a "Destination Branch Invalid" alert.

### **Story 2: Resilient Store Checkout with Allowed Negative Inventory**

**As a** Store Cashier,

**I want to** complete transactions through the POS application even if database stock numbers read zero,

**so that** live customer sales are never hindered by administrative data entry delays.

* **Before:** Cashiers have to cross-check items manually or adjust spreadsheet records, which leads to long customer lines and lost sales opportunities during crowded mosque bazaar events.  
* **After (Delta):** The POS terminal allows checkout procedures to pass zero-balance limits, shifting the database stock state to negative integers while automatically queuing an alert for Supervisor reconciliation.

**Fields Data Table:**

| Field Name | Type | Purpose | Validation | Default | Dependency |
| :---- | :---- | :---- | :---- | :---- | :---- |
| order\_id | UUID | Primary Order Key | Required, Unique | Generated | None |
| branch\_id | UUID | Terminal location reference | Required, FK | None | branches.id |
| items | JSONB | Array of nested product sales data | Required structure | None | products.id |
| discount\_id | UUID | Linked promotion record | Optional, FK | Null | discounts.id |
| payment\_mode | Enum | Mode classification | CASH or QRIS | CASH | None |
| total\_price | Decimal | Net invoice value | Required, Min: 0 | 0.00 | None |

**Business Logic:**

* **Permission:** Staff (Cashier), Supervisor, Manager, Admin.  
* **Rules:**  
  * If a campaign discount code is present, confirm the target discount\_id matches an active promo configuration established by central Admins. If invalid, block checkout initialization.  
  * If requested item checkout volume exceeds database stock availability, permit the transaction to finish, drop the record value into a negative integer state, and throw a NegativeStockFlag warning event.  
  * Cash choices route calculations straight to the local branch cash\_drawer\_balance. QRIS entries pass directly to the central QRIS clearing account balance.  
* **Side Effects:** Decrements targeted branch store inventory records, creates automated accounting logs in the general ledger, and reflects the transaction on the supervisor and global dashboard screens.

**Acceptance Criteria (GWT):**

* \[ \] Given a physical clothing item exists on a store rack but its database inventory reads 0, when the Cashier processes a sale for that item, then the system processes the invoice and drops database stock status down to \-1.  
* \[ \] Given a Cashier attempts to apply a manual discount override not defined in the admin settings, then the checkout interface fails to authorize and logs an "Unauthorized Discount Schema" system error.

### **Story 3: Verified Physical Stock Opname & Variance Auditing**

**As a** Branch Supervisor,

**I want to** execute a formal physical stock reconciliation entry by inputting verified shelf quantities along with a clear reason text block,

**so that** negative stock numbers are corrected transparently.

* **Before:** Variations between system records and physical items are modified using unmonitored spreadsheet edits, leaving no historical trace detailing why inventory adjustments happened.  
* **After (Delta):** A dedicated Stock Opname screen requires supervisors to append actual physical item counts alongside a mandatory explanation log, which resets database values to match physical store realities.

**Fields Data Table:**

| Field Name | Type | Purpose | Validation | Default | Dependency |
| :---- | :---- | :---- | :---- | :---- | :---- |
| opname\_id | UUID | Primary Reconciliation Key | Required, Unique | Generated | None |
| branch\_id | UUID | Associated storefront identifier | Required, FK | None | branches.id |
| product\_id | UUID | Associated product identifier | Required, FK | None | products.id |
| system\_qty | Integer | Volume state recorded by database | Required | 0 | None |
| physical\_qty | Integer | Count verified manually on-site | Required, Min: 0 | None | None |
| reason\_log | Text | Explanatory text for tracking updates | Required, Min 10 chars | None | None |

**Business Logic:**

* **Permission:** Supervisor, Manager, Admin. (Staff role explicitly blocked).  
* **Rules:**  
  * The reconciliation form input will fail if the reason\_log text block contains fewer than 10 characters.  
  * On approval, the targeted product record's active inventory level immediately matches the inputted physical\_qty value.  
* **Side Effects:** Automatically calculates the physical variance delta (physical\_qty \- system\_qty), writes an entry to inventory\_audit\_logs, and resets the branch's negative stock alerts.

**Acceptance Criteria (GWT):**

* \[ \] Given a product inventory level is stuck at \-3 with verified physical stock counting at 12, when the Supervisor submits an opname form containing the text note "Intake sorting batch delayed from central hub", then the database value updates to 12 and logs a variance of \+15.  
* \[ \] Given a Supervisor attempts to clear a negative stock alert but provides an empty explanation field, then the application rejects the entry and displays a "Mandatory justification log required" error.

### **Story 4: Dynamic Ledger Expense Entry and Fund Pool Routing**

**As a** Logistics/Finance Manager,

**I want to** record operational expenses through a flexible layout by picking a custom financial target pool,

**so that** organizational expenses are sorted correctly without requiring hardcoded input views.

* **Before:** System outlays are compiled manually by moving data between separate spreadsheet sheets named "SDM", "Operasional", and "Pengembangan".  
* **After (Delta):** A single expense module maps financial outflows to structured pools via a dynamic selection field, which updates the central general ledger and account metrics in real time.

**Fields Data Table:**

| Field Name | Type | Purpose | Validation | Default | Dependency |
| :---- | :---- | :---- | :---- | :---- | :---- |
| expense\_id | UUID | Primary Expense Entry Key | Required, Unique | Generated | None |
| source\_cash\_id | UUID | Originating account reference | Required, FK | None | cash\_accounts.id |
| target\_pool | Enum | Target cost allocation category | HR / OPS / DEV / DISC | None | None |
| amount | Decimal | Total financial outlay | Required, Positive | 0.00 | None |
| notes | String | Explanatory description | Required | None | None |

**Business Logic:**

* **Permission:** Manager, Admin. (Staff and Supervisors are blocked from this module).  
* **Rules:**  
  * Outbound transaction values must be greater than zero.  
  * The selected source\_cash\_id account must hold a balance greater than or equal to the requested expense before authorizing the outflow.  
* **Side Effects:** Deducts the input value from the corresponding source balance, writes an outbound record into the general cash\_transactions database table, and updates expense analytics charts.

**Acceptance Criteria (GWT):**

* \[ \] Given a central cash account holds an active balance of Rp10.000.000, when the Manager logs a Rp2.000.000 payout to the HR allocation pool for branch stipends, then the source cash balance drops to Rp8.000.000.  
* \[ \] Given a Manager attempts to log an expense entry that exceeds the available liquidity of the chosen source cash account, then the transaction system cancels the request and alerts the user with an "Insufficient Account Funds" notification.

### **Story 5: End-of-Day Branch Cash Remittance (Setoran Kas)**

**As a** Branch Supervisor,

**I want to** submit a formal cash remittance request to transfer collected cash drawer revenue up to the central office balance,

**so that** local cash drawer safety exposure is minimized.

* **Before:** Retail cash remains sitting inside regional cash drawers without explicit structural tracking records, increasing the risk of unrecorded usage or manual bookkeeping mistakes.  
* **After (Delta):** Local branch cash drawer volumes are tracked as system liabilities. A remittance entry places these funds into a "Pending Verification" state until a central Manager verifies receipt of the physical cash.

**Fields Data Table:**

| Field Name | Type | Purpose | Validation | Default | Dependency |
| :---- | :---- | :---- | :---- | :---- | :---- |
| remit\_id | UUID | Primary Remittance tracking key | Required, Unique | Generated | None |
| branch\_id | UUID | Source retail outlet identifier | Required, FK | None | branches.id |
| amount | Decimal | Total cash volume transferred | Required, Positive | 0.00 | None |
| status | Enum | Processing milestone classification | PENDING / VERIFIED | PENDING | None |
| recipient\_id | UUID | Approving user identifier | Optional, FK | Null | users.id |

**Business Logic:**

* **Permission:** Supervisor initiates. Manager or Admin verifies and approves.  
* **Rules:**  
  * The remittance request amount cannot exceed the active cash\_drawer\_balance tracking metric at the originating branch.  
  * The branch's local cash drawer pool status changes to "Reserved/In-Transit" and remains locked until a central Manager moves the transaction tracking state to VERIFIED.  
* **Side Effects:** Adjusts active storefront cash records, updates the central treasury balance upon verification approval, and logs the complete transfer step metrics within the financial audit tables.

## **5\. Technical Feasibility (TEC) & Dependencies (DEP)**

### **RFC Hooks (Signals for Engineering Run)**

#### **Schema Change Tables**

| Table Name | Field Name | Type | Key / Validation / Rules |
| :---- | :---- | :---- | :---- |
| warehouses | id | UUID | PRIMARY KEY, NOT NULL |
| warehouses | name | VARCHAR(255) | NOT NULL |
| warehouses | type | VARCHAR(50) | NOT NULL ('CENTRAL', 'BRANCH') |
| products | id | UUID | PRIMARY KEY, NOT NULL |
| products | sku | VARCHAR(100) | UNIQUE, NOT NULL |
| products | price\_tier | DECIMAL(12,2) | NOT NULL |
| inventories | id | UUID | PRIMARY KEY, NOT NULL |
| inventories | product\_id | UUID | FOREIGN KEY REFERENCES products(id) |
| inventories | warehouse\_id | UUID | FOREIGN KEY REFERENCES warehouses(id) |
| inventories | quantity | INT | NOT NULL, DEFAULT 0 |
| branches | id | UUID | PRIMARY KEY, NOT NULL |
| branches | name | VARCHAR(255) | NOT NULL |
| branches | warehouse\_id | UUID | FOREIGN KEY REFERENCES warehouses(id) |
| users | id | UUID | PRIMARY KEY, NOT NULL |
| users | name | VARCHAR(255) | NOT NULL |
| users | role | VARCHAR(50) | NOT NULL ('ADMIN', 'MANAGER', 'SUPERVISOR') |
| inventory\_audit\_logs | id | UUID | PRIMARY KEY, NOT NULL |
| inventory\_audit\_logs | branch\_id | UUID | FOREIGN KEY REFERENCES branches(id) |
| inventory\_audit\_logs | product\_id | UUID | FOREIGN KEY REFERENCES products(id) |
| inventory\_audit\_logs | system\_qty | INT | NOT NULL |
| inventory\_audit\_logs | physical\_qty | INT | NOT NULL |
| inventory\_audit\_logs | user\_id | UUID | FOREIGN KEY REFERENCES users(id) |
| inventory\_audit\_logs | reason\_log | TEXT | NOT NULL |
| sales\_orders | id | UUID | PRIMARY KEY, NOT NULL |
| sales\_orders | branch\_id | UUID | FOREIGN KEY REFERENCES branches(id) |
| sales\_orders | total\_price | DECIMAL(12,2) | NOT NULL |
| sales\_orders | discount\_id | UUID | NULLABLE |
| sales\_orders | payment\_mode | VARCHAR(50) | NOT NULL ('CASH', 'QRIS') |
| sales\_order\_items | id | UUID | PRIMARY KEY, NOT NULL |
| sales\_order\_items | order\_id | UUID | FOREIGN KEY REFERENCES sales\_orders(id) |
| sales\_order\_items | product\_id | UUID | FOREIGN KEY REFERENCES products(id) |
| sales\_order\_items | quantity | INT | NOT NULL |
| sales\_order\_items | unit\_price | DECIMAL(12,2) | NOT NULL |
| cash\_transactions | id | UUID | PRIMARY KEY, NOT NULL |
| cash\_transactions | order\_id | UUID | NULLABLE, FOREIGN KEY REFERENCES sales\_orders(id) |
| cash\_transactions | source\_cash\_id | UUID | NOT NULL |
| cash\_transactions | target\_pool | VARCHAR(50) | NOT NULL ('HR', 'OPS', 'DEV', 'DISC') |
| cash\_transactions | debit | DECIMAL(12,2) | NOT NULL, DEFAULT 0.00 |
| cash\_transactions | credit | DECIMAL(12,2) | NOT NULL, DEFAULT 0.00 |

#### **Core API Endpoint Specifications**

* **POST** /api/v1/pos/checkout  
  * Payload Header: Authorization: Bearer Token  
  * Payload Body:  
    {  
    "branch\_id": "8f3b92c4-721a-4c91-bd83-021948acde12",  
    "discount\_id": "11a2b3c4-5678-90ab-cdef-1234567890ab",  
    "payment\_mode": "CASH",  
    "items": \[  
    {  
    "product\_id": "4c918f3b-bd83-4c91-bd83-021948acde99",  
    "quantity": 3  
    }  
    \]  
    }  
  * Response Codes: 201 Created (Success Invoice), 422 Unprocessable Entity (Invalid Discount Configuration).  
* **POST** /api/v1/inventory/opname  
  * Payload Body:  
    {  
    "branch\_id": "8f3b92c4-721a-4c91-bd83-021948acde12",  
    "product\_id": "4c918f3b-bd83-4c91-bd83-021948acde99",  
    "physical\_qty": 15,  
    "reason\_log": "Found misplaced sorting box behind shelf cluster B."  
    }  
  * Response Codes: 200 OK (Inventory Level Reset), 403 Forbidden (Role permission block).  
* **POST** /api/v1/finance/remit  
  * Payload Body:  
    {  
    "branch\_id": "8f3b92c4-721a-4c91-bd83-021948acde12",  
    "amount": 2500000.00  
    }  
  * Response Codes: 201 Created (Remittance Request Logged), 400 Bad Request (Amount exceeds available drawer cash balance).

### **Dependencies (DEP)**

* **Authentication Gateway:** Secure communication token backend to identify POS terminals and enforce role-based routes.  
* **Database Transaction Locks:** Implementation of explicit database row locking mechanisms during updates to prevent stock overselling during simultaneous checkout bursts.

## **6\. Open Questions Log (The "Not Ready" Gate)**

| \# | Question | Owner | Status |
| :---- | :---- | :---- | :---- |
| 1 | Should the system automatically write off asset value changes in the general ledger when a negative stock transaction is resolved by an opname entry showing a lower physical count? |  |  |

