# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

AGROVISE is a plain-PHP (no framework, no Composer, no build step, no tests) application for an agriculture products business, running on WAMP (Windows/Apache/MySQL/PHP 8.x). It is two apps in one codebase:

1. **Public marketing site** — `index.php` and `categories/*.php`. Shows only products where `is_published = TRUE`.
2. **Admin panel / ERP** — everything under `admin/`. Covers products, purchasing (stock lots), packing, invoicing, sales policies, payment receipts, banking, clients/vendors/employees, ledgers, and public-site traffic analytics.

Both wear the "Harvest Editorial" design (forest-ink/ivory/wheat-gold, Cormorant Garamond + Jost).

## Running & database

- Served by WAMP Apache at `http://localhost/agrovise/`; admin login at `http://localhost/agrovise/admin/login.php` (default credentials: admin / admin123; login accepts username or email).
- DB connection constants live in `includes/db.php` (MySQL `agrovise_db` on localhost, user `root`, empty password).
- **Fresh install: import `agrovise_db.sql`** (mysqldump at the repo root — the schema source of truth, includes the default admin). There is no installer script.
- There is no lint/test/build tooling. Verify changes by loading the affected page in the browser. PHP CLI for syntax checks: `f:/wamp64/bin/php/php8.3.28/php.exe -l <file>`.
- `LOGIC_CHANGES.md` logs every logic fix/feature with before/after code; `_removed_backup_2026-07-04.zip` holds files deleted in the 2026-07 cleanup.

## Page architecture

Every page is self-contained PHP: logic at the top (POST handling, `?delete=<id>`-style GET actions, redirects), then a full HTML document. Admin pages all start with:

```php
require_once '../includes/db.php';
require_once '../includes/functions.php';
requirePermission('<module>');   // or requireAdminLogin() on dashboard/ajax
```

- `includes/db.php` — `getDBConnection()` returns a PDO handle (exceptions on, assoc fetch default). Prepared statements are used throughout; keep doing that.
- `includes/functions.php` — runs the **security bootstrap** at include time (hardened `session_start` with HttpOnly/SameSite/strict-mode cookie, security response headers, and auto-CSRF verification of every logged-in POST) and holds all shared helpers: auth + RBAC (`requirePermission`, `hasPermission`, `isSuperAdmin`, `adminModules`), flash messages, image upload/path resolution, `sanitize()` for output escaping, category maps, `generateRandomPassword()`, plus the security helpers `csrfToken`/`csrfField`/`verifyCsrf` and `dbError` (logs the real exception, shows a generic message).
- **Security posture:** SQL is 100% parameterized (PDO prepared statements everywhere — keep it that way). Every `<form method="POST">` must include `<?php echo csrfField(); ?>` (auto-verified on submit). Never echo `$e->getMessage()` to the browser — use `dbError($e)`. File uploads go through `uploadImageFile()` which content-verifies via `getimagesize()` and forces the extension. Three `.htaccess` files enforce web-server rules: root blocks download of `.sql`/`.md`/`.zip`/dotfiles + no dir listing; `uploads/` disables script execution (data-only); `includes/` denies all direct HTTP access. Bump nothing here casually — these are load-bearing.
- **`includes/admin-sidebar.php` — the shared admin sidebar.** Every admin page requires it in place of the old copy-pasted `<aside>`; a nav change means editing this one file. Links render only when `hasPermission('<module>')` passes.
- **`includes/category-page.php` — the shared public category template.** The five `categories/*.php` files are 8-line stubs that set `$category` + `$metaTail` and require it (URLs unchanged). Clicking a product card opens a detail overlay, which is also what records a product view.
- **`includes/tracking.php` — public-site traffic tracking.** `trackPageView()` (called by `index.php` and the category template) and `trackProductView()` (called by the root `track.php` beacon, which the detail overlay POSTs to) write `site_visits` / `product_views`. Both skip bots and logged-in staff and swallow their own DB errors — tracking must never break the public site. Visitors are an anonymous 1-year `av_vid` cookie. Reported in `admin/traffic.php` (module `traffic`), including a per-visitor journey drill-down.
- **`includes/policy-items.php` — the shared Policy Calculator grid** used by `add-policy.php`, `edit-policy.php` and `policy-calculator.php`, so all three price a policy identically.
- **Role-based access:** `admins` accounts have `role` ('admin' = full access; 'user' = only modules in the `permissions` JSON) and may link to an employee via `employee_id`. Modules: products, purchasing, vendors, packing, invoices, policies, payments, clients, banking, ledgers, vehicles, expenses, traffic (defined in `adminModules()`); employee/account management (incl. `designations.php`) is admin-only and never grantable. Logins are created/managed from `add-employee.php`/`edit-employee.php`. When adding a new admin page, guard it with `requirePermission()` and add its sidebar entry to the shared include (grouped drop-down menus; group auto-opens on its pages).
- **Self-service pages** (any logged-in account): `my-profile.php` (account/employee info, sales, salary, expense summary, issued vehicle + monthly odometer submission into `vehicle_readings` — one per vehicle per month) and `expenses.php` (file claims with bill image → `uploads/expenses/`; approvers = admins + 'expenses' users review there). **Expense claim lifecycle:** WAITING → APPROVED/DECLINED (notifies claimant) → PAID via `add-transaction.php` category "Employee Expenses" (auto-fills approved amount, marks claim PAID with `paid_via` BANK/CASH derived from the account name, links `paid_transaction_id`). Employees only ever see Bank/Cash — never account names. In-app `notifications` fan out per target account and show as a badge in the sidebar.
- `admin/ajax_endpoints.php` — the only JSON API (`?action=search_clients|get_client|get_product`), used by dynamic invoice/transaction forms.
- **Messaging:** `settings` (key/value) + `outbound_messages` tables; admin-only `admin/messaging.php` authorizes the company sender number and dispatches queued messages via WhatsApp click-to-send links (`waSendLink()`). Invoicing queues officer+client messages; a Purchasing withdrawal with a vendor queues the vendor confirmation (both after commit, failure-proofed via `queueOutboundMessage()`). `add-transaction.php` also has optional payment pickers: vendor purchase lots, employee salary, client outstanding — none required to submit.
- `admin/dump_schema.php` — admin-only diagnostic that prints the live schema.
- CRUD naming pattern: list page `things.php`, plus `add-thing.php` and `edit-thing.php`.
- Styling: one shared `assets/css/style.css` (public styles + scoped `.admin-body` "Harvest Editorial" overrides at the end); pages link it as `style.css?v=ed2` — bump the version tag when changing CSS. Fonts/Font Awesome via CDN.

## ERP domain model (the part that spans many files)

- `products` is the catalog; `is_published` gates public-site visibility (toggled in `admin/publish-products.php`).
- **`purchasing` rows are stock lots, and `purchasing.quantity` is the live stock level.** Each lot has a batch number and a type: `BULK`, `PACKING` (materials), `FINISHED`, or `OTHERS`.
- Packing (`packing_operations` + `packing_materials_used`) consumes a BULK lot plus PACKING-material lots and produces a FINISHED lot, carrying costs forward.
- Invoices (`invoices` + `invoice_items`) sell from a specific `purchasing` lot: `invoice_items.purchasing_id` points at the lot, and creating an invoice decrements that lot's quantity inside a DB transaction (editing restores + re-deducts). Invoice/PR numbers are generated as `INV-`/`PR-` + zero-padded `MAX(id)+1`.
- **Sales policies (`policies` + `policy_items`) are named product bundles at agreed prices** (e.g. "Eid Policy"), with an optional validity window and an ACTIVE/INACTIVE status. A policy's price comes from the Policy Calculator: `total = Σ (quantity × price) × (1 + tax/100)` — the same formula invoices use, so applying a policy reproduces its price exactly. `add-invoice.php` can apply an active in-window policy, which fills the invoice grid with its products/quantities/prices and records `invoices.policy_id`. Policy lines bind to **products, not stock lots** (a seasonal price list must not be pinned to a batch that depletes); on apply, each line is matched to a lot at that moment — FINISHED lot with enough stock preferred, then any lot that covers it, then the fullest — and out-of-stock/short products are reported to the user rather than silently dropped.
- Money flows through `banking` (accounts with a `balance` column) and `transactions` (linked to a bank account and optionally a vendor, employee, or client). `payment_receipts` records client payments: creating one adds a DEPOSIT transaction and bumps the bank balance; editing/deleting keeps all three records in sync.
- Ledger/report pages (`ledger.php`, `ledger-client.php`, `ledger-product.php`, `detailed-report.php`, `analytics.php`) derive balances from invoices vs. payment receipts and transactions — they don't have their own tables. `ledger-export.php` streams 12 detailed CSV reports (P/L monthly/yearly/by-area, COGS, products sold, bulk buys, buy-vs-sell, expenses, salaries, client ledger, officer receivables, stock+expiry) with an optional `?year=` filter.
- **Sales officers:** `clients.employee_id` assigns a dealer's sales officer; invoices (`invoices.employee_id`) and payment receipts (`payment_receipts.employee_id`) carry that officer (add-invoice/add-pr default it from the client). Officer receivable = Σ their invoices − Σ payments collected through them. A `user` account with no granted modules is a sales officer: `landingPage()` sends it to `my-profile.php` (its personal sales dashboard) at login, `dashboard.php` bounces it there, and the sidebar hides Dashboard — they get only My Space until an admin grants modules. The admin dashboard has an area-selectable Profit/Loss chart. `purchasing.expiry_date` feeds the stock report.
- Note: `admins` is MyISAM (no transactions); account uniqueness is checked before inserts rather than relying on rollback.

## Schema-evolution convention

For schema changes: write an idempotent `addColumnIfMissing`-style PHP script, run it once, **refresh `agrovise_db.sql`** (`f:/wamp64/bin/mysql/mysql8.4.7/bin/mysqldump.exe -u root --databases agrovise_db --add-drop-table --result-file=agrovise_db.sql`), then delete the script (the 2026-07 cleanup removed all historical one-off migration scripts; they live in the backup zip and their changes are documented in `LOGIC_CHANGES.md`).
