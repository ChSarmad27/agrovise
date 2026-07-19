<?php
/**
 * AGROVISE - Product Ledger
 */
require_once '../includes/db.php';
require_once '../includes/functions.php';
requirePermission('ledgers');

$product_id = intval($_GET['product_id'] ?? 0);
$conn = getDBConnection();

$stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$product_id]);
$product = $stmt->fetch();

if (!$product) {
    die("Product not found.");
}

$entries = [];

// Vendors (Stock In)
// Since vendors don't have a distinct "date" field other than created_at, we use DATE(created_at)
$vendStmt = $conn->prepare("SELECT 'Vendor / Stock In' as details, vendor_no as ref_no, DATE(created_at) as date, quantity as stock_in, 0 as stock_out, created_at FROM vendors WHERE product_id = ?");
$vendStmt->execute([$product_id]);
$vendorEntries = $vendStmt->fetchAll(PDO::FETCH_ASSOC);

// Invoices (Stock Out)
$invStmt = $conn->prepare("
    SELECT 'Invoice / Sale' as details, i.invoice_no as ref_no, i.date as date, 0 as stock_in, ii.quantity as stock_out, i.created_at 
    FROM invoice_items ii 
    JOIN invoices i ON ii.invoice_id = i.id 
    WHERE ii.product_id = ?
");
$invStmt->execute([$product_id]);
$invoiceEntries = $invStmt->fetchAll(PDO::FETCH_ASSOC);

$entries = array_merge($vendorEntries, $invoiceEntries);

// Sort
usort($entries, function($a, $b) {
    $dateA = strtotime($a['date']);
    $dateB = strtotime($b['date']);
    if ($dateA == $dateB) {
        return strtotime($a['created_at']) <=> strtotime($b['created_at']);
    }
    return $dateA <=> $dateB;
});

// Calculate Initial Stock
// Since current stock in DB matches initial + stock_in - stock_out
$totalIn = array_sum(array_column($entries, 'stock_in'));
$totalOut = array_sum(array_column($entries, 'stock_out'));
$initialStock = $product['stock_quantity'] - $totalIn + $totalOut;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Product Ledger - <?php echo sanitize($product['name']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Montserrat', sans-serif; background: #fff; color: #333; margin: 0; padding: 20px; }
        .container { max-width: 900px; margin: 0 auto; padding: 20px; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #2e7d32; padding-bottom: 20px; }
        .header h1 { color: #2e7d32; margin: 0; text-transform: uppercase; letter-spacing: 1px; }
        .header h2 { margin: 10px 0 0 0; color: #555; }
        
        .product-info { margin-bottom: 20px; font-size: 14px; }
        .product-info p { margin: 5px 0; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 10px; font-size: 14px; }
        th { background-color: #2e7d32; color: #fff; text-align: left; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        
        .action-btn { position: fixed; top: 20px; right: 20px; background: #2e7d32; color: #fff; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-family: inherit; font-weight: 600; }
        @media print { .action-btn { display: none; } }
    </style>
</head>
<body>
    <button class="action-btn" onclick="window.print()">Print Ledger</button>

    <div class="container">
        <div class="header">
            <h1>AGROVISE</h1>
            <h2>PRODUCT LEDGER</h2>
        </div>
        
        <div class="product-info">
            <p><strong>Product Name:</strong> <?php echo sanitize($product['name']); ?></p>
            <p><strong>Category:</strong> <span style="text-transform: capitalize;"><?php echo sanitize($product['category']); ?></span></p>
            <p><strong>Current Stock in System:</strong> <?php echo $product['stock_quantity']; ?></p>
            <p><strong>Report Date:</strong> <?php echo date('d M Y h:i A'); ?></p>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Details</th>
                    <th>Ref No</th>
                    <th class="text-center">Stock In</th>
                    <th class="text-center">Stock Out</th>
                    <th class="text-center">Balance</th>
                </tr>
            </thead>
            <tbody>
                <!-- Initial Stock -->
                <tr>
                    <td><?php echo date('d-m-Y', strtotime($product['created_at'])); ?></td>
                    <td><strong>Initial System Stock</strong></td>
                    <td>-</td>
                    <td class="text-center">-</td>
                    <td class="text-center">-</td>
                    <td class="text-center"><strong><?php echo $initialStock; ?></strong></td>
                </tr>
                
                <?php 
                $balance = $initialStock; 
                foreach($entries as $ent): 
                    $balance += $ent['stock_in'];
                    $balance -= $ent['stock_out'];
                ?>
                <tr>
                    <td><?php echo date('d-m-Y', strtotime($ent['date'])); ?></td>
                    <td><?php echo $ent['details']; ?></td>
                    <td><?php echo sanitize($ent['ref_no']); ?></td>
                    <td class="text-center" style="color:var(--primary-green);"><?php echo $ent['stock_in'] > 0 ? '+'.$ent['stock_in'] : '-'; ?></td>
                    <td class="text-center" style="color:#d32f2f;"><?php echo $ent['stock_out'] > 0 ? '-'.$ent['stock_out'] : '-'; ?></td>
                    <td class="text-center"><strong><?php echo $balance; ?></strong></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div style="margin-top: 30px; text-align: right;">
            <h3>Closing Stock Balance: <?php echo $balance; ?> units</h3>
        </div>
    </div>
</body>
</html>
