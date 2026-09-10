# Wisselbanken — B2B Construction Materials Marketplace

Wisselbanken is a multi-tenant B2B procurement platform for the construction industry. It connects general contractors, subcontractors, architects, engineers, property owners, manufacturers, distributors, and logistics providers in a single marketplace where they can create estimates, issue RFQs, negotiate quotes, manage orders, and track fulfilment — all gated by a fine-grained Role-Based Access Control (RBAC) system enforced at every route, view, and service layer.

---

## Table of Contents

1. [Purpose and Overview](#1-purpose-and-overview)
2. [Technology Stack](#2-technology-stack)
3. [Installation and Setup](#3-installation-and-setup)
4. [Application Architecture](#4-application-architecture)
5. [Database Schema](#5-database-schema)
6. [RBAC System](#6-rbac-system)
7. [Organization Types and Relationships](#7-organization-types-and-relationships)
8. [Feature Modules](#8-feature-modules)
9. [Services Layer](#9-services-layer)
10. [Frontend Architecture](#10-frontend-architecture)
11. [Configuration Reference](#11-configuration-reference)
12. [Development Workflow](#12-development-workflow)

---

## 1. Purpose and Overview

### What Wisselbanken Does

Wisselbanken solves the coordination problem in construction procurement. A general contractor managing a large project works with dozens of subcontractors, each of whom sources materials from a mix of manufacturers, stocking distributors, and rep agencies. Today this happens over email, spreadsheets, and phone calls with no audit trail and no shared data model.

Wisselbanken replaces that with a structured platform where:

- **Buyers** (GC, subcontractor, owner, architect) create project estimates, attach bill-of-materials line items, and issue RFQs to sellers they already have a trading relationship with.
- **Sellers** (manufacturer, distributor, rep agency) receive RFQs, quote a price, and fulfil orders — with automated checks that a seller org has an active principal/manufacturer relationship before they can respond.
- **Platform operators** manage roles, run approval workflows, handle returns, and maintain the product catalogue under a permission system that prevents any single user from approving their own purchases.
- **Project members** from different orgs collaborate on a shared project workspace, with the platform enforcing that a GC and a subcontractor have an active `gc_subcontractor` relationship before both can see the same estimate.

### Core Design Principles

- **Multi-tenancy per organisation**: every data query is scoped to `org_id`. A user can hold roles in multiple organisations and switch between them in one session.
- **RBAC enforced at three layers**: middleware (`RbacAudit`), service (`PermissionService`), and Blade directives (`@canDo` / `@cannotDo`). No UI element is hidden without a corresponding server-side check.
- **Relationship mesh**: orgs cannot trade with each other unless an `org_relationships` record exists and is active. The relationship type encodes what the two parties are allowed to do together.
- **Separation of duties**: `SodConflictRule` records prevent a user from holding two incompatible roles at once (e.g. submitter + approver for the same workflow).
- **Full audit trail**: every permission check, role assignment, and delegation is written to `rbac_audit_logs`.

---

## 2. Technology Stack

### Backend

| Component | Version / Package |
|---|---|
| Language | PHP 8.2 |
| Framework | Laravel 11 |
| Database | MariaDB (MySQL-compatible) |
| ORM | Eloquent |
| PDF generation | `barryvdh/laravel-dompdf ^3.1` |
| DataTables (server-side) | `yajra/laravel-datatables ^11.0` |
| Excel import/export | `maatwebsite/excel` |
| Authentication scaffolding | `laravel/ui ^4.6` |
| Queue driver | Database (configurable) |
| Cache driver | Database / File (configurable) |

### Frontend

| Component | Version |
|---|---|
| Build tool | Vite 6 |
| CSS framework | Bootstrap 5 + Sass |
| Utility CSS | Tailwind CSS 3 |
| Reactive UI | Alpine.js |
| HTTP client | Axios |
| Icons | Bootstrap Icons |

---

## 3. Installation and Setup

### Prerequisites

- PHP 8.2 with extensions: `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `fileinfo`
- Composer 2
- Node.js 20+ and npm
- MariaDB 10.6+ or MySQL 8+

### Step-by-Step Installation

```bash
# 1. Clone the repository
git clone <repo-url> wisselbanken
cd wisselbanken

# 2. Install PHP dependencies
composer install

# 3. Install JavaScript dependencies
npm install

# 4. Copy and configure the environment file
cp .env.example .env

# 5. Generate the application encryption key
php artisan key:generate

# 6. Configure your database in .env
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=wisselbanken
# DB_USERNAME=root
# DB_PASSWORD=your_password

# 7. Run all database migrations
php artisan migrate

# 8. Seed the database (platform data, RBAC structure, demo data)
php artisan db:seed

# 9. Build frontend assets
npm run build

# 10. Start the development server
php artisan serve
```

### RBAC-Specific Seeding

The RBAC seeders are separate from the main `DatabaseSeeder` and can be re-run independently:

```bash
# Seed organisation types, roles, permission groups, and the role-permission matrix
php artisan db:seed --class="Database\Seeders\Rbac\RbacSeeder" --force

# Seed SOD conflict rules
php artisan db:seed --class="Database\Seeders\Rbac\SodConflictRuleSeeder" --force
```

### Environment Variables

| Variable | Default | Purpose |
|---|---|---|
| `RBAC_MODE` | `audit` | `audit` logs without blocking; `enforce` returns 403 on failed checks |
| `RBAC_ENFORCE_BATCHES` | _(empty)_ | Comma-separated list of enforcement batches to activate: `admin,read,write,approve` |
| `APP_URL` | `http://localhost` | Base URL used in email links and asset paths |

To enable full permission enforcement:

```env
RBAC_MODE=enforce
RBAC_ENFORCE_BATCHES=admin,read,write,approve
```

---

## 4. Application Architecture

### Directory Structure

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Admin/          # Platform-admin CRUD panels
│   │   └── Frontend/       # User-facing feature controllers
│   └── Middleware/
│       ├── RbacAudit.php           # Permission check on every route
│       ├── AdminCheck.php          # Legacy admin gate
│       ├── CheckRole.php           # Role-string gate (legacy)
│       └── RefreshCsrfToken.php
├── Models/
│   ├── Rbac/               # RBAC entity models
│   └── *.php               # Domain models
├── Services/
│   └── Rbac/               # RBAC business logic services
config/
├── rbac.php                # RBAC mode, batches, onboarding bundles
└── route_permission_map.php # Route → permission group mapping
database/
├── migrations/             # 70+ migration files, chronological
└── seeders/
    ├── Rbac/
    │   ├── data/           # PHP arrays: roles, permission groups, matrix, org types
    │   └── *.php           # Seeder classes
    └── *.php               # Domain seeders
resources/
└── views/
    ├── admin/              # Platform-admin Blade templates
    └── user/               # User-facing Blade templates
routes/
└── web.php                 # All HTTP routes
```

### Request Lifecycle

1. **HTTP Request** arrives at Laravel's router.
2. **`RbacAudit` middleware** intercepts the request, looks up the route in `route_permission_map.php`, and calls `PermissionService::checkPermission()`.
   - In `audit` mode: logs the outcome and always passes the request through.
   - In `enforce` mode: returns HTTP 403 if the check fails (subject to batch filtering).
3. **Controller** runs, calling `PermissionService` directly for any additional fine-grained checks (e.g. project-scoped permissions, manage-member checks).
4. **Service layer** (`OrgRelationshipService`, `SodService`, `DelegationService`, etc.) enforces business rules beyond the permission check.
5. **Blade view** uses `@canDo` / `@cannotDo` directives to hide or show UI elements based on the same permission logic.

---

## 5. Database Schema

### Core Domain Tables

| Table | Purpose |
|---|---|
| `users` | Platform user accounts (name, email, password, profile_image) |
| `products` | Master product catalogue entries |
| `product_variations` | Size/thickness/finish variants of a product |
| `product_variation_colors` | Color assignments per variation |
| `product_files` | Spec sheets, CAD files, images attached to products |
| `manufacturers` | Manufacturer master records |
| `divisions` | Product division/category groupings |
| `specifications` | Specification attributes for products |
| `sizes` / `thicknesses` / `finishes` | Product attribute lookup tables |
| `paint_types` / `colors` / `color_effects` | Paint-specific attribute tables |
| `pallets` | Pallet/shipping unit definitions |
| `pallet_addresses` | Delivery addresses for pallets |
| `state_taxes` | US state tax rate lookup |
| `saved_lists` | User-created material lists (shopping lists) |
| `saved_list_items` | Line items within a saved list |
| `quotes` | Estimates / project quote headers |
| `quote_items` | Line items in a quote (product, variation, qty, price) |
| `orders` | Purchase orders derived from approved quotes |
| `order_items` | Line items in an order |
| `user_products` | Seller's personal product catalogue (workspace) |
| `user_services` | Seller's service offerings (workspace) |
| `user_product_prices` | Custom pricing per user-product |
| `customers` | CRM: buyer contacts tracked by a seller org |
| `customer_activity_logs` | Activity log entries per customer |
| `blogs` | Platform blog / announcement posts |
| `plan_crosswalk` | Project-scoped mapping: plan line code → SKU → manufacturer part number |
| `rfq_requests` | RFQ header (buyer org, title, deadline, items) |
| `rfq_recipients` | Which seller orgs received the RFQ |
| `rfq_responses` | Seller price quotes in response to an RFQ |

### RBAC Tables

| Table | Purpose |
|---|---|
| `organizations` | Multi-tenant organisation records (name, org_type, team_size) |
| `roles` | 49 platform roles across 5 functional clusters |
| `permission_groups` | 25 permission domains (e.g. `estimate_management`) |
| `role_permissions` | 365 rows mapping `role_id → permission_group_id → access_level` |
| `user_org_roles` | Which roles a user holds in which organisation |
| `org_relationships` | Active trading/structural links between two organisations |
| `project_members` | Users who are members of a specific quote/project |
| `delegations` | Time-bound role impersonation grants |
| `api_tokens` | Hashed bearer tokens for service accounts |
| `rbac_audit_logs` | Immutable log of every permission check |
| `rbac_settings` | Key-value store for per-org RBAC configuration |
| `sod_conflict_rules` | Pairs of roles that must not be held simultaneously |
| `role_assignment_logs` | History of every role grant / revocation |
| `org_invites` | Pending email invitations to join an organisation |

### Migration History (Chronological)

The migration timeline reflects the project's build phases:

- **Initial framework** (2024-06-10): state taxes, users, cache, jobs
- **Product catalogue** (2025-01-01 to 2025-08-05): divisions, specs, products, variations, colors, finishes, pallets, pallet addresses
- **Commerce layer** (2026-01-16): quotes, quote items, user product prices
- **CRM** (2026-04-16): customers, customer activity logs
- **Workspace** (2026-04-20): user products, user services (personal catalogues)
- **RBAC** (2026-06-25): organisations, roles, permission groups, role_permissions, user_org_roles, org_relationships, project_members, audit logs, delegations, API tokens
- **Advanced RBAC** (2026-07-07 to 2026-08-03): SOD rules, role assignment logs, RBAC settings, org invites
- **RFQ and approval** (2026-09-01): plan crosswalk, order approval columns, RFQ tables

---

## 6. RBAC System

The RBAC system is the centrepiece of Wisselbanken. It is a custom-built, multi-tenant, project-scoped permission engine with five enforcement layers.

### 6.1 Access Levels

All permissions use a strict five-level hierarchy. A user with a higher level automatically satisfies all lower-level requirements:

| Level | Symbol | What it means |
|---|---|---|
| Full | F | Create, read, update, delete — unrestricted within the org |
| Approve | A | Approve actions submitted by others |
| Own | O | Act only on own or org-owned records |
| Submit | S | Create or request (cannot approve own submissions) |
| Read | R | View only |

### 6.2 Permission Groups (25 total)

| Slug | Name |
|---|---|
| `organization_management` | Organization Management |
| `user_management` | User Management |
| `project_management` | Project Management |
| `estimate_management` | Estimate Management |
| `procurement` | Procurement |
| `product_management` | Product Management |
| `pricing_management` | Pricing Management |
| `approval_authority` | Approval Authority |
| `financial_access` | Financial Access |
| `manufacturer_controls` | Manufacturer Controls |
| `reporting_and_analytics` | Reporting & Analytics |
| `ai_extraction_review` | AI Extraction Review |
| `system_administration` | System Administration |
| `quote_rfq_management` | Quote / RFQ Management |
| `contract_and_agreement_management` | Contract & Agreement Management |
| `invoice_and_payment_processing` | Invoice & Payment Processing |
| `order_fulfillment` | Order Fulfillment |
| `returns_and_rma` | Returns & RMA |
| `catalog_taxonomy_and_data_quality` | Catalog Taxonomy & Data Quality |
| `compliance_and_certification` | Compliance & Certification |
| `budget_and_cost_control` | Budget & Cost Control |
| `delegation_and_impersonation` | Delegation & Impersonation |
| `audit_and_logging` | Audit & Logging |
| `seller_payout_and_settlement` | Seller Payout & Settlement |
| `inventory_and_availability` | Inventory & Availability |

### 6.3 Roles (49 total, across 4 deployment phases)

**Phase 1 — Core (immediately active):**
`platform_super_admin`, `platform_data_reviewer`, `platform_catalog_admin`, `organization_owner`, `organization_admin`, `executive_approver`, `procurement_manager`, `requisitioner`, `project_manager`, `estimator`, `viewer_read_only`, `manufacturer_admin`, `product_manager`, `order_fulfillment_csr`, `catalog_data_steward`, `auditor_read_all`, `integration_service_account`

**Phase 2 — Extended (second rollout):**
`platform_operations`, `platform_finance_billing`, `financial_admin`, `procurement_coordinator`, `ap_invoice_clerk`, `contract_manager`, `budget_owner`, `architect`, `engineer`, `compliance_certifications`, `inventory_manager`, `delegate_proxy`, `pricing_manager`, `sales_rep`

**Phase 3 — Specialist (third rollout):**
`platform_support`, `platform_compliance_officer`, `api_system`, `project_engineer`, `superintendent`, `specifier`, `consultant`, `designer`, `spec_reviewer_ahj`, `technical_rep`, `account_manager`, `returns_rma_handler`, `logistics_coordinator`, `subcontractor_pm`, `foreman`, `installer`, `qa_qc`, `field_viewer`

### 6.4 How Permission Checks Work

`PermissionService::checkPermission(userId, orgId, permissionGroup, requiredLevel, ?projectId)` resolves as follows:

1. Loads the active roles for `(userId, orgId)` from `user_org_roles`.
2. Checks if any active delegation grants an elevated role for this user.
3. Uses `PermissionMatrix` (a cached `role_id → group_slug → level` map, 10-minute TTL) to look up each role's level for the requested group.
4. Returns `true` if the best level found satisfies the hierarchy requirement (F ≥ A ≥ O ≥ S ≥ R).
5. If `projectId` is provided, additionally verifies the user has an active `project_members` record for that project in this org.

### 6.5 RbacAudit Middleware

Every authenticated route that appears in `config/route_permission_map.php` is checked by this middleware. The map entry format is:

```php
'POST estimates/{quote}/items' => [
    'estimate_management', 'S',
    'batch' => 'write',
    'project_param' => 'quote',
],
```

- `batch` controls Phase 3 rollout: set `RBAC_ENFORCE_BATCHES=admin,read,write,approve` to enable full enforcement.
- `project_param` tells the middleware which route parameter holds the project/quote ID for project-scoped checks.

### 6.6 Enforcement Batches (Phase 3 Rollout Order)

| Batch | Risk level | What it covers |
|---|---|---|
| `admin` | Lowest | Platform-admin management operations |
| `read` | Low | Read-only data fetches |
| `write` | Medium | Create / submit / update operations |
| `approve` | Highest | Approve / delete operations |

### 6.7 Blade Directives

```blade
@canDo('estimate_management', 'S')
    <button>Create Line Item</button>
@endCanDo

@cannotDo('approval_authority', 'A')
    <p>You do not have approval authority.</p>
@endCannotDo
```

Both directives accept an optional third argument for project-scoped checks: `@canDo('estimate_management', 'S', $projectId)`.

### 6.8 Separation of Duties (SOD)

`SodConflictRule` records pair roles that must not be held simultaneously by the same user in the same org (e.g. a user who submits purchase orders cannot also be the executive approver for those same orders). `SodService::checkConflict(userId, orgId, newRoleId)` returns the conflicting rule before a role assignment is committed.

### 6.9 Delegations

`DelegationService` implements time-bound impersonation. A user with `delegation_and_impersonation:O` can grant a colleague a higher role for a defined period. While a delegation is active, `PermissionService` factors the delegated role into the permission check. All delegation grants, activations, and expirations are written to `rbac_audit_logs`.

### 6.10 API Tokens (Service Accounts)

`ServiceAccountService` issues hashed bearer tokens stored in `api_tokens`. Each token is scoped to an org and linked to the `integration_service_account` role, giving automated integrations the exact permission surface of that role — no more. Tokens have optional expiry dates and can be revoked immediately.

### 6.11 Onboarding Role Bundles

When a new organisation completes registration, `OrgOnboardingService::seedOrgRoles()` reads the org type and team size from `config/rbac.php` and auto-assigns a starting set of roles so a solo operator does not need to manually assign 4–5 roles on their first login:

| Bundle | Triggered when |
|---|---|
| `buyer_small` | Org type is subcontractor / general_contractor / owner **and** team size is solo or 2–5 |
| `manufacturer_small` | Org type is manufacturer / distributor / stocking **and** team size is solo or 2–5 |
| `owner_only` | All other combinations (larger teams or specialist org types) |

### 6.12 Peel-Off Mechanism

`RoleAssignmentService` implements the "peel-off" pattern: when an org grows and a user who started as `organization_owner` (full access) needs to hand off a function to a specialist, the owner can assign the specialist role to a new user and then remove that permission group from their own role — without losing the rest of their access. The peel-off is logged in `role_assignment_logs`.

---

## 7. Organization Types and Relationships

### 7.1 Organization Types (19)

| Slug | Name | Purpose |
|---|---|---|
| `owner` | Owner | Property owner / developer |
| `architect` | Architect | Architectural firm |
| `engineering_firm` | Engineering Firm | Engineering consultant |
| `general_contractor` | General Contractor | General contractor |
| `subcontractor` | Subcontractor | Trade contractor |
| `manufacturer` | Manufacturer | Product manufacturer |
| `fabricator` | Fabricator | Custom fabricator |
| `distributor_stocking` | Distributor (Stocking) | Holds inventory on-hand |
| `distributor_non_stocking` | Distributor (Non-Stocking) | Drop-ships direct from manufacturer |
| `logistics_provider` | Logistics Provider | Shipping / logistics company |
| `consultant` | Consultant | External consultant / advisor |
| `service_vendor` | Service Vendor | Ancillary service provider |
| `platform_internal` | Platform Internal | Wisselbanken internal operations |
| `buying_group_gpo` | Buying Group / GPO | Group purchasing org |
| `manufacturer_s_rep_sales_agency` | Manufacturer's Rep / Sales Agency | Independent agency selling for multiple manufacturers |
| `testing_certification_body` | Testing / Certification Body | Issues product certifications |
| `government_ahj` | Government / AHJ | Authority having jurisdiction |
| `rental_equipment_provider` | Rental / Equipment Provider | Equipment and tool rental |
| `financial_surety_partner` | Financial / Surety Partner | Lender, factoring, or surety partner |

### 7.2 Org Relationship Types

The `org_relationships` table stores directed links between organisations. `OrgRelationshipService` provides the central API for querying and enforcing these links.

| Relationship type | Meaning |
|---|---|
| `buyer_seller` | Trading partnership: buyer can issue RFQs to seller |
| `gc_subcontractor` | GC and sub can collaborate on the same project workspace |
| `distributor_manufacturer` | Distributor has manufacturer authorisation to sell their products |
| `manufacturer_rep_agency` | Rep agency has an active principal manufacturer |
| `gpo_member` | Organisation is a GPO member (pricing benefits apply) |

### 7.3 Relationship Enforcement

**Before an RFQ response is submitted:**
- Buyer→Seller `buyer_seller` relationship must be active (`OrgRelationshipService::hasActive()`).
- If the seller is a distributor (`distributor_stocking` or `distributor_non_stocking`), an active `distributor_manufacturer` relationship must exist.
- If the seller is a rep agency (`manufacturer_s_rep_sales_agency`), an active `manufacturer_rep_agency` relationship must exist.

**Before a project workspace is accessed:**
- If the accessing org differs from the project creator's org, an active `gc_subcontractor` relationship must exist between the two (`OrgRelationshipService::hasActiveEither()`).

### 7.4 OrgRelationshipService API

| Method | What it does |
|---|---|
| `hasActive(fromOrgId, toOrgId, type)` | Checks directed active relationship A → B |
| `hasActiveEither(orgA, orgB, type)` | Checks active relationship in either direction |
| `partnerIds(orgId, type)` | Returns IDs of all active partners of a given type |
| `canInteract(orgA, orgB)` | True if any active relationship exists between the two orgs |
| `hasAnyActive(orgId, type)` | True if the org has ANY active outgoing relationship of the given type |
| `sellerAuthorizationError(sellerOrgId, sellerOrgType)` | Returns `null` if authorised, or an error message string if not |

---

## 8. Feature Modules

### 8.1 Product Catalogue

**Admin controllers:** `Admin\ProductController`, `Admin\ManufacturerController`, `Admin\DivisionController`, `Admin\ColorController`, `Admin\ColorEffectController`, `Admin\FinishController`, `Admin\ThicknesController`, `Admin\SizeController`, `Admin\SpecificationController`, `Admin\PaintTypeController`, `Admin\ProductFileController`, `Admin\ProductPricingController`

The product catalogue supports a flexible attribute model: a product has one division, one manufacturer, and any number of specifications. Variations are child records that carry size, thickness, finish, and color assignments. Product files (spec sheets, drawings) are attached to the product level.

### 8.2 Quote / Estimate Builder

**Controller:** `Frontend\QuoteController`

Users create a quote (project estimate), add line items referencing product variations, set quantities and per-unit prices, attach a project address, and export the estimate as a PDF (via DomPDF). Quotes move through a status lifecycle: `draft → pending_approval → approved → converted_to_order`.

### 8.3 Approval Workflow

**Controller:** `Frontend\OrderApprovalController`

When a quote is submitted for approval, the `ApprovalRoutingService` checks whether the submitting user is the sole member with `approval_authority:A` in the org. If so, it auto-approves (solo-operator shortcut). Otherwise, it routes to the designated approver. Approvers see a pending queue in their workspace and can approve or reject with comments.

### 8.4 RFQ (Request for Quotation)

**Controllers:** `Frontend\RfqController` (buyer side), `Frontend\RfqSellerController` (seller side)

**Buyer flow:**
1. Create an RFQ with a title, deadline, and item descriptions.
2. Select recipient seller orgs from those with active `buyer_seller` relationships.
3. Submit — recipients see it in their incoming RFQ panel.

**Seller flow:**
1. View incoming RFQs in `rfq_recipients`.
2. Respond with a total price, validity date, and notes — creating an `rfq_responses` record.
3. Or decline, updating the recipient status to `declined`.

Authorization checks at both ends ensure the relationship mesh is intact before any action is allowed.

### 8.5 Order Management

**Admin controller:** `Admin\OrderController`  
**Frontend controller:** `Frontend\OrderController`, `Frontend\CheckoutController`

Orders are created from approved quotes. They carry buyer/seller org references, line items with unit prices, and a project title. The order lifecycle includes a three-way match gate (PO → receipt → invoice) enforced through the `invoice_and_payment_processing` permission group.

### 8.6 Plan Crosswalk

**Controller:** `Frontend\PlanCrosswalkController`

The plan crosswalk is a project-scoped table that maps architectural plan line codes to platform SKUs and manufacturer part numbers. It is used by estimators to translate drawing callouts into purchasable items without leaving the platform. Entries are scoped to `(org_id, quote_id)` so different teams on the same project maintain their own mappings.

### 8.7 Project Workspace

**Controller:** `Frontend\ProjectWorkspaceController`

A per-project surface that shows all estimate items, project members, and crosswalk entries for a single quote. Access requires:
- `estimate_management:R` in the user's org for the given project ID (project-scoped permission check).
- If the accessing org differs from the project creator's org, an active `gc_subcontractor` relationship must exist.

Members can be added from within the workspace by users with `project_management:F`. The member list is filtered to the current org.

### 8.8 User Workspace / Dashboard

**Controller:** `Frontend\UserWorkspaceController`

The primary entry screen after login. It shows:
- **My Projects**: quotes where the user has an active `project_members` record, gated by `estimate_management:R`.
- **Pending outgoing RFQs**: open RFQs the org has sent, gated by `quote_rfq_management:S`.
- **Incoming RFQs**: RFQs where the org is a recipient in `pending` status, gated by `quote_rfq_management:S`.
- **Pending approvals**: quotes in `pending_approval` status within the user's projects, gated by `approval_authority:A`.
- **Role summary**: the user's active roles in the current org.
- **Quick links**: context-aware navigation (manage projects, create estimate, etc.) shown via `@canDo` directives.

### 8.9 Org Admin

**Controller:** `Frontend\OrgAdminController`, `Frontend\OrgSettingsController`

Org administrators can:
- View all members and their role assignments.
- Invite new members by email (creates an `org_invites` record and sends a link).
- Assign roles using the peel-off mechanism (`RoleAssignmentService`).
- Remove roles (the removed role is logged in `role_assignment_logs`).
- Edit org settings (name, address, team size).
- Transfer ownership to another member.
- View the org's segment of the audit log.

### 8.10 Org Switching

**Controller:** `Frontend\OrgSwitchController`

A user who holds roles in multiple organisations can switch their active organisation from the navigation. The chosen org ID is stored in the session key `rbac_current_org_id` and all subsequent queries are scoped to it. The switch is instantaneous and does not require a page reload.

### 8.11 Delegations

**Controller:** `Frontend\DelegationController`

Users with `delegation_and_impersonation:O` can grant a time-bound delegation to a colleague. The delegation specifies:
- The target user.
- The role to delegate.
- A start date and an end date.

While active, `PermissionService` treats the delegate as holding the delegated role. All actions taken under a delegation are flagged in `rbac_audit_logs` with the original user's identity preserved.

### 8.12 API Tokens

**Controller:** `Frontend\ApiTokenController`

Service accounts (automated integrations, CI pipelines, ERP connectors) authenticate with bearer tokens rather than session cookies. Tokens are created through the Org Admin interface, scoped to the org, and linked to the `integration_service_account` role. The raw token is shown once at creation; only the hash is stored in `api_tokens`.

### 8.13 Customer CRM

**Controller:** `Frontend\CustomerController`

Seller orgs maintain a lightweight CRM of their buyer contacts. Each customer record stores name, email, phone, company, and address. Activity logs track interactions. Soft-delete is supported — deleted customers are hidden from the main list but retained for audit purposes.

### 8.14 Personal Catalogue (Workspace)

**Controllers:** `Frontend\UserProductController`, `Frontend\UserSavedServiceController`

Sellers can maintain a personal product and services catalogue separate from the platform master catalogue. Products can be linked to master catalogue variations or defined as custom items. Services have a name, description, unit, and price. Both appear in the estimate builder as selectable line items.

### 8.15 Saved Lists

**Controller:** `Frontend\ListController`

Any authenticated user can create named material lists (shopping lists / takeoff lists). Items are added from the product catalogue or from product search results. Saved lists can be converted to quote line items in one action.

### 8.16 Pallets and Shipping

**Controller:** `Frontend\PalletProductController`

Pallet records group products into shipping units. Each pallet can have one or more delivery addresses. Pallet addresses carry a project name, recipient name, and full street address.

### 8.17 Blog / Announcements

**Admin controller:** `Admin\BlogController`

Platform operators publish blog posts and announcements. The blog is content-managed entirely through the admin panel. Published posts are visible to all authenticated users on the frontend.

---

## 9. Services Layer

### `PermissionService`

The single source of truth for all access control decisions.

- `checkPermission(userId, orgId, permissionGroup, requiredLevel, ?projectId): bool`
- `canInteractWithOrg(userId, orgId): bool` — shortcut for org-level access
- `partnerOrgIds(orgId, relationshipType): Collection` — ids of partner orgs

### `PermissionMatrix`

A cached lookup table (`role_id → permission_group_slug → access_level`), rebuilt from `role_permissions` and stored in the cache with a 10-minute TTL. Invalidated automatically when role permissions are updated via the role designer.

### `OrgRelationshipService`

All cross-org enforcement routes through this service. Methods: `hasActive`, `hasActiveEither`, `partnerIds`, `canInteract`, `hasAnyActive`, `sellerAuthorizationError`. See §7.4 for the full API.

### `OrgOnboardingService`

Called once at org registration. Seeds the `user_org_roles` table with the onboarding bundle appropriate for the org type and team size.

### `RoleAssignmentService`

Handles role grants and revocations with SOD checking, assignment logging, and the peel-off mechanism. Every call writes a record to `role_assignment_logs`.

### `DelegationService`

Creates, validates, and expires delegations. Integrates with `PermissionService` so delegated roles are factored into permission checks transparently.

### `ServiceAccountService`

Issues, hashes, and validates API bearer tokens. Token lookups are O(1) via the hash index on `api_tokens`.

### `SodService`

`checkConflict(userId, orgId, newRoleId): ?SodConflictRule` — checks `sod_conflict_rules` before any role assignment. Returns the conflicting rule if found, `null` if safe to proceed.

### `ApprovalRoutingService`

Determines who should receive an approval request for a given quote. Implements the solo-operator auto-approve: if the submitting user is the only active user with `approval_authority:A` in the org, the quote is auto-approved immediately.

---

## 10. Frontend Architecture

### Blade Templates

Views live in `resources/views/` and are split into two namespaces:

- `admin/` — Platform admin panels. Use DataTables for server-side pagination.
- `user/` — User-facing screens. Structured around the workspace metaphor.

Key user-facing view directories:

| Path | Contents |
|---|---|
| `user/dashboard.blade.php` | User workspace / landing page |
| `user/project-workspace/` | Per-project workspace screens |
| `user/org-admin/` | Org member management, delegations, API tokens |
| `user/rfq/` | Buyer RFQ creation and seller incoming RFQ panels |
| `user/quotes/` | Estimate builder and PDF export |
| `user/orders/` | Order history and details |
| `user/products/` | Product browsing and personal catalogue |

### Blade Directives

```php
// AppServiceProvider registers these:
Blade::directive('canDo', function ($expression) { ... });
Blade::directive('endCanDo', function () { ... });
Blade::directive('cannotDo', function ($expression) { ... });
Blade::directive('endCannotDo', function () { ... });
```

### Vite Build Pipeline

`vite.config.js` compiles:
- `resources/sass/app.scss` → Bootstrap 5 + custom Sass → `public/build/assets/app.css`
- `resources/js/app.js` → Alpine.js + axios + custom modules → `public/build/assets/app.js`
- Tailwind CSS is applied as a PostCSS plugin during the Sass compile.

For development with hot reload:

```bash
npm run dev
```

For production:

```bash
npm run build
```

---

## 11. Configuration Reference

### `config/rbac.php`

| Key | Type | Purpose |
|---|---|---|
| `mode` | string | `audit` or `enforce` — controls whether failed permission checks block requests |
| `enforce_batches` | array | Subset of `[admin, read, write, approve]` to enforce in Phase 3 |
| `current_org_session_key` | string | Session key storing the active org ID (`rbac_current_org_id`) |
| `team_sizes` | array | Valid team size options shown at registration |
| `small_team_sizes` | array | Sizes that trigger a full onboarding bundle |
| `onboarding.bundles` | array | Role lists per bundle: `buyer_small`, `manufacturer_small`, `owner_only` |

### `config/route_permission_map.php`

Maps `"METHOD uri"` strings to permission requirements. See §6.5 for the format. This file is the contract between the router and the RBAC middleware — any route not listed here passes through RbacAudit without a permission check.

---

## 12. Development Workflow

### Running the Development Stack

```bash
# Terminal 1: Laravel server
php artisan serve

# Terminal 2: Vite hot-reload
npm run dev
```

### Re-seeding RBAC Data

If you modify role or permission group data files under `database/seeders/Rbac/data/`, re-seed without touching other data:

```bash
php artisan db:seed --class="Database\Seeders\Rbac\RoleSeeder" --force
php artisan db:seed --class="Database\Seeders\Rbac\PermissionGroupSeeder" --force
php artisan db:seed --class="Database\Seeders\Rbac\RolePermissionSeeder" --force
```

### Clearing the Permission Cache

`PermissionMatrix` caches in the configured cache driver. Force a rebuild after changing role permissions:

```bash
php artisan cache:clear
```

### Code Style

The project follows PSR-12. Run checks with:

```bash
./vendor/bin/pint
```

### Key Artisan Commands

| Command | What it does |
|---|---|
| `php artisan migrate` | Run pending migrations |
| `php artisan migrate:fresh --seed` | Drop all tables, re-migrate, re-seed (dev only) |
| `php artisan db:seed --class=RbacSeeder --force` | Re-seed RBAC structure |
| `php artisan cache:clear` | Clear all caches (rebuilds PermissionMatrix on next request) |
| `php artisan route:list` | List all registered routes |
| `php artisan serve` | Start the built-in PHP development server |

---

## Appendix: Current Branch State

The active development branch is `release/3.0`. The RBAC system is in **enforce mode** with all four batches active. Current audit log contains 123 entries and is reviewed weekly.

Outstanding items tracked for the next milestone:
- Contract / price list feature (new data models required: `contracts`, `price_lists` tables).
- GPO member pricing enforcement in the RFQ and order flow.
- Seed test data into `org_relationships` for integration testing.
- Week 12: full QA across all 49 roles, documentation handover, and client sign-off.
