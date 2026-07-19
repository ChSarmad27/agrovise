<?php
/**
 * AGROVISE - Edit Transaction
 * Edits a plain banking transaction and re-syncs account balances (reverts the
 * old effect, applies the new one, handling an account change) in a DB
 * transaction. Transactions linked to a Payment Receipt or an Expense Claim are
 * NOT editable here — they must be managed from their own page to stay in sync.
 */
require_once '../includes/db.php';
require_once '../includes/functions.php';
requirePermission('banking');

$conn = getDBConnection();
$id = intval($_GET['id'] ?? 0);
$errors = [];

$stmt = $conn->prepare("SELECT * FROM transactions WHERE id = ?");
$stmt->execute([$id]);
$tx = $stmt->fetch();

if (!$tx) {
    setFlashMessage('error', 'Transaction not found.');
    header('Location: banking.php');
    exit;
}

// Guard: is this transaction linked to a payment receipt or an expense claim?
$linkedExpense = $conn->prepare("SELECT id FROM expense_claims WHERE paid_transaction_id = ?");
$linkedExpense->execute([$id]);
$isExpenseLinked = (bool)$linkedExpense->fetch();
$isPrLinked = (strpos($tx['description'] ?? '', 'Payment Received via PR:') === 0);

if ($isExpenseLinked || $isPrLinked) {
    setFlashMessage('error', $isPrLinked
        ? 'This deposit belongs to a Payment Receipt — edit it from the Payment Receipts page so records stay in sync.'
        : 'This withdrawal is a paid expense reimbursement — manage it from the Expenses page.');
    header('Location: banking.php');
    exit;
}

$accounts = $conn->query("SELECT id, account_name, balance FROM banking ORDER BY account_name")->fetchAll();
$vendors = $conn->query("SELECT id, name FROM vendors ORDER BY name")->fetchAll();
$employees = $conn->query("SELECT id, name FROM employees ORDER BY name")->fetchAll();
$clients = $conn->query("SELECT id, name FROM clients ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bank_id = intval($_POST['bank_id'] ?? 0);
    $vendor_id = !empty($_POST['vendor_id']) ? intval($_POST['vendor_id']) : null;
    $employee_id = !empty($_POST['employee_id']) ? intval($_POST['employee_id']) : null;
    $client_id = !empty($_POST['client_id']) ? intval($_POST['client_id']) : null;
    $type = trim($_POST['type'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $amount = floatval($_POST['amount'] ?? 0);
    $transaction_date = trim($_POST['transaction_date'] ?? date('Y-m-d'));
    $description = trim($_POST['description'] ?? '');

    if (empty($bank_id)) $errors[] = "Account is required.";
    if (!in_array($type, ['DEPOSIT', 'WITHDRAWAL'])) $errors[] = "Valid type is required.";
    if (empty($category)) $errors[] = "Category is required.";
    if ($amount <= 0) $errors[] = "Amount must be greater than zero.";
    if ($category === 'Employee Expenses') $errors[] = "Reimbursements are recorded from the Expenses/Add-Transaction flow, not edited here.";
    if ($category === 'Purchasing' && !$vendor_id) $errors[] = "Select the vendor being paid.";
    if ($category === 'Salaries' && !$employee_id) $errors[] = "Select the employee being paid.";
    if ($category === 'Client Payment' && !$client_id) $errors[] = "Select the client.";

    if (empty($errors)) {
        try {
            $conn->beginTransaction();

            // 1. Revert the OLD effect on the old account
            if ($tx['type'] === 'DEPOSIT') {
                $conn->prepare("UPDATE banking SET balance = balance - ? WHERE id = ?")->execute([$tx['amount'], $tx['bank_id']]);
            } else {
                $conn->prepare("UPDATE banking SET balance = balance + ? WHERE id = ?")->execute([$tx['amount'], $tx['bank_id']]);
            }

            // 2. Apply the NEW effect on the new account
            if ($type === 'DEPOSIT') {
                $conn->prepare("UPDATE banking SET balance = balance + ? WHERE id = ?")->execute([$amount, $bank_id]);
            } else {
                $conn->prepare("UPDATE banking SET balance = balance - ? WHERE id = ?")->execute([$amount, $bank_id]);
            }

            // 3. Update the transaction row
            $conn->prepare("UPDATE transactions SET bank_id = ?, vendor_id = ?, employee_id = ?, client_id = ?, type = ?, category = ?, amount = ?, description = ?, transaction_date = ? WHERE id = ?")
                 ->execute([$bank_id, $vendor_id, $employee_id, $client_id, $type, $category, $amount, $description, $transaction_date, $id]);

            $conn->commit();
            setFlashMessage('success', 'Transaction updated and balances re-synced.');
            header('Location: banking.php');
            exit;
        } catch (PDOException $e) {
            $conn->rollBack();
            $errors[] = dbError($e);
        }
    }
} else {
    // Prefill from the stored row
    $_POST = [
        'bank_id' => $tx['bank_id'], 'vendor_id' => $tx['vendor_id'], 'employee_id' => $tx['employee_id'],
        'client_id' => $tx['client_id'], 'type' => $tx['type'], 'category' => $tx['category'],
        'amount' => $tx['amount'], 'transaction_date' => $tx['transaction_date'], 'description' => $tx['description'],
    ];
}
$P = $_POST;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Transaction - AGROVISE</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=ed3">
</head>
<body class="admin-body">
    <div class="admin-wrapper">
        <?php require __DIR__ . '/../includes/admin-sidebar.php'; ?>
        <main class="admin-main">
            <div class="admin-header"><h1>Edit Transaction</h1></div>
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
                                <option value="<?php echo $acc['id']; ?>" <?php echo $P['bank_id'] == $acc['id'] ? 'selected' : ''; ?>><?php echo sanitize($acc['account_name']) . " (Bal: Rs. " . number_format($acc['balance'], 2) . ")"; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                            <div class="form-group">
                                <label class="form-label">Type *</label>
                                <select name="type" class="form-select" required>
                                    <option value="DEPOSIT" <?php echo $P['type'] === 'DEPOSIT' ? 'selected' : ''; ?>>Deposit (Money In)</option>
                                    <option value="WITHDRAWAL" <?php echo $P['type'] === 'WITHDRAWAL' ? 'selected' : ''; ?>>Withdrawal (Money Out)</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Category *</label>
                                <select name="category" id="categorySelect" class="form-select" required>
                                    <?php foreach (['Purchasing' => 'Purchasing / Supplier Payment', 'Salaries' => 'Salaries', 'Client Payment' => 'Client Payment', 'Office Expenses' => 'Office Expenses', 'Custom' => 'Custom / Other'] as $cv => $cl): ?>
                                    <option value="<?php echo $cv; ?>" <?php echo $P['category'] === $cv ? 'selected' : ''; ?>><?php echo $cl; ?></option>
                                    <?php endforeach; ?>
                                    <?php if (!in_array($P['category'], ['Purchasing','Salaries','Client Payment','Office Expenses','Custom'], true)): ?>
                                    <option value="<?php echo sanitize($P['category']); ?>" selected><?php echo sanitize($P['category']); ?></option>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </div>

                        <div id="vendor_select" class="form-group entity-select" style="display:none;">
                            <label class="form-label">Select Vendor / Supplier *</label>
                            <select name="vendor_id" class="form-select">
                                <option value="">-- Select Vendor --</option>
                                <?php foreach($vendors as $v): ?>
                                <option value="<?php echo $v['id']; ?>" <?php echo $P['vendor_id'] == $v['id'] ? 'selected' : ''; ?>><?php echo sanitize($v['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div id="employee_select" class="form-group entity-select" style="display:none;">
                            <label class="form-label">Select Employee *</label>
                            <select name="employee_id" class="form-select">
                                <option value="">-- Select Employee --</option>
                                <?php foreach($employees as $e): ?>
                                <option value="<?php echo $e['id']; ?>" <?php echo $P['employee_id'] == $e['id'] ? 'selected' : ''; ?>><?php echo sanitize($e['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div id="client_select" class="form-group entity-select" style="display:none;">
                            <label class="form-label">Select Client *</label>
                            <select name="client_id" class="form-select">
                                <option value="">-- Select Client --</option>
                                <?php foreach($clients as $c): ?>
                                <option value="<?php echo $c['id']; ?>" <?php echo $P['client_id'] == $c['id'] ? 'selected' : ''; ?>><?php echo sanitize($c['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                            <div class="form-group">
                                <label class="form-label">Amount (Rs) *</label>
                                <input type="number" name="amount" class="form-input" step="0.01" required min="0.01" value="<?php echo sanitize($P['amount']); ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Date *</label>
                                <input type="date" name="transaction_date" class="form-input" required value="<?php echo sanitize(date('Y-m-d', strtotime($P['transaction_date']))); ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Description / Remarks</label>
                            <textarea name="description" class="form-textarea"><?php echo sanitize($P['description']); ?></textarea>
                        </div>

                        <div style="display:flex; gap:10px;">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Transaction</button>
                            <a href="banking.php" class="btn btn-outline">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script>
    const categorySelect = document.getElementById('categorySelect');
    function syncCategory() {
        document.querySelectorAll('.entity-select').forEach(el => { el.style.display = 'none'; el.querySelector('select').required = false; });
        const c = categorySelect.value;
        if (c === 'Purchasing') { const s = document.getElementById('vendor_select'); s.style.display = 'block'; s.querySelector('select').required = true; }
        else if (c === 'Salaries') { const s = document.getElementById('employee_select'); s.style.display = 'block'; s.querySelector('select').required = true; }
        else if (c === 'Client Payment') { const s = document.getElementById('client_select'); s.style.display = 'block'; s.querySelector('select').required = true; }
    }
    categorySelect.addEventListener('change', syncCategory);
    syncCategory();
    </script>
</body>
</html>
