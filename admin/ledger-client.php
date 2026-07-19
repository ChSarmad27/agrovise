<?php
/**
 * AGROVISE - Client Ledger
 */
require_once '../includes/db.php';
require_once '../includes/functions.php';
requirePermission('ledgers');

$client_id = intval($_GET['client_id'] ?? 0);
$conn = getDBConnection();

$stmt = $conn->prepare("SELECT * FROM clients WHERE id = ?");
$stmt->execute([$client_id]);
$client = $stmt->fetch();

if (!$client) {
    die("Client not found.");
}

// Fetch ledger entries
$entries = [];

$invStmt = $conn->prepare("SELECT 'Invoice' as type, id, invoice_no as ref_no, date, total_amount as debit, 0 as credit, created_at FROM invoices WHERE client_id = ?");
$invStmt->execute([$client_id]);
$invoices = $invStmt->fetchAll(PDO::FETCH_ASSOC);

$prStmt = $conn->prepare("SELECT 'Payment Receipt' as type, id, pr_no as ref_no, date, 0 as debit, amount_received as credit, created_at FROM payment_receipts WHERE client_id = ?");
$prStmt->execute([$client_id]);
$prs = $prStmt->fetchAll(PDO::FETCH_ASSOC);

$entries = array_merge($invoices, $prs);

// Sort by date ascending, then created_at ascending
usort($entries, function($a, $b) {
    $dateA = strtotime($a['date']);
    $dateB = strtotime($b['date']);
    if ($dateA == $dateB) {
        return strtotime($a['created_at']) <=> strtotime($b['created_at']);
    }
    return $dateA <=> $dateB;
});

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Client Ledger - <?php echo sanitize($client['name']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Montserrat', sans-serif; background: #fff; color: #333; margin: 0; padding: 20px; }
        .container { max-width: 900px; margin: 0 auto; padding: 20px; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #2e7d32; padding-bottom: 20px; }
        .header h1 { color: #2e7d32; margin: 0; text-transform: uppercase; letter-spacing: 1px; }
        .header h2 { margin: 10px 0 0 0; color: #555; }
        
        .client-info { margin-bottom: 20px; font-size: 14px; }
        .client-info p { margin: 5px 0; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 10px; font-size: 14px; }
        th { background-color: #2e7d32; color: #fff; text-align: left; }
        .text-right { text-align: right; }
        
        .action-btn { position: fixed; top: 20px; right: 20px; background: #2e7d32; color: #fff; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-family: inherit; font-weight: 600; }
        @media print { .action-btn { display: none; } }
    </style>
</head>
<body>
    <button class="action-btn" onclick="window.print()"><i class="fas fa-print"></i> Print Ledger</button>

    <div class="container">
        <div class="header">
            <h1>AGROVISE</h1>
            <h2>CLIENT LEDGER</h2>
        </div>
        
        <div class="client-info">
            <p><strong>Name:</strong> <?php echo sanitize($client['name']); ?></p>
            <p><strong>Address:</strong> <?php echo sanitize($client['address']); ?> (<?php echo sanitize($client['area']); ?>)</p>
            <p><strong>Phone:</strong> <?php echo sanitize($client['phone']); ?></p>
            <p><strong>Report Date:</strong> <?php echo date('d M Y h:i A'); ?></p>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Details</th>
                    <th>Ref No</th>
                    <th class="text-right">Debit (Rs)</th>
                    <th class="text-right">Credit (Rs)</th>
                    <th class="text-right">Balance (Rs)</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($entries)): ?>
                <tr>
                    <td colspan="6" style="text-align:center;">No entries found.</td>
                </tr>
                <?php else: ?>
                    <?php 
                    $balance = 0; 
                    foreach($entries as $ent): 
                        $balance += $ent['debit'];
                        $balance -= $ent['credit'];
                    ?>
                    <tr>
                        <td><?php echo date('d-m-Y', strtotime($ent['date'])); ?></td>
                        <td><?php echo $ent['type']; ?></td>
                        <td><?php echo sanitize($ent['ref_no']); ?></td>
                        <td class="text-right"><?php echo $ent['debit'] > 0 ? number_format($ent['debit'], 2) : '-'; ?></td>
                        <td class="text-right"><?php echo $ent['credit'] > 0 ? number_format($ent['credit'], 2) : '-'; ?></td>
                        <td class="text-right"><strong><?php echo number_format($balance, 2); ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        
        <?php if(!empty($entries)): ?>
        <div style="margin-top: 30px; text-align: right;">
            <h3>Closing Balance: Rs. <?php echo number_format($balance, 2); ?> <small style="color:#666;">(<?php echo $balance > 0 ? 'Receivable' : ($balance < 0 ? 'Payable' : 'Settled'); ?>)</small></h3>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
