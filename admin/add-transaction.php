<?php
/**
 * AGROVISE - Add Transaction
 * Includes the "Employee Expenses" payment flow: pick an employee, pick one
 * of their APPROVED expense claims (amount auto-fills), and saving the
 * withdrawal marks the claim as PAID (via Bank or Cash) and notifies them.
 */
require_once '../includes/db.php';
require_once '../includes/functions.php';
requirePermission('banking');

$conn = getDBConnection();
$errors = [];

$accounts = $conn->query("SELECT id, account_name, balance FROM banking ORDER BY account_name")->fetchAll();
$vendors = $conn->query("SELECT id, name FROM vendors ORDER BY name")->fetchAll();
$employees = $conn->query("SELECT id, name, salary FROM employees ORDER BY name")->fetchAll();
$clients = $conn->query("SELECT id, name FROM clients ORDER BY name")->fetchAll();

// Approved (unpaid) claims, embedded for the Employee Expenses flow
$approvedClaims = $conn->query("
    SELECT id, employee_id, type, amount, DATE_FORMAT(created_at, '%d %b %Y') as filed_on
    FROM expense_claims WHERE status = 'APPROVED' ORDER BY created_at
")->fetchAll();

// Optional payment pickers (never required — the form submits without them):
// each vendor's purchase lots, plus outstanding totals per vendor and client.
$vendorPurchases = $conn->query("
    SELECT pur.id, pur.vendor_id, pur.batch_number, pur.total_price,
           DATE_FORMAT(pur.date_added, '%d %b %Y') as bought_on, p.name as product
    FROM purchasing pur JOIN products p ON pur.product_id = p.id
    WHERE pur.vendor_id IS NOT NULL ORDER BY pur.date_added DESC
")->fetchAll();
$vendorOutstanding = [];
foreach ($conn->query("
    SELECT v.id,
           IFNULL((SELECT SUM(total_price) FROM purchasing WHERE vendor_id = v.id), 0) -
           IFNULL((SELECT SUM(amount) FROM transactions WHERE vendor_id = v.id AND category = 'Purchasing' AND type = 'WITHDRAWAL'), 0) AS due
    FROM vendors v")->fetchAll() as $r) {
    $vendorOutstanding[$r['id']] = round(floatval($r['due']), 2);
}
$clientOutstanding = [];
foreach ($conn->query("
    SELECT c.id,
           IFNULL((SELECT SUM(total_amount) FROM invoices WHERE client_id = c.id), 0) -
           IFNULL((SELECT SUM(amount_received) FROM payment_receipts WHERE client_id = c.id), 0) AS due
    FROM clients c")->fetchAll() as $r) {
    $clientOutstanding[$r['id']] = round(floatval($r['due']), 2);
}

// Prefill when arriving from the expenses page "Pay" button
$prefillClaim = null;
if (isset($_GET['expense_claim']) && is_numeric($_GET['expense_claim'])) {
    foreach ($approvedClaims as $c) {
        if ($c['id'] == intval($_GET['expense_claim'])) { $prefillClaim = $c; break; }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bank_id = intval($_POST['bank_id'] ?? 0);
    $vendor_id = !empty($_POST['vendor_id']) ? intval($_POST['vendor_id']) : null;
    $employee_id = !empty($_POST['employee_id']) ? intval($_POST['employee_id']) : null;
    $client_id = !empty($_POST['client_id']) ? intval($_POST['client_id']) : null;
    $claim_id = !empty($_POST['expense_claim_id']) ? intval($_POST['expense_claim_id']) : null;
    $purchase_ref = !empty($_POST['purchase_ref']) ? intval($_POST['purchase_ref']) : null;  // optional
    $type = trim($_POST['type'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $amount = floatval($_POST['amount'] ?? 0);
    $transaction_date = trim($_POST['transaction_date'] ?? date('Y-m-d'));
    $description = trim($_POST['description'] ?? '');

    if (empty($bank_id)) $errors[] = "Account is required.";
    if (!in_array($type, ['DEPOSIT', 'WITHDRAWAL'])) $errors[] = "Valid type is required.";
    if (empty($category)) $errors[] = "Category is required.";
    if ($amount <= 0) $errors[] = "Amount must be greater than zero.";

    if ($category === 'Purchasing' && !$vendor_id) $errors[] = "Select the vendor being paid.";
    if ($category === 'Salaries' && !$employee_id) $errors[] = "Select the employee being paid.";
    if ($category === 'Client Payment' && !$client_id) $errors[] = "Select the client.";

    // Employee Expenses: needs employee + one of their APPROVED claims, as a withdrawal
    $claim = null;
    if ($category === 'Employee Expenses') {
        if (!$employee_id) $errors[] = "Select the employee being reimbursed.";
        if (!$claim_id) $errors[] = "Select which approved expense claim is being paid.";
        if ($type !== 'WITHDRAWAL') $errors[] = "Expense reimbursements must be a Withdrawal.";
        if ($employee_id && $claim_id) {
            $cst = $conn->prepare("SELECT * FROM expense_claims WHERE id = ? AND employee_id = ? AND status = 'APPROVED'");
            $cst->execute([$claim_id, $employee_id]);
            $claim = $cst->fetch();
            if (!$claim) $errors[] = "That claim is not approved (or belongs to another employee).";
        }
    }

    if (empty($errors)) {
        try {
            $conn->beginTransaction();

            if ($claim && $description === '') {
                $description = 'Expense reimbursement — claim #' . $claim['id'] . ' (' . $claim['type'] . ')';
            }
            // Optional: note which purchase this vendor payment settles
            if ($purchase_ref && $category === 'Purchasing' && $description === '') {
                $pb = $conn->prepare("SELECT batch_number FROM purchasing WHERE id = ?");
                $pb->execute([$purchase_ref]);
                if ($b = $pb->fetchColumn()) $description = 'Payment against purchase batch ' . $b;
            }

            $stmt = $conn->prepare("INSERT INTO transactions (bank_id, vendor_id, employee_id, client_id, type, category, amount, description, transaction_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$bank_id, $vendor_id, $employee_id, $client_id, $type, $category, $amount, $description, $transaction_date]);
            $transaction_id = $conn->lastInsertId();

            // Update balance
            if ($type == 'DEPOSIT') {
                $upd = $conn->prepare("UPDATE banking SET balance = balance + ? WHERE id = ?");
            } else {
                $upd = $conn->prepare("UPDATE banking SET balance = balance - ? WHERE id = ?");
            }
            $upd->execute([$amount, $bank_id]);

            // Mark the expense claim as paid + tell the employee (Bank/Cash only — never the account name)
            if ($claim) {
                $accName = '';
                foreach ($accounts as $acc) { if ($acc['id'] == $bank_id) { $accName = $acc['account_name']; break; } }
                $paidVia = stripos($accName, 'cash') !== false ? 'CASH' : 'BANK';

                $conn->prepare("UPDATE expense_claims SET status = 'PAID', paid_via = ?, paid_transaction_id = ? WHERE id = ?")
                     ->execute([$paidVia, $transaction_id, $claim['id']]);

                notifyEmployeeAccount($conn, $claim['employee_id'],
                    'Your ' . sanitize($claim['type']) . ' claim of Rs. ' . number_format($claim['amount'], 2) .
                    ' has been paid via ' . ($paidVia === 'CASH' ? 'Cash' : 'Bank') . '.');
            }

            $conn->commit();

            // --- Vendor payment message (never blocks the transaction on failure) ---
            $queuedNote = '';
            try {
                if ($category === 'Purchasing' && $vendor_id && $type === 'WITHDRAWAL' && messagingEnabled($conn)) {
                    $vst = $conn->prepare("SELECT name, contact_person, phone FROM vendors WHERE id = ?");
                    $vst->execute([$vendor_id]);
                    if ($ven = $vst->fetch()) {
                        $contact = trim($ven['contact_person'] ?? '') !== '' ? $ven['contact_person'] : $ven['name'];
                        $body = "Asslam-U-Alikum Mr " . $contact . " from " . $ven['name']
                              . ", the payment of Rs. " . number_format($amount, 2)
                              . " has been sent to your account from our end, please confirm this payment and send us a confirmation message."
                              . "\nSent on: " . date('d M Y, h:i A') . messageSignature();
                        if (queueOutboundMessage($conn, 'VENDOR', $ven['name'], $ven['phone'], $body, 'Vendor payment')) {
                            $queuedNote = ' Vendor message ready — <a href="messaging.php" style="font-weight:700; text-decoration:underline; color:inherit;">open the Messaging Centre to send it via WhatsApp</a>.';
                        }
                    }
                }
            } catch (Exception $e) { /* messaging must never break payments */ }

            setFlashMessage('success', ($claim ? 'Reimbursement recorded and claim marked as PAID!' : 'Transaction recorded successfully!') . $queuedNote);
            header('Location: banking.php');
            exit;
        } catch(PDOException $e) {
            $conn->rollBack();
            $errors[] = dbError($e);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Transaction - AGROVISE</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=ed3">
</head>
<body class="admin-body">
    <div class="admin-wrapper">
        <?php require __DIR__ . '/../includes/admin-sidebar.php'; ?>
        <main class="admin-main">
            <div class="admin-header"><h1>Record Transaction</h1></div>
            <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul style="margin: 0; padding-left: 20px;">
                    <?php foreach ($errors as $error) echo "<li>" . sanitize($error) . "</li>"; ?>
                </ul>
            </div>
            <?php endif; ?>
            <div class="data-card" style="max-width: 600px;">
                <div style="padding: 20px;">
                    <form method="POST">
                        <?php echo csrfField(); ?>
                        <div class="form-group">
                            <label class="form-label">Bank / Cash Account *</label>
                            <select name="bank_id" class="form-select" required>
                                <option value="">-- Select Account --</option>
                                <?php foreach($accounts as $acc): ?>
                                <option value="<?php echo $acc['id']; ?>"><?php echo sanitize($acc['account_name']) . " (Bal: Rs. " . number_format($acc['balance'], 2) . ")"; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                            <div class="form-group">
                                <label class="form-label">Type *</label>
                                <select name="type" id="typeSelect" class="form-select" required>
                                    <option value="">-- Select Type --</option>
                                    <option value="DEPOSIT">Deposit (Money In)</option>
                                    <option value="WITHDRAWAL">Withdrawal (Money Out)</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Category *</label>
                                <select name="category" id="categorySelect" class="form-select" required>
                                    <option value="">-- Select Category --</option>
                                    <option value="Purchasing">Purchasing / Supplier Payment</option>
                                    <option value="Salaries">Salaries</option>
                                    <option value="Employee Expenses" <?php echo $prefillClaim ? 'selected' : ''; ?>>Employee Expenses (Reimbursement)</option>
                                    <option value="Client Payment">Client Payment</option>
                                    <option value="Office Expenses">Office Expenses</option>
                                    <option value="Custom">Custom / Other</option>
                                </select>
                            </div>
                        </div>

                        <div id="vendor_select" class="form-group entity-select" style="display:none;">
                            <label class="form-label">Select Vendor / Supplier *</label>
                            <select name="vendor_id" id="vendorSelect" class="form-select">
                                <option value="">-- Select Vendor --</option>
                                <?php foreach($vendors as $v): ?>
                                <option value="<?php echo $v['id']; ?>"><?php echo sanitize($v['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small id="vendorDueHint" style="color:#b3261e; display:none;"></small>
                        </div>

                        <div id="purchase_select" class="form-group" style="display:none;">
                            <label class="form-label">Pending Purchases <small style="font-weight:400; color:#999;">(optional — leave unselected for a general payment)</small></label>
                            <select name="purchase_ref" id="purchaseSelect" class="form-select">
                                <option value="">-- No specific purchase / general payment --</option>
                            </select>
                            <small style="color:#888;">Picking a purchase fills its amount automatically; you can still change it or submit without picking one.</small>
                        </div>
                        <div id="employee_select" class="form-group entity-select" style="display:none;">
                            <label class="form-label">Select Employee *</label>
                            <select name="employee_id" id="employeeSelect" class="form-select">
                                <option value="">-- Select Employee --</option>
                                <?php foreach($employees as $e): ?>
                                <option value="<?php echo $e['id']; ?>" data-salary="<?php echo floatval($e['salary']); ?>" <?php echo ($prefillClaim && $prefillClaim['employee_id'] == $e['id']) ? 'selected' : ''; ?>><?php echo sanitize($e['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small id="salaryHint" style="color:#2e7d32; display:none;"></small>
                        </div>
                        <div id="client_select" class="form-group entity-select" style="display:none;">
                            <label class="form-label">Select Client *</label>
                            <select name="client_id" id="clientSelect" class="form-select">
                                <option value="">-- Select Client --</option>
                                <?php foreach($clients as $c): ?>
                                <option value="<?php echo $c['id']; ?>"><?php echo sanitize($c['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small id="clientDueHint" style="color:#b3261e; display:none;"></small>
                        </div>

                        <div id="claim_select" class="form-group" style="display:none;">
                            <label class="form-label">Approved Expense Claim *</label>
                            <select name="expense_claim_id" id="claimSelect" class="form-select">
                                <option value="">-- Select the employee first --</option>
                            </select>
                            <small style="color:#888;">Only claims already Approved (and not yet paid) are listed. The approved amount fills in automatically.</small>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                            <div class="form-group">
                                <label class="form-label">Amount (Rs) *</label>
                                <input type="number" name="amount" id="amountInput" class="form-input" step="0.01" required min="0.01">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Date *</label>
                                <input type="date" name="transaction_date" class="form-input" required value="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Description / Remarks</label>
                            <textarea name="description" class="form-textarea"></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Transaction</button>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script>
    const approvedClaims = <?php echo json_encode($approvedClaims); ?>;
    const prefillClaimId = <?php echo $prefillClaim ? intval($prefillClaim['id']) : 'null'; ?>;
    const vendorPurchases = <?php echo json_encode($vendorPurchases); ?>;
    const vendorOutstanding = <?php echo json_encode($vendorOutstanding); ?>;
    const clientOutstanding = <?php echo json_encode($clientOutstanding); ?>;

    const categorySelect = document.getElementById('categorySelect');
    const typeSelect = document.getElementById('typeSelect');
    const employeeSelect = document.getElementById('employeeSelect');
    const claimSelect = document.getElementById('claimSelect');
    const amountInput = document.getElementById('amountInput');
    const vendorSelect = document.getElementById('vendorSelect');
    const purchaseSelect = document.getElementById('purchaseSelect');
    const clientSelect = document.getElementById('clientSelect');

    function syncCategory() {
        document.querySelectorAll('.entity-select').forEach(el => el.style.display = 'none');
        document.querySelectorAll('.entity-select select').forEach(el => el.required = false);
        document.getElementById('claim_select').style.display = 'none';
        claimSelect.required = false;
        document.getElementById('purchase_select').style.display = 'none';

        const category = categorySelect.value;
        if (category === 'Purchasing') {
            document.getElementById('vendor_select').style.display = 'block';
            document.querySelector('#vendor_select select').required = true;
            document.getElementById('purchase_select').style.display = 'block';
            fillPurchases();
        } else if (category === 'Salaries') {
            document.getElementById('employee_select').style.display = 'block';
            employeeSelect.required = true;
        } else if (category === 'Client Payment') {
            document.getElementById('client_select').style.display = 'block';
            document.querySelector('#client_select select').required = true;
        } else if (category === 'Employee Expenses') {
            document.getElementById('employee_select').style.display = 'block';
            employeeSelect.required = true;
            document.getElementById('claim_select').style.display = 'block';
            claimSelect.required = true;
            typeSelect.value = 'WITHDRAWAL';   // reimbursements always go out
            fillClaims();
        }
    }

    function fillClaims() {
        const empId = parseInt(employeeSelect.value) || 0;
        const mine = approvedClaims.filter(c => parseInt(c.employee_id) === empId);
        claimSelect.innerHTML = mine.length
            ? '<option value="">-- Select Claim --</option>' + mine.map(c =>
                `<option value="${c.id}" data-amount="${c.amount}">#${c.id} · ${c.type} · Rs. ${parseFloat(c.amount).toFixed(2)} (filed ${c.filed_on})</option>`).join('')
            : '<option value="">No approved claims for this employee</option>';
        if (prefillClaimId && mine.some(c => c.id == prefillClaimId)) {
            claimSelect.value = prefillClaimId;
            syncAmount();
        }
    }

    function syncAmount() {
        const opt = claimSelect.selectedOptions[0];
        if (opt && opt.dataset.amount) amountInput.value = parseFloat(opt.dataset.amount).toFixed(2);
    }

    // ---- Optional pickers (never required; the form submits without them) ----
    function fillPurchases() {
        const vid = parseInt(vendorSelect.value) || 0;
        const mine = vendorPurchases.filter(p => parseInt(p.vendor_id) === vid);
        purchaseSelect.innerHTML = '<option value="">-- No specific purchase / general payment --</option>' +
            mine.map(p => `<option value="${p.id}" data-amount="${p.total_price}">Batch ${p.batch_number} · ${p.product} · Rs. ${parseFloat(p.total_price).toFixed(2)} (${p.bought_on})</option>`).join('');
        const hint = document.getElementById('vendorDueHint');
        if (vid && vendorOutstanding[vid] !== undefined) {
            hint.textContent = 'Outstanding payable to this vendor: Rs. ' + parseFloat(vendorOutstanding[vid]).toLocaleString();
            hint.style.display = 'block';
        } else { hint.style.display = 'none'; }
    }

    function purchasePicked() {
        const opt = purchaseSelect.selectedOptions[0];
        if (opt && opt.dataset.amount) amountInput.value = parseFloat(opt.dataset.amount).toFixed(2);
    }

    function salaryHintSync() {
        const hint = document.getElementById('salaryHint');
        const opt = employeeSelect.selectedOptions[0];
        if (categorySelect.value === 'Salaries' && opt && opt.dataset.salary && parseFloat(opt.dataset.salary) > 0) {
            hint.textContent = 'Monthly salary: Rs. ' + parseFloat(opt.dataset.salary).toLocaleString();
            hint.style.display = 'block';
            if (!amountInput.value) amountInput.value = parseFloat(opt.dataset.salary).toFixed(2);
        } else { hint.style.display = 'none'; }
    }

    function clientDueSync() {
        const hint = document.getElementById('clientDueHint');
        const cid = parseInt(clientSelect.value) || 0;
        if (categorySelect.value === 'Client Payment' && cid && clientOutstanding[cid] !== undefined) {
            hint.textContent = 'Outstanding balance from this client: Rs. ' + parseFloat(clientOutstanding[cid]).toLocaleString();
            hint.style.display = 'block';
            if (!amountInput.value && parseFloat(clientOutstanding[cid]) > 0) amountInput.value = parseFloat(clientOutstanding[cid]).toFixed(2);
        } else { hint.style.display = 'none'; }
    }

    categorySelect.addEventListener('change', syncCategory);
    employeeSelect.addEventListener('change', () => {
        if (categorySelect.value === 'Employee Expenses') fillClaims();
        salaryHintSync();
    });
    claimSelect.addEventListener('change', syncAmount);
    vendorSelect.addEventListener('change', fillPurchases);
    purchaseSelect.addEventListener('change', purchasePicked);
    clientSelect.addEventListener('change', clientDueSync);

    // Initial state (handles ?expense_claim= prefill)
    syncCategory();
    </script>
</body>
</html>
