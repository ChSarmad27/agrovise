<?php
/**
 * AGROVISE - Edit Payment Receipt
 */
require_once '../includes/db.php';
require_once '../includes/functions.php';
requirePermission('payments');

$conn = getDBConnection();
$id = intval($_GET['id'] ?? 0);
$errors = [];

$stmt = $conn->prepare("SELECT * FROM payment_receipts WHERE id = ?");
$stmt->execute([$id]);
$pr = $stmt->fetch();

if (!$pr) {
    header('Location: pr.php');
    exit;
}

$clientsList = $conn->query("SELECT id, name FROM clients ORDER BY name ASC")->fetchAll();
$accountsList = $conn->query("SELECT id, account_name, balance FROM banking ORDER BY account_name ASC")->fetchAll();
$employeesList = $conn->query("SELECT id, name, role FROM employees ORDER BY name ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client_id = intval($_POST['client_id'] ?? 0);
    $bank_id = intval($_POST['bank_id'] ?? 0);
    $employee_id = !empty($_POST['employee_id']) ? intval($_POST['employee_id']) : null;
    $date = trim($_POST['date'] ?? '');
    $amount_received = floatval($_POST['amount_received'] ?? 0);

    if (empty($client_id)) $errors[] = 'Please select a valid client.';
    if (empty($bank_id)) $errors[] = 'Please select a deposit account.';
    if ($amount_received <= 0) $errors[] = 'Amount must be greater than zero.';

    if (empty($errors)) {
        try {
            $conn->beginTransaction();

            $old_amount = $pr['amount_received'];
            $old_bank_id = $pr['bank_id'];
            $diff = $amount_received - $old_amount;

            // 1. Update PR
            $stmt = $conn->prepare("UPDATE payment_receipts SET client_id = ?, bank_id = ?, employee_id = ?, date = ?, amount_received = ? WHERE id = ?");
            $stmt->execute([$client_id, $bank_id, $employee_id, $date, $amount_received, $id]);
            
            // 2. Sync Banking transaction
            // Find transaction by PR description
            $descMatch = "Payment Received via PR: " . $pr['pr_no'];
            $tStmt = $conn->prepare("SELECT id FROM transactions WHERE description = ? AND amount = ? AND bank_id = ?");
            $tStmt->execute([$descMatch, $old_amount, $old_bank_id]);
            $trans = $tStmt->fetch();

            if ($trans) {
                // Update transaction
                $uT = $conn->prepare("UPDATE transactions SET amount = ?, bank_id = ?, transaction_date = ? WHERE id = ?");
                $uT->execute([$amount_received, $bank_id, $date, $trans['id']]);

                // Update Balances
                if ($old_bank_id == $bank_id) {
                    // Just add the difference
                    $uB = $conn->prepare("UPDATE banking SET balance = balance + ? WHERE id = ?");
                    $uB->execute([$diff, $bank_id]);
                } else {
                    // Revert old bank
                    $uB1 = $conn->prepare("UPDATE banking SET balance = balance - ? WHERE id = ?");
                    $uB1->execute([$old_amount, $old_bank_id]);
                    // Add to new bank
                    $uB2 = $conn->prepare("UPDATE banking SET balance = balance + ? WHERE id = ?");
                    $uB2->execute([$amount_received, $bank_id]);
                }
            }

            $conn->commit();
            setFlashMessage('success', 'Payment Receipt and Banking records updated!');
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
    <title>Edit Receipt - AGROVISE</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=ed3">
</head>
<body class="admin-body">
    <div class="admin-wrapper">
        <?php require __DIR__ . '/../includes/admin-sidebar.php'; ?>
        
        <main class="admin-main">
            <div class="admin-header"><h1>Edit Receipt: <?php echo sanitize($pr['pr_no']); ?></h1></div>
            
            <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul><?php foreach ($errors as $e) echo "<li>$e</li>"; ?></ul>
            </div>
            <?php endif; ?>
            
            <div class="data-card" style="max-width: 700px;">
                <div style="padding: 25px;">
                    <form method="POST">
                        <?php echo csrfField(); ?>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                             <div class="form-group">
                                <label class="form-label">Date *</label>
                                <input type="date" name="date" class="form-input" required value="<?php echo $pr['date']; ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">PR Number</label>
                                <input type="text" class="form-input" value="<?php echo sanitize($pr['pr_no']); ?>" readonly style="background:#eee;">
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                            <div class="form-group">
                                <label class="form-label">Client Name (Dealer) *</label>
                                <select name="client_id" class="form-select" required>
                                    <?php foreach($clientsList as $c): ?>
                                    <option value="<?php echo $c['id']; ?>" <?php echo $c['id'] == $pr['client_id'] ? 'selected' : ''; ?>><?php echo sanitize($c['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Received Through (Sales Officer)</label>
                                <select name="employee_id" class="form-select">
                                    <option value="">-- None --</option>
                                    <?php foreach($employeesList as $e): ?>
                                    <option value="<?php echo $e['id']; ?>" <?php echo $e['id'] == ($pr['employee_id'] ?? 0) ? 'selected' : ''; ?>><?php echo sanitize($e['name'] . ' — ' . $e['role']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                            <div class="form-group">
                                <label class="form-label">Amount Received Rs. *</label>
                                <input type="number" step="0.01" name="amount_received" class="form-input" required value="<?php echo $pr['amount_received']; ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Deposit In (Account) *</label>
                                <select name="bank_id" class="form-select" required>
                                    <?php foreach($accountsList as $acc): ?>
                                    <option value="<?php echo $acc['id']; ?>" <?php echo $acc['id'] == $pr['bank_id'] ? 'selected' : ''; ?>><?php echo sanitize($acc['account_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div style="margin-top: 20px; display: flex; gap: 10px;">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
                            <a href="pr.php" class="btn btn-outline">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
