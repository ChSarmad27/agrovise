<?php
/**
 * AGROVISE - Detailed Statement of Account
 */
require_once '../includes/db.php';
require_once '../includes/functions.php';
requirePermission('ledgers');

$conn = getDBConnection();
$type = $_GET['type'] ?? '';
$id = intval($_GET['id'] ?? 0);

if (!$type || !$id) {
    header('Location: ledger.php');
    exit;
}

$entity = null;
$history = [];
$title = "";

if ($type === 'client') {
    $stmt = $conn->prepare("SELECT * FROM clients WHERE id = ?");
    $stmt->execute([$id]);
    $entity = $stmt->fetch();
    if (!$entity) { header('Location: ledger.php'); exit; }
    $title = "Client Statement: " . $entity['name'];
    
    // Fetch Invoices (Debits) and Payments (Credits)
    // 1. Invoices
    $invStmt = $conn->prepare("SELECT date as t_date, invoice_no as ref, total_amount as debit, 0 as credit, 'Invoice' as type FROM invoices WHERE client_id = ?");
    $invStmt->execute([$id]);
    $history = array_merge($history, $invStmt->fetchAll());
    
    // 2. Payment Receipts
    $prStmt = $conn->prepare("SELECT date as t_date, pr_no as ref, 0 as debit, amount_received as credit, 'Payment Receipt' as type FROM payment_receipts WHERE client_id = ?");
    $prStmt->execute([$id]);
    $history = array_merge($history, $prStmt->fetchAll());
    
    // 3. Manual Banking Transactions
    $btStmt = $conn->prepare("SELECT transaction_date as t_date, 'BANK' as ref, (CASE WHEN type='WITHDRAWAL' THEN amount ELSE 0 END) as debit, (CASE WHEN type='DEPOSIT' THEN amount ELSE 0 END) as credit, category as type FROM transactions WHERE client_id = ?");
    $btStmt->execute([$id]);
    $history = array_merge($history, $btStmt->fetchAll());

} elseif ($type === 'vendor') {
    $stmt = $conn->prepare("SELECT * FROM vendors WHERE id = ?");
    $stmt->execute([$id]);
    $entity = $stmt->fetch();
    if (!$entity) { header('Location: ledger.php'); exit; }
    $title = "Vendor Statement: " . $entity['name'];
    
    // 1. Purchases (Credits/Payables)
    $purStmt = $conn->prepare("SELECT date_added as t_date, batch_number as ref, 0 as debit, total_price as credit, 'Purchase' as type FROM purchasing WHERE vendor_id = ?");
    $purStmt->execute([$id]);
    $history = array_merge($history, $purStmt->fetchAll());
    
    // 2. Payments (Withdrawals/Debits)
    $btStmt = $conn->prepare("SELECT transaction_date as t_date, 'BANK' as ref, amount as debit, 0 as credit, category as type FROM transactions WHERE vendor_id = ? AND type = 'WITHDRAWAL'");
    $btStmt->execute([$id]);
    $history = array_merge($history, $btStmt->fetchAll());

} elseif ($type === 'employee') {
    $stmt = $conn->prepare("SELECT * FROM employees WHERE id = ?");
    $stmt->execute([$id]);
    $entity = $stmt->fetch();
    if (!$entity) { header('Location: ledger.php'); exit; }
    $title = "Employee Statement: " . $entity['name'];
    
    // 1. Sales Performance (Credits for them in terms of value generated)
    $invStmt = $conn->prepare("SELECT date as t_date, invoice_no as ref, 0 as debit, total_amount as credit, 'Sale Generated' as type FROM invoices WHERE employee_id = ?");
    $invStmt->execute([$id]);
    $history = array_merge($history, $invStmt->fetchAll());
    
    // 2. Salary Payments (Withdrawals/Debits)
    $btStmt = $conn->prepare("SELECT transaction_date as t_date, 'BANK' as ref, amount as debit, 0 as credit, category as type FROM transactions WHERE employee_id = ? AND category = 'Salaries'");
    $btStmt->execute([$id]);
    $history = array_merge($history, $btStmt->fetchAll());
}

// Sort history by date
usort($history, function($a, $b) {
    return strtotime($a['t_date']) - strtotime($b['t_date']);
});

$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo $title; ?> - AGROVISE</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=ed3">
    <style>
        .statement-header { margin-bottom: 30px; border-bottom: 2px solid #eee; padding-bottom: 20px; }
        .statement-info { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-bottom: 30px; }
        .info-box { background: #f9f9f9; padding: 20px; border-radius: 8px; }
        .running-bal { font-weight: bold; }
        @media print {
            .admin-sidebar, .admin-header, .btn-print-group { display: none !important; }
            .admin-main { margin: 0 !important; padding: 0 !important; width: 100% !important; }
            .data-card { border: none !important; padding: 0 !important; }
        }
    </style>
</head>
<body class="admin-body">
    <div class="admin-wrapper">
        <?php require __DIR__ . '/../includes/admin-sidebar.php'; ?>
        
        <main class="admin-main">
            <div class="admin-header">
                <h1>Detailed Statement</h1>
                <div class="btn-print-group">
                    <button onclick="window.print()" class="btn btn-primary"><i class="fas fa-print"></i> Print Statement</button>
                </div>
            </div>

            <div class="data-card" style="padding: 30px;">
                <div class="statement-header">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                        <div>
                            <h2 style="color: var(--primary-green); margin: 0;"><?php echo sanitize($entity['name'] ?? 'Record Deleted'); ?></h2>
                            <p style="color: #666;"><?php echo $type === 'client' ? 'Client ID: #' . $id : ($type === 'vendor' ? 'Vendor ID: #' . $id : 'Employee ID: #' . $id); ?></p>
                        </div>
                        <div style="text-align: right;">
                            <h3 style="margin: 0;">Statement of Account</h3>
                            <p>Generated on: <?php echo date('d M Y'); ?></p>
                        </div>
                    </div>
                </div>

                <div class="statement-info">
                    <div class="info-box">
                        <h4>Contact Details</h4>
                        <p><strong>Phone:</strong> <?php echo sanitize($entity['phone'] ?? 'N/A'); ?></p>
                        <?php if(isset($entity['address'])): ?><p><strong>Address:</strong> <?php echo sanitize($entity['address']); ?></p><?php endif; ?>
                        <?php if(isset($entity['area'])): ?><p><strong>Area:</strong> <?php echo sanitize($entity['area']); ?></p><?php endif; ?>
                    </div>
                    <?php 
                    $totalDebit = array_sum(array_column($history, 'debit'));
                    $totalCredit = array_sum(array_column($history, 'credit'));
                    $finalBal = ($type === 'vendor') ? ($totalCredit - $totalDebit) : ($totalDebit - $totalCredit);
                    ?>
                    <div class="info-box" style="background: var(--dark-green); color: white;">
                        <h4>Summary Balance</h4>
                        <div style="font-size: 24px; font-weight: 700;">Rs. <?php echo number_format(abs($finalBal), 2); ?></div>
                        <p style="opacity: 0.8;"><?php echo $finalBal >= 0 ? ($type === 'vendor' ? 'Payable to Vendor' : 'Outstanding Balance') : 'Advance Payment / Credit'; ?></p>
                    </div>
                </div>

                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Reference</th>
                            <th>Description</th>
                            <th>Debit (Dr)</th>
                            <th>Credit (Cr)</th>
                            <th>Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $runningBal = 0;
                        foreach($history as $row): 
                            if ($type === 'vendor') {
                                $runningBal += ($row['credit'] - $row['debit']);
                            } else {
                                $runningBal += ($row['debit'] - $row['credit']);
                            }
                        ?>
                        <tr>
                            <td><?php echo date('d M Y', strtotime($row['t_date'])); ?></td>
                            <td><code><?php echo sanitize($row['ref']); ?></code></td>
                            <td><?php echo sanitize($row['type']); ?></td>
                            <td class="status-negative"><?php echo $row['debit'] > 0 ? 'Rs. ' . number_format($row['debit'], 2) : '-'; ?></td>
                            <td class="status-positive"><?php echo $row['credit'] > 0 ? 'Rs. ' . number_format($row['credit'], 2) : '-'; ?></td>
                            <td class="running-bal">Rs. <?php echo number_format($runningBal, 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($history)): ?>
                        <tr><td colspan="6" style="text-align: center; padding: 40px; color: #999;">No transaction history found for this account.</td></tr>
                        <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr style="background: #f1f1f1; font-weight: bold;">
                            <td colspan="3" style="text-align: right;">TOTALS:</td>
                            <td class="status-negative">Rs. <?php echo number_format($totalDebit, 2); ?></td>
                            <td class="status-positive">Rs. <?php echo number_format($totalCredit, 2); ?></td>
                            <td style="background: var(--primary-green); color: white;">Rs. <?php echo number_format($runningBal, 2); ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </main>
    </div>
</body>
</html>
