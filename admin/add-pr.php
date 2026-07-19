<?php
/**
 * AGROVISE - Add PR
 */
require_once '../includes/db.php';
require_once '../includes/functions.php';
requirePermission('payments');

$conn = getDBConnection();

// Auto-generate PR NO
$row = $conn->query("SELECT MAX(id) as max_id FROM payment_receipts")->fetch();
$nextId = ($row['max_id'] ?? 0) + 1;
$defaultPrNo = 'PR-' . str_pad($nextId, 6, '0', STR_PAD_LEFT);

$clientsList = $conn->query("SELECT id, name, employee_id FROM clients ORDER BY name ASC")->fetchAll();
$accountsList = $conn->query("SELECT id, account_name, balance FROM banking ORDER BY account_name ASC")->fetchAll();
$employeesList = $conn->query("SELECT id, name, role FROM employees ORDER BY name ASC")->fetchAll();
$clientEmployeeMap = [];
foreach ($clientsList as $c) { $clientEmployeeMap[$c['id']] = $c['employee_id'] ? intval($c['employee_id']) : ''; }

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client_id = intval($_POST['client_id'] ?? 0);
    $bank_id = intval($_POST['bank_id'] ?? 0);
    $employee_id = !empty($_POST['employee_id']) ? intval($_POST['employee_id']) : null;
    $date = trim($_POST['date'] ?? '');
    $pr_no = trim($_POST['pr_no'] ?? '');
    $amount_received = floatval($_POST['amount_received'] ?? 0);

    if (empty($client_id)) $errors[] = 'Please select a valid client.';
    if (empty($bank_id)) $errors[] = 'Please select a deposit account.';
    if (empty($date)) $errors[] = 'Date is required.';
    if (empty($pr_no)) $errors[] = 'PR Number is required.';
    if ($amount_received <= 0) $errors[] = 'Amount must be greater than zero.';

    if (empty($errors)) {
        try {
            $conn->beginTransaction();

            // 1. Insert PR
            $stmt = $conn->prepare("INSERT INTO payment_receipts (pr_no, client_id, bank_id, employee_id, date, amount_received) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$pr_no, $client_id, $bank_id, $employee_id, $date, $amount_received]);
            
            // 2. Create Banking Transaction
            $tStmt = $conn->prepare("INSERT INTO transactions (bank_id, type, category, amount, description, transaction_date) VALUES (?, ?, ?, ?, ?, ?)");
            $tStmt->execute([
                $bank_id, 
                'DEPOSIT', 
                'Client Payment', 
                $amount_received, 
                "Payment Received via PR: $pr_no", 
                $date
            ]);

            // 3. Update Account Balance
            $uStmt = $conn->prepare("UPDATE banking SET balance = balance + ? WHERE id = ?");
            $uStmt->execute([$amount_received, $bank_id]);

            $conn->commit();
            setFlashMessage('success', 'Payment Receipt created and banking balance updated!');
            header('Location: pr.php');
            exit;
        } catch (PDOException $e) {
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Payment Receipt - AGROVISE</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=ed3">
</head>
<body class="admin-body">
    <div class="admin-wrapper">
        <?php require __DIR__ . '/../includes/admin-sidebar.php'; ?>
        
        <main class="admin-main">
            <div class="admin-header">
                <h1>Create Payment Receipt</h1>
            </div>
            
            <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul style="margin: 0; padding-left: 20px;">
                    <?php foreach ($errors as $error) echo "<li>$error</li>"; ?>
                </ul>
            </div>
            <?php endif; ?>
            
            <div class="data-card">
                <div class="data-card-header">
                    <h2>Receipt Details</h2>
                    <a href="pr.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Back to PRs</a>
                </div>
                
                <div style="padding: 30px;">
                    <form method="POST" action="">
                        <?php echo csrfField(); ?>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px;">
                            <div class="form-group">
                                <label class="form-label">Date *</label>
                                <input type="date" name="date" class="form-input" required value="<?php echo date('Y-m-d'); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Client Name (Dealer) *</label>
                                <select name="client_id" id="clientSelect" class="form-select" required onchange="defaultOfficer()">
                                    <option value="">-- Choose Client --</option>
                                    <?php foreach($clientsList as $c): ?>
                                    <option value="<?php echo $c['id']; ?>"><?php echo sanitize($c['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Received Through (Sales Officer)</label>
                                <select name="employee_id" id="employeeSelect" class="form-select">
                                    <option value="">-- None --</option>
                                    <?php foreach($employeesList as $e): ?>
                                    <option value="<?php echo $e['id']; ?>"><?php echo sanitize($e['name'] . ' — ' . $e['role']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <small style="color:#888;">The officer who collected this payment from the dealer.</small>
                            </div>

                            <div class="form-group">
                                <label class="form-label">PR Number *</label>
                                <input type="text" name="pr_no" class="form-input" required value="<?php echo $defaultPrNo; ?>">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Amount Received *</label>
                                <input type="number" step="0.01" name="amount_received" class="form-input" required min="0.01">
                            </div>

                            <div class="form-group">
                                <label class="form-label">Deposit In (Account) *</label>
                                <select name="bank_id" class="form-select" required>
                                    <option value="">-- Select Account --</option>
                                    <?php foreach($accountsList as $acc): ?>
                                    <option value="<?php echo $acc['id']; ?>"><?php echo sanitize($acc['account_name']) . " (Bal: " . number_format($acc['balance'], 2) . ")"; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div style="margin-top: 30px;">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Receipt</button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
    <script>
    const clientEmployeeMap = <?php echo json_encode($clientEmployeeMap); ?>;
    function defaultOfficer() {
        const cid = document.getElementById('clientSelect').value;
        const eid = clientEmployeeMap[cid];
        if (eid) document.getElementById('employeeSelect').value = eid;
    }
    </script>
</body>
</html>
