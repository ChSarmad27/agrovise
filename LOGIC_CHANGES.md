# LOGIC_CHANGES.md

Log of every **logic** change made during the full QA pass on 2026-07-04.
Pure view/design work (the dashboard redesign and the 3D motion effects on
`index.php`) changed **no** business logic and is summarized at the end.

QA method: `php -l` syntax check on all 60+ PHP files, live-schema comparison
against every SQL statement in the code, CLI render tests of each page with a
simulated admin session, and read-only smoke tests of the fixed queries
against the live `agrovise_db` database.

---

## 1. `admin/edit-invoice.php` — "Edit" created a duplicate invoice (CRITICAL)

**Fault.** The file was a stale copy of `add-invoice.php` (its header comment
even said "Add Invoice"). Clicking Edit on `invoices.php` opened a blank
"create" form; saving it **INSERTed a brand-new invoice** with a new number
and **deducted stock a second time**. Editing an invoice was impossible and
silently corrupted stock and sales totals.

**Before (core of the fault):**
```php
// Generate Invoice No
$row = $conn->query("SELECT MAX(id) as max_id FROM invoices")->fetch();
$nextId = ($row['max_id'] ?? 0) + 1;
$invoiceNo = 'INV-' . str_pad($nextId, 6, '0', STR_PAD_LEFT);
...
$stmt = $conn->prepare("INSERT INTO invoices (invoice_no, client_id, date, total_amount) VALUES (?, ?, ?, ?)");
...
// Reduce stock in purchasing
$sStmt = $conn->prepare("UPDATE purchasing SET quantity = quantity - ? WHERE id = ?");
```

**After (true edit, all inside one DB transaction):**
```php
// 1. Restore stock held by the old items
$restore = $conn->prepare("UPDATE purchasing SET quantity = quantity + ? WHERE id = ?");
// 2. Remove old items
$conn->prepare("DELETE FROM invoice_items WHERE invoice_id = ?")->execute([$id]);
// 3. Validate new items against the restored stock
if ($lot['quantity'] < $item['quantity']) { throw new Exception("Not enough stock..."); }
// 4. Insert new items and deduct stock
// 5. Update invoice header (invoice_no unchanged)
$upd = $conn->prepare("UPDATE invoices SET client_id = ?, employee_id = ?, date = ?, total_amount = ? WHERE id = ?");
```

The form now loads pre-filled with the invoice's client, employee, date and
item rows, and the available-stock list counts the quantities this invoice
already holds as available for reallocation:
```sql
SELECT ..., p.quantity + IFNULL(held.qty, 0) as stock
FROM purchasing p
LEFT JOIN (SELECT purchasing_id, SUM(quantity) as qty
           FROM invoice_items WHERE invoice_id = ? GROUP BY purchasing_id) held
       ON held.purchasing_id = p.id
```

---

## 2. `admin/pr.php` — deleting a Payment Receipt left phantom money in the bank (CRITICAL)

**Fault.** `add-pr.php` creates three records per receipt: the PR row, a
DEPOSIT row in `transactions`, and a `+amount` on `banking.balance`.
`edit-pr.php` keeps all three in sync. But **delete** removed only the PR row,
so the bank balance stayed inflated and an orphan deposit transaction
remained — the books no longer balanced.

**Before:**
```php
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM payment_receipts WHERE id = ?");
    $stmt->execute([$id]);
    setFlashMessage('success', 'Payment receipt deleted successfully!');
    header('Location: pr.php');
    exit;
}
```

**After:** the delete runs in a DB transaction, finds the linked deposit using
the same description/amount/bank matching that `edit-pr.php` already uses,
reverts the balance, deletes the transaction, then deletes the PR:
```php
$descMatch = "Payment Received via PR: " . $pr['pr_no'];
$tStmt = $conn->prepare("SELECT id FROM transactions WHERE description = ? AND amount = ? AND bank_id = ?");
...
$conn->prepare("UPDATE banking SET balance = balance - ? WHERE id = ?")
     ->execute([$pr['amount_received'], $pr['bank_id']]);
$conn->prepare("DELETE FROM transactions WHERE id = ?")->execute([$trans['id']]);
...
$conn->prepare("DELETE FROM payment_receipts WHERE id = ?")->execute([$id]);
```
If no linked transaction exists (e.g. a PR created before bank-linking was
added — the live PR-000001 is such a case), only the PR is deleted, which is
the correct behaviour.

---

## 3. `admin/edit-account.php` — saving always failed on a nonexistent column (HIGH)

**Fault.** The UPDATE wrote a `bank_name` column that does not exist in the
live `banking` table (`id, account_name, account_number, balance, created_at`),
so **every save threw "Column not found"**. The form also read
`$account['bank_name']`, an undefined array key.

**Before:**
```php
$bank_name = trim($_POST['bank_name'] ?? '');
...
$stmt = $conn->prepare("UPDATE banking SET account_name = ?, account_number = ?, bank_name = ? WHERE id = ?");
$stmt->execute([$account_name, $account_number, $bank_name, $id]);
...
<input type="text" name="bank_name" class="form-input" value="<?php echo sanitize($account['bank_name']); ?>">
```

**After:** the `bank_name` field and column were removed from both the query
and the form (nothing else in the app ever used `bank_name`, so removing it —
not adding a column — matches the schema's intent):
```php
$stmt = $conn->prepare("UPDATE banking SET account_name = ?, account_number = ? WHERE id = ?");
$stmt->execute([$account_name, $account_number, $id]);
```

---

## 4. `includes/db.php` — `initDatabase()` could never complete on a fresh install (HIGH)

**Fault A.** It created the `transactions` table with
`FOREIGN KEY (bank_id) REFERENCES banking(id)` but **never created `banking`**
— on an empty MySQL server the CREATE fails and setup aborts.

**Fault B.** It created the `admins` table but seeded the default admin into
`admin_users`, a table that doesn't exist → fatal error, and even if it
existed, `login.php` reads from `admins`, so login would fail.

**Before:**
```php
// (no banking table anywhere)
$conn->exec("CREATE TABLE IF NOT EXISTS transactions ( ... FOREIGN KEY (bank_id) REFERENCES banking(id) ... )");
...
$stmt = $conn->prepare("INSERT IGNORE INTO admin_users (username, password) VALUES (?, ?)");
```

**After:**
```php
// Banking accounts table (must exist before transactions, which references it)
$conn->exec("CREATE TABLE IF NOT EXISTS banking (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_name VARCHAR(255) NOT NULL,
    account_number VARCHAR(100) DEFAULT NULL,
    balance DECIMAL(15,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
$conn->exec("CREATE TABLE IF NOT EXISTS transactions ( ... )");
...
$stmt = $conn->prepare("INSERT IGNORE INTO admins (username, password) VALUES (?, ?)");
```
The `banking` definition mirrors the live table from `agrovise_db.sql` exactly.

---

## 5. `install.php` — built an obsolete pre-ERP schema (HIGH)

**Fault.** The installer created its own tables: an `admin_users` table the
app never reads, and a `products` table with a `description` column that the
live code doesn't have — while skipping the 12 ERP tables the app depends on.
Running it produced a database the application cannot work with.

**Fix.** The duplicated schema was deleted; `install.php` now calls
`initDatabase()` from `includes/db.php` (fixed in #4) so there is a single
source of truth for the schema:
```php
require_once 'includes/db.php';
...
initDatabase();
```

---

## 6. `admin/add-invoice.php` — unassigned employee saved as `0` instead of `NULL` (MEDIUM)

**Fault.** `intval($_POST['employee_id'] ?? 0)` stores `0` when "-- Choose
Employee --" is left selected. `invoices.employee_id` is `DEFAULT NULL` by
design; the `0` values pollute the employee sales ledger queries
(`... FROM invoices WHERE employee_id = e.id`) and misreport "unassigned" as a
value. `add-transaction.php` already used the null pattern.

**Before:**
```php
$employee_id = intval($_POST['employee_id'] ?? 0);
```

**After:**
```php
$employee_id = !empty($_POST['employee_id']) ? intval($_POST['employee_id']) : null;
```

---

## 7. `admin/add-packing.php` — packing materials could go stock-negative (MEDIUM)

**Fault.** Bulk stock was validated before deduction, but packing-material
quantities were deducted with **no availability check**, allowing negative
stock in `purchasing` and a wrong material cost basis.

**Fix (added, mirroring the existing bulk-stock guard):**
```php
// Verify packing material stock (same guard as bulk, so materials can't go negative)
if (is_array($materials)) {
    $matCheck = $conn->prepare("SELECT quantity FROM purchasing WHERE id = ?");
    foreach ($materials as $mat) {
        ...
        if ($m_stock === false || $m_stock < $m_qty) {
            $errors[] = "Not enough packing material stock! Available: " . ...;
        }
    }
}
```

---

## 8. `admin/detailed-report.php` — fatal error on an invalid entity id (LOW)

**Fault.** `$entity = $stmt->fetch();` returns `false` for an unknown id, and
the next line `$entity['name']` is a fatal `TypeError` in PHP 8 (blank page).

**Fix (all three branches — client / vendor / employee):**
```php
$entity = $stmt->fetch();
if (!$entity) { header('Location: ledger.php'); exit; }
$title = "Client Statement: " . $entity['name'];
```

---

## 9. `admin/fix_banking_logic.php` — PHP parse error (LOW, dead code)

**Fault.** This one-off codemod (which injected delete handlers into
`banking.php`) contained unescaped single quotes inside a single-quoted string
(`'...isset($_GET['delete_account'])...'`) — a parse error, the only one in
the codebase. Its job is already done: `banking.php` contains the handlers.

**Fix.** Replaced with a clearly-marked retired stub that exits immediately,
so it parses cleanly and can never mangle `banking.php` if opened again.

---

## Feature: role-based access control (2026-07-04)

Not a bug fix — a requested feature, logged here for the record.

- **`admin/migrate_rbac.php`** (new, run once): extends `admins` with `email`,
  `role` ('admin'|'user'), `permissions` (JSON module list), `employee_id`,
  `created_at`; renames duplicate usernames (id 2 'admin' → 'admin2') and adds
  a UNIQUE index on username. Existing accounts default to full admin.
- **`includes/functions.php`**: new helpers — `adminModules()` (grantable
  module map), `loadAdminAccess()`, `isSuperAdmin()`, `hasPermission()`,
  `requirePermission()`, `generateRandomPassword()`. `requireAdminLogin()` now
  also loads the account's role/permissions into the session and logs out
  sessions whose account row was deleted.
- **`admin/login.php`**: accepts username **or** email; caches role +
  permissions in the session on sign-in.
- **All module pages** (35 files): `requireAdminLogin()` replaced with
  `requirePermission('<module>')`; unauthorized access flashes an error and
  bounces to the dashboard. Modules: products, purchasing, vendors, packing,
  invoices, payments, clients, banking, ledgers. Employee pages require the
  admin role (the 'employees' module is never grantable to Users).
- **Sidebar** (32 files, codemod like the repo's historical `fix_sidebar.py`):
  every sidebar link is wrapped in `hasPermission()` so users only see the
  modules they hold.
- **`admin/add-employee.php` / `edit-employee.php`**: "System Access" section —
  generate email/username/password, choose Admin (full access) or User with a
  per-module checklist; edit page can also reset passwords, change
  role/permissions, or remove access. Guards prevent demoting/removing the
  last Admin account. Credentials are shown once in the success flash.
- **`admin/employees.php`**: shows each employee's access level; deleting an
  employee also removes their linked login (blocked if it is the last Admin).
- **`admin/dashboard.php`**: KPIs, charts, panels, quick actions and the
  products table now render (and query) only for permitted modules; the
  product-delete action requires the products permission.
- **`agrovise_db.sql`** regenerated from the live database after migration.

## Feature — per-item sales tax on invoices (2026-07-04)

`invoice_items.sales_tax` (DECIMAL(5,2), default 0) stores a tax **percent
per line item**, since tax differs product to product. Blank or zero means
no tax.

- **`add-invoice.php` / `edit-invoice.php`**: each item row has an optional
  "Tax %" field. Line total = qty × price × (1 + tax/100); the invoice's
  `total_amount` (used by ledgers/receivables) therefore includes tax.
  Validation clamps negatives to 0 and rejects values over 100. On edit,
  a stored 0 shows as a blank field (zero = "not written").
- **`print-invoice.php`**: if **no** line carries tax, the printed document
  is byte-for-byte the same delivery challan as before — no mention of tax
  anywhere. If **any** line has tax, the items table gains Rate / Tax % /
  Amount columns and a Subtotal / Sales Tax / Grand Total block appears.
- Product-profitability reports intentionally keep using `ii.price`
  (net of tax) as revenue — sales tax is collected for the government and
  is not margin.

Verified end-to-end through the live endpoints: a 17% line produced
total 117.00 and printed with Subtotal/Sales Tax/Grand Total; a blank-tax
line produced total 100.00 and its print contained zero occurrences of the
word "tax"; both test invoices were then deleted and stock restored exactly.

## Security — ironclad hardening pass (2026-07-06)

Defensive hardening across the whole app; **no functionality changed** (verified
by live create/render tests). SQL was already 100% parameterized (audit found no
interpolated queries) — the gaps were CSRF, error leaks, upload trust, session
strength, and web-server exposure.

**Core bootstrap (`includes/functions.php`, runs on every page):**
- Hardened session cookie: `HttpOnly` (blocks JS cookie theft), `SameSite=Lax`
  (blocks CSRF via cookie), `Secure` when on HTTPS, `use_strict_mode`.
- Security headers on every response: `X-Content-Type-Options: nosniff`,
  `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy`, `Permissions-Policy`;
  `X-Powered-By` removed (hides PHP version).
- **CSRF protection**: `csrfToken()`/`csrfField()`/`verifyCsrf()`; every POST from
  a logged-in session is auto-verified with a constant-time `hash_equals`.
  A `csrfField()` hidden input was injected into all 29 admin POST forms + login.
- `dbError()` logs the real exception and returns a generic message — all
  user-facing `$e->getMessage()` leaks replaced (30 files); DB connection failure
  no longer prints the DSN/credentials.

**Login (`admin/login.php`):** own CSRF check, `session_regenerate_id(true)` on
success (kills session-fixation), and a brute-force lockout (5 fails → 15-min
cool-off).

**File uploads (`uploadImageFile`):** now reject anything that isn't a genuine
uploaded image — `is_uploaded_file`, real-type check via `getimagesize()`,
**extension forced from the verified type** (not the user's filename), random
`random_bytes` name, `0644` perms. Product/bill/meter uploads all share it.

**Web-server rules (Apache `.htaccess`):**
- Root: no directory listing; blocks direct download of `.sql .md .zip .log .ini
  .bak .json …` and all dotfiles → the `agrovise_db.sql` dump, docs, and backup
  zip now return **403**.
- `uploads/`: `engine off` + deny all scripts → a PHP file planted in uploads
  returns 403 (cannot execute) — belt-and-suspenders with the content check.
- `includes/`: `Require all denied` (library code, never served directly).

Verified live: tokenless POST → clean 403 and no row written; tokened POST →
works; DB dump/docs/includes → 403; legit pages + AJAX API + public site → 200;
planted `uploads/probe.php` → 403; all 60 PHP files pass `php -l`.

## Update — Traccar SMS Gateway support, dual-app sender (2026-07-06)

`sendSmsViaGateway()` now supports two Android gateway apps, selected by the
new `sms_gateway_type` setting (Messaging Centre gained a "Gateway App"
dropdown that relabels/hides fields accordingly):
- **traccar** (default): POST `{url}/` with `Authorization: <api key>` header
  and `{"to": "+92…", "message": …}` — the key is stored in the password slot,
  no username needed.
- **smsgate**: unchanged (POST `{url}/message`, Basic auth,
  `{"message": …, "phoneNumbers": […]}`).
Verified all three send paths against a Traccar-shaped mock (correct header,
JSON shape, +92 numbers, HTTP 200 handling): the Send-Test-SMS tool, the
auto-send inside `queueOutboundMessage()` (the call invoicing makes), and the
per-message SMS button. Test rows removed; gateway URL/key left blank for the
real phone (type=traccar and auto-send pre-set).

## Feature — real SIM-network SMS via Android gateway (2026-07-06)

The messaging queue can now transmit **real SMS through a SIM** using an
Android phone running the SMS Gate app (sms-gate.app) as the gateway — no
special number needed; messages go out from the company SIM.

- `outbound_messages.sent_via` column added (SMS / WHATSAPP / manual).
- functions.php: `intlPhonePK()` (0300… → +92300…), `smsGatewayConfigured()`,
  `sendSmsViaGateway()` (cURL POST `{url}/message`, Basic auth,
  `{"message": …, "phoneNumbers": ["+92…"]}`, 4s/8s timeouts so an offline
  phone can never hang invoicing). `queueOutboundMessage()` now auto-sends
  over SMS at queue time when the "Auto-send by SMS" toggle is on; failures
  leave the row PENDING for retry.
- messaging.php: SIM-gateway settings (URL / username / password / auto-send
  toggle), a Send-Test-SMS tool, a per-message "SMS" send button (WhatsApp
  stays as fallback), and a Channel column on the sent log.

Verified against a mock of the app's API: settings saved; the test SMS
arrived at the gateway with correct Basic auth and `+923326679047`; creating
an invoice with auto-send ON fired both messages automatically (marked
SENT via SMS, exact templates in the gateway log); with the gateway offline,
an invoice still saved in ~4s and its messages stayed PENDING. Test invoices
deleted (stock restored); gateway settings left blank for the real phone.

## Feature — auto-dash phone/CNIC formats (2026-07-05)

Phone numbers are standardized to `0000-0000000` (4-7) and CNICs to
`00000-0000000-0` (5-7-1) with the dashes **inserted automatically** at two
layers:
- **As-you-type:** new `assets/js/input-formats.js` attaches to every input
  named `phone`/`cnic` on add/edit-client, add/edit-employee, add/edit-vendor,
  inserting dashes live with caret preservation, capping length, and
  reformatting pre-filled legacy values (e.g. old 11-digit employee phones) on
  page load.
- **Server-side:** `normalizePhonePK()` / `normalizeCNIC()` in functions.php
  strip non-digits and re-dash when the digit count is right, so even a
  JS-less submission of `03001234567` stores as `0300-1234567`. Employee phone
  validation switched from bare 11-digit to the dashed standard; edit-employee
  and both vendor forms normalize too (legacy values pass through unchanged).
Verified E2E: dashless client/employee/vendor submissions all stored dashed
(e.g. `3520212345678` → `35202-1234567-8`); test rows removed.

## Feature — messaging system + banking payment pickers (2026-07-05)

**Messaging.** New `settings` + `outbound_messages` tables (explicit
utf8mb4_unicode_ci). Admin-only `messaging.php` ("Messaging" under Company):
the admin authorizes the company sender phone number and toggles messaging on;
each queued message has a WhatsApp click-to-send button (`wa.me` link with the
text prefilled; local 0-prefixed numbers become +92) plus mark-sent/discard.
When active, the system composes messages automatically:
- **Invoice logged** (`add-invoice.php`, after commit, failure-proofed): one
  message to the sales officer ("Asslam-U-Alikum Mr <officer>, the <product>
  (Qty: n) for your client <client> has been invoiced successfully and will be
  dispatched soon for the <address>...") and one to the client (same wording
  without the client clause) — both with the invoiced-on timestamp and the
  shared "Regards, / Agrovise Team" sign-off.
- **Vendor payment** (`add-transaction.php`, category Purchasing +
  withdrawal): "Asslam-U-Alikum Mr <contact_person> from <vendor>, the payment
  of Rs. <amount> has been sent to your account from our end, please confirm
  this payment and send us a confirmation message."
Helpers in functions.php: getSetting/setSetting, messagingEnabled,
queueOutboundMessage (skips blank phones), waSendLink, messageSignature.
Phone sources verified: employees.phone, clients.phone, vendors.phone +
vendors.contact_person.

**Banking payment pickers** (mirroring the Employee Expenses flow, all
OPTIONAL — the form submits without any selection):
- Purchasing: picking a vendor lists their purchase lots ("Pending Purchases")
  with an outstanding-payable hint; picking one auto-fills the amount and
  defaults the description to "Payment against purchase batch <batch>".
- Salaries: picking an employee shows their monthly salary and auto-fills the
  amount when empty.
- Client Payment: picking a client shows their outstanding balance and
  auto-fills when empty.
Verified E2E: settings saved; invoice queued both messages with the exact
templates; a vendor payment WITHOUT any purchase selected submitted fine and
queued the vendor message; test rows removed and balances restored exactly.

## Feature — "Sales Employee" access level + editable banking transactions (2026-07-05)

**Sales Employee access level.** add-employee / edit-employee now offer a
third access level alongside User and Admin. "Sales Employee" stores the login
as `role='user'` with **empty permissions** (`[]`), which means the account
gets only My Space (My Profile + Expenses) and nothing else — it lands on
`my-profile.php`, is bounced from every module page, and its sidebar shows only
the My Space group. The module checklist hides when this level is chosen; on
edit, a `user` account with no modules is detected and shown as "Sales
Employee". Verified end-to-end: created account stored as user/`[]`, logged in
→ my-profile, blocked (302) from invoices/banking/ledger/employees, sidebar
shows only My Space.

**Editable/deletable banking transactions.** The banking transactions table had
an "Actions" header but no buttons. Added Edit + Delete per row and a new
`edit-transaction.php` that re-syncs balances inside a DB transaction (revert
the old effect, apply the new, handling an account switch). Both edit and
delete **guard linked transactions** — a Payment Receipt's deposit
(description begins "Payment Received via PR:") or a paid expense reimbursement
(`expense_claims.paid_transaction_id`, or category "Employee Expenses") shows a
lock icon and is refused server-side, directing the user to the PR/Expenses
page so linked records never desync. Verified: editing 500→800 moved the
balance exactly (revert +500, apply −800); delete reverted it and removed the
row; edit and delete of a PR-linked deposit were both blocked and that row
survived.

## Fix — designations page crash: collation mismatch (2026-07-05)

**Symptom:** `admin/designations.php` returned a fatal
`PDOException: Illegal mix of collations (utf8mb4_unicode_ci, IMPLICIT) and
(utf8mb4_0900_ai_ci, IMPLICIT) for operation '='` on line 38.

**Cause:** the query counts employees per title with `e.role = d.title`.
`designations` (and the other fleet/expense tables) were created without an
explicit `COLLATE`, so on MySQL 8.4 they took the server default
`utf8mb4_0900_ai_ci`, whereas `employees` uses the schema-wide
`utf8mb4_unicode_ci`. MySQL refuses to compare string columns across two
collations.

**Fix:** an idempotent migration converted the five tables introduced in the
2026-07 work — `designations`, `vehicles`, `expense_claims`, `notifications`,
`vehicle_readings` — to `utf8mb4_unicode_ci` (root-cause fix; no query
changes needed). Verified: the page loads and lists all titles with correct
"employees holding it" counts; add, duplicate-guard, and delete all work; the
employee/vehicle/expense pages that read these tables render clean. Dump
refreshed. (The pre-existing `banking`/`transactions`/`vendors` tables remain
`0900` — they only ever join on integer IDs, so they never trip this and were
left untouched.)

## Feature — sales-officer attribution, restricted access & detailed ledgers (2026-07-05)

New columns (idempotent migration, then removed): `clients.employee_id`
(assigned sales officer / dealer handler), `payment_receipts.employee_id`
(officer the payment was collected through), `purchasing.expiry_date`.

**Sales-officer attribution:** add/edit-client now pick an assigned officer;
add-invoice and add-pr default the officer from the chosen client (JS map) and
store it. The printed invoice shows "Sales Officer: <name>". Officer receivable
= Σ invoices bearing their name − Σ payments collected through them.

**Restricted access:** a `user` account with no granted modules (a sales
officer) now lands on `my-profile.php` at login (new `landingPage()` helper);
`dashboard.php` redirects such accounts to their profile, and the sidebar
hides the Dashboard link for them. They keep only My Space (profile +
expenses) unless an admin grants modules. My Profile gained a personal
snapshot: this-month sales, total sales, due payments from their dealers, and
a monthly-sales bar chart — so an officer sees only their own numbers and can
make no changes.

**Admin dashboard P/L:** admins get a Profit &amp; Loss panel with an
area selector (each area + "Overall Company"), charting monthly profit/loss
(green bars profit, red loss) computed as invoice-line revenue − COGS.

**Ledgers (accounts/admin only):** the employee tab is now "Sales Officers"
with Sales Invoiced / Collected / Outstanding Receivable columns; per-client
statements remain via `detailed-report.php`. New **Exports** tab drives
`ledger-export.php` — 12 detailed CSV downloads (UTF-8 BOM for Excel), several
with an optional year filter: monthly P/L, yearly P/L, P/L by area, COGS by
product, products sold (line detail), bulk purchases, bought-vs-sold
comparison, expenses (total + individual), salaries (total + individual),
client ledger, officer receivables, and a stock report (batch, type, vendor
bought-from, qty, unit cost, stock value, expiry date). `fputcsv` is called
with an explicit escape arg to avoid PHP 8.4's deprecation leaking into output.

E2E-verified: sales-officer account lands on profile, is bounced from
dashboard/invoices/ledger, and sees its own sales chart; a client saved with
an officer, an invoice+PR under that officer produced Invoiced 5,000 /
Collected 2,000 / Outstanding 3,000 in both the ledger and the CSV; all 12
exports returned clean CSVs with real data. Test rows were then removed.

## Feature — HR/fleet/expense-claims suite (2026-07-04)

New tables: `designations` (admin-managed titles, seeded with Trainee/Area/
Regional Sales Officer, Accounts, General Manager, CEO), `vehicles` (plate,
make/model/color/year, CASH vs LOAN financing with tenure + installments
remaining + monthly amount, assigned employee), `expense_claims`
(WAITING→APPROVED/DECLINED→PAID lifecycle), `notifications` (per-account
fan-out), `vehicle_readings` (one odometer reading per vehicle per month,
optional meter photo, must not decrease). No FKs to `employees` (MyISAM) —
`employees.php` delete now unassigns vehicles and removes claims/accounts.

Pages: `vehicles.php`/`add-vehicle.php`/`edit-vehicle.php` (module
'vehicles'; edit shows reading history + distance deltas), `designations.php`
(admin-only; employee Role fields are now dropdowns fed from it),
`expenses.php` (claim form with bill upload; approver review section),
`my-profile.php` (account, sales, salary-paid, claims summary, issued vehicle
+ monthly reading form). Sidebar rebuilt into permission-aware drop-down
groups (My Space / Catalog / Supply / Sales / Finance / Company) with an
unread-notification badge.

**Fixed while wiring payments:** `add-transaction.php` markup contained the
entity selects (vendor/employee/client) duplicated ~8× with identical names —
the last empty duplicate always overwrote the chosen value on submit, so
Salaries/Purchasing transactions silently saved NULL vendor/employee links.
Rebuilt with a single set of selects; the insert now stores
vendor_id/employee_id/client_id again, plus the new "Employee Expenses"
category that auto-fills an approved claim's amount and marks it PAID.

E2E-verified through live endpoints: employee filed a Rs 1,500 Travel claim
(WAITING + 3 approver notifications), admin approved (claimant notified),
payment logged via banking (claim → PAID via BANK, transaction linked,
balance moved), employee's view showed "Amount Paid — Bank" with zero
occurrences of the bank's name, vehicle issued on a 48-month loan, odometer
reading submitted, and a duplicate same-month reading was rejected. All test
data was then removed and the reversed payment restored the bank balance.

## Housekeeping — 2026-07-04 cleanup & merge pass

Removed dead files (all backed up in `_removed_backup_2026-07-04.zip` at the
repo root before deletion; nothing referenced them):

- **Applied one-off scripts:** `admin/migrate_erp.php`, `migrate_pr.php`,
  `migrate_detailed_ledger.php`, `migrate_rbac.php`, `fix_actions_regex.php`,
  `fix_banking_final.php`, `fix_banking_logic.php`, `fix_db_schema.php`,
  `fix_db_simple.php`, `refactor_purchasing_form.php`,
  `refactor_purchasing_list.php`, `refactor_transaction_ui.php`,
  `update_actions.php` — several of these ran **without authentication** and
  could rewrite page files if visited, so removing them also closes a hole.
- **Stale root files:** `agrovise_update.sql`, `database_updates.sql`
  (superseded by the refreshed `agrovise_db.sql`), `index.html` (outdated
  static homepage; `index.php` is the live one), `install.php` (installation
  done; its own output said to delete it), `clean_views.py`, `fix_sidebar.py`
  (historic codemods), and the stray `-p/` directory.
- `admin/schema_info.php` merged into `admin/dump_schema.php`, which is now
  **admin-only** (both were previously unauthenticated).

Merges (no behaviour or URL changes, no measurable speed cost):

- **`includes/admin-sidebar.php`** — the sidebar `<aside>` block, previously
  copy-pasted into 32 admin pages (~40 lines each), is now one shared include.
  Nav changes are single-file edits from here on.
- **`includes/category-page.php`** — the five near-identical
  `categories/*.php` pages (151-177 lines each) are now 8-line stubs setting
  `$category`/`$metaTail`; the markup lives once in the template. Public URLs
  unchanged.
- **`includes/db.php`** slimmed to constants + `getDBConnection()`:
  `initDatabase()`/`insertSampleProducts()` (~215 lines) had no caller after
  `install.php` was removed, and `agrovise_db.sql` is the documented setup path.
- **`agrovise_db.sql` refreshed** via mysqldump — now includes the RBAC
  columns on `admins` (email, role, permissions, employee_id, created_at) and
  current data. Verified: 14 tables, 12 INSERT blocks.

Post-cleanup verification: 50 PHP files, 0 syntax errors; public home,
category pages, login (GET+POST), and all admin pages serve correctly with
the shared sidebar (13 links, permission-gated); response times 9-47 ms.

## View-only changes (no logic touched)

- **`admin/dashboard.php`** — redesigned main content: greeting banner with
  quick actions, business KPI cards (Total Sales, Receivables Due, Cash &
  Bank, Stock Value at cost), Chart.js sales-by-month bar chart and catalog
  donut, latest invoices and low-stock alert panels, plus the original recent
  products table. New data access is **read-only aggregate SELECTs**; the
  sidebar and the product-delete handler are unchanged (the delete handler was
  moved above the page queries — same behaviour, it always redirected).
- **`index.php`** — added a cursor-driven 3D layer: hero content tilts toward
  the cursor with depth-layered children, category/feature cards tilt in 3D
  with a moving light glare, floating shapes and the Three.js particle camera
  parallax with the mouse, the "Success Stories" 3D slider swivels toward the
  cursor, and the about image tilts. Disabled automatically for touch devices
  and `prefers-reduced-motion` users. PHP logic, content, and links unchanged.
- **`index.php` (2026-07-04, second pass)** — full view-layer redesign in a
  luxury "harvest editorial" direction (chaptered scroll, Cormorant Garamond
  display serif + tracked Jost sans, forest-ink/ivory/wheat-gold palette,
  editorial category plates, scrolling ribbon, cursor dot, scroll reveals).
  The PHP data block at the top (featured products, published-product and
  category counters, happy-farmers count) is byte-identical to before; all
  category/admin/footer links preserved. Design is original, inspired by the
  chaptered-luxury-microsite genre — no third-party brand assets used.

---

# 2026-07-14 — Website Traffic + Sales Policies (new features)

Two features added. New schema applied with an idempotent script, which was
then run, `agrovise_db.sql` refreshed, and the script deleted (per the
schema-evolution convention).

## Schema

```sql
site_visits    (visitor_id, session_id, page_type, page_url, category,
                referrer, ip_address, device, browser, user_agent, created_at)
product_views  (visitor_id, session_id, product_id, category, source, created_at)
policies       (name, description, start_date, end_date, status, total_amount,
                created_by, created_at)
policy_items   (policy_id, product_id, quantity, price, sales_tax, packs_per_carton)
invoices.policy_id  int NULL   -- which policy an invoice was raised under
```

All four tables are InnoDB, so the policy save/update paths get real
transactions (most legacy tables are MyISAM, where `beginTransaction()` is a
no-op).

## 1. Website traffic

**New: `includes/tracking.php`.** `trackPageView()` writes one `site_visits`
row per public page load; `trackProductView()` writes a `product_views` row
when a visitor opens a product. Both are failure-proofed (every DB call is
wrapped — a tracking error can never take the public site down) and both skip
bots (`isBotAgent()`) and logged-in staff, so staff browsing is not counted as
traffic. Visitors are identified by an anonymous 1-year `av_vid` cookie
(HttpOnly, SameSite=Lax) — no personal data is collected beyond IP + UA.

**Hooks.** `index.php` calls `trackPageView($conn, 'home')`;
`includes/category-page.php` calls `trackPageView($conn, 'category', $category)`,
so all five category pages are covered by the one shared template.

**New: `track.php`** (site root) — the beacon the public site POSTs to when a
product card is opened. Always answers `204`. It is a public endpoint, so
there is no CSRF requirement for anonymous visitors; the page still sends the
token so a logged-in admin's POST passes the auto-CSRF gate in `functions.php`.

**Product-level tracking** needed somewhere to hang off: the public site had no
product detail view, so category pages now open a **product detail overlay**
(image, category, packing, packs/carton) when a card is clicked. Opening it is
what records the product view — so "what type of product he visits" is real
recorded interest, not an impression.

**New: `admin/traffic.php`** (module `traffic`) — date-ranged (default 30 days,
with Today/7/30/365 shortcuts): KPIs (page views, unique visitors, product
views, pages/visit, views today), a Chart.js views-vs-visitors line chart,
categories browsed (ranked bars), product interest by type (doughnut), most
viewed products, device/browser/referrer splits, and a visitor table where
each row drills into that visitor's **journey** — every page and product they
looked at, newest first.

## 2. Sales policies

A policy is a named bundle of products at agreed prices (e.g. "Eid Policy").

**The Policy Calculator is how a policy gets its price:** choose products, set
each quantity and unit price (optional per-line tax %), and the sum of the
lines becomes the policy total —
`total = Σ (quantity × price) × (1 + tax/100)`, the same formula invoices use,
so an applied policy reproduces its price exactly on the invoice.

- **`includes/policy-items.php`** — the shared calculator grid (+ its JS), so
  `add-policy.php`, `edit-policy.php` and `policy-calculator.php` all compute
  identically.
- **`admin/policies.php`** — list: product lines inline, policy price, how many
  invoices used it, activate/deactivate toggle, edit, delete. Deleting a policy
  never touches invoices already raised under it (they keep their own line items).
- **`admin/add-policy.php`** / **`admin/edit-policy.php`** — name, validity
  window, status, description + the calculator. Edit replaces the lines
  wholesale and re-prices the policy from them.
- **`admin/policy-calculator.php`** — standalone scratchpad; it POSTs to
  `add-policy.php`, so pricing and saving share one validation path.

**Invoice integration (`admin/add-invoice.php`).** An "Apply a Sales Policy"
picker lists ACTIVE policies inside their validity window. Applying one adds
every policy product to the invoice grid with its quantity, agreed price, tax
and packs already filled. Each policy line is matched to a stock lot of that
product — a FINISHED lot that can cover the quantity is preferred, then any lot
that can, then the fullest lot available. Products with **no** stock are
reported and not added; products with **insufficient** stock are added and
flagged, so nothing fails silently. Lines stay editable after applying, and the
chosen policy is recorded on `invoices.policy_id` (shown as a badge on
`invoices.php`). Policies bind to *products*, not to stock lots — a seasonal
price list must not be pinned to a batch that will deplete.

**Permissions.** Two new grantable modules in `adminModules()`: `policies`
(Sales group: Policies + Policy Calculator) and `traffic` (top-level Website
Traffic link). Both permission-gated in the shared sidebar as usual.

## Verification

`php -l` clean on all 14 touched files. Live end-to-end tests against
`agrovise_db`: public pages hit over HTTP recorded correct visits
(page_type/category/device/browser) and product views; a Googlebot UA was
correctly ignored; the traffic page rendered accurate KPIs and reconstructed a
visitor's exact journey. A test "Eid Policy" (CEEDO 10 × 150, sudao 5 × 500
+17% tax) saved with total **4,425.00** = 1,500 + 2,925 (calculator formula
confirmed); an invoice created from it stored `policy_id`, totalled 4,425.00
and deducted stock correctly (609→599, 989→984). All four validation guards
(no name / no products / bad date range / bad tax) fired. **All test data was
then rolled back** — stock restored, test invoice and policy deleted, traffic
tables truncated — so both features ship with empty tables.
