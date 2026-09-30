<?php
/**
 * AGROVISE - Print Invoice Template
 */
require_once '../includes/db.php';
require_once '../includes/functions.php';
requirePermission('invoices');

$id = intval($_GET['id'] ?? 0);
$conn = getDBConnection();

$stmt = $conn->prepare("SELECT i.*, c.name, c.address, c.phone, c.cnic, c.area, e.name as officer_name, e.role as officer_role
                        FROM invoices i
                        JOIN clients c ON i.client_id = c.id
                        LEFT JOIN employees e ON i.employee_id = e.id
                        WHERE i.id = ?");
$stmt->execute([$id]);
$invoice = $stmt->fetch();

if (!$invoice) {
    die("Invoice not found.");
}

$itemsStmt = $conn->prepare("SELECT ii.*, p.name as product_name, purch.batch_number, purch.expiry_date
                             FROM invoice_items ii
                             JOIN products p ON ii.product_id = p.id
                             JOIN purchasing purch ON ii.purchasing_id = purch.id
                             WHERE ii.invoice_id = ?");
$itemsStmt->execute([$id]);
$items = $itemsStmt->fetchAll();

// Sales tax appears on the document ONLY when at least one line carries tax.
// A fully tax-free invoice prints with no mention of tax at all.
$hasTax = false;
$subTotal = 0.0;
$taxTotal = 0.0;
foreach ($items as $item) {
    $lineNet = $item['quantity'] * $item['price'];
    $lineTax = $lineNet * (floatval($item['sales_tax'] ?? 0) / 100);
    $subTotal += $lineNet;
    $taxTotal += $lineTax;
    if (floatval($item['sales_tax'] ?? 0) > 0) $hasTax = true;
}
$grandTotal = $subTotal + $taxTotal;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Delivery Challan / Invoice - <?php echo sanitize($invoice['invoice_no']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Montserrat', sans-serif; margin: 0; padding: 0; color: #333; background: #fff; }
        .invoice-container { width: 800px; margin: 20px auto; padding: 40px; border: 1px solid #ccc; }
        .header { display: flex; justify-content: space-between; border-bottom: 2px solid #333; padding-bottom: 20px; margin-bottom: 30px; }
        .company-details h1 { margin: 0; font-size: 32px; letter-spacing: 2px; }
        .invoice-details { text-align: right; }
        .bill-to p { margin: 4px 0; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 30px; margin-top: 30px;}
        th { background: #eee; padding: 12px; text-align: left; }
        td { padding: 12px; border-bottom: 1px dashed #ccc; }
        @media print {
            body { background: #fff; }
            .invoice-container { border: none; margin: 0; width: 100%; }
            .print-btn { display: none; }
        }
        .print-btn { position: fixed; top: 20px; right: 20px; padding: 10px 20px; background: #000; color: #fff; border: none; cursor: pointer; }
    </style>
</head>
<body>
    <button class="print-btn" onclick="window.print()">Print Invoice</button>
    <div class="invoice-container">
        <div class="header">
            <div class="company-details">
                <h1>AGROVISE</h1>
                <p>123 Agriculture Road, Farming District</p>
                <p>Phone: +1 234 567 8900</p>
            </div>
            <div class="invoice-details">
                <h2>DELIVERY CHALLAN</h2>
                <p>Invoice No: <?php echo sanitize($invoice['invoice_no']); ?></p>
                <p>Date: <?php echo date('F d, Y', strtotime($invoice['date'])); ?></p>
                <?php if (!empty($invoice['officer_name'])): ?>
                <p>Sales Officer: <strong><?php echo sanitize($invoice['officer_name']); ?></strong></p>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="bill-to">
            <h3>Delivered To:</h3>
            <p><strong><?php echo sanitize($invoice['name']); ?></strong></p>
            <p><strong>Location:</strong> <?php echo sanitize($invoice['address']); ?>, <?php echo sanitize($invoice['area']); ?></p>
            <p><strong>Contact:</strong> <?php echo sanitize($invoice['phone']); ?></p>
            <p><strong>CNIC:</strong> <?php echo sanitize($invoice['cnic']); ?></p>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>S.No</th>
                    <th>Product Details</th>
                    <th>Batch Number</th>
                    <th>Expiry</th>
                    <th>Packs/Carton</th>
                    <th>Quantity Delivered</th>
                    <?php if ($hasTax): ?>
                    <th style="text-align:right;">Rate (Rs)</th>
                    <th style="text-align:right;">Tax %</th>
                    <th style="text-align:right;">Amount (Rs)</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php $idx=1; foreach ($items as $item): ?>
                <tr>
                    <td><?php echo $idx++; ?></td>
                    <td><strong><?php echo sanitize($item['product_name']); ?></strong></td>
                    <td><?php echo sanitize($item['batch_number']); ?></td>
                    <td><?php echo $item['expiry_date'] ? date('M d, Y', strtotime($item['expiry_date'])) : '—'; ?></td>
                    <td><?php echo sanitize($item['packs_per_carton']); ?></td>
                    <td><strong><?php echo number_format($item['quantity'], 2); ?></strong></td>
                    <?php if ($hasTax): ?>
                    <td style="text-align:right;"><?php echo number_format($item['price'], 2); ?></td>
                    <td style="text-align:right;"><?php echo floatval($item['sales_tax']) > 0 ? rtrim(rtrim(number_format($item['sales_tax'], 2), '0'), '.') . '%' : '—'; ?></td>
                    <td style="text-align:right;"><strong><?php echo number_format($item['quantity'] * $item['price'] * (1 + floatval($item['sales_tax']) / 100), 2); ?></strong></td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($hasTax): ?>
        <div style="display: flex; justify-content: flex-end; margin-bottom: 30px;">
            <table style="width: 320px; margin: 0;">
                <tr>
                    <td style="text-align:right; border:none; padding:6px 12px;">Subtotal</td>
                    <td style="text-align:right; border:none; padding:6px 12px; width:130px;">Rs. <?php echo number_format($subTotal, 2); ?></td>
                </tr>
                <tr>
                    <td style="text-align:right; border:none; padding:6px 12px;">Sales Tax</td>
                    <td style="text-align:right; border:none; padding:6px 12px;">Rs. <?php echo number_format($taxTotal, 2); ?></td>
                </tr>
                <tr>
                    <td style="text-align:right; border-top:2px solid #333; padding:8px 12px;"><strong>Grand Total</strong></td>
                    <td style="text-align:right; border-top:2px solid #333; padding:8px 12px;"><strong>Rs. <?php echo number_format($grandTotal, 2); ?></strong></td>
                </tr>
            </table>
        </div>
        <?php endif; ?>

        <div style="margin-top: 60px; display: flex; justify-content: space-between;">
            <div style="width: 200px; border-top: 1px solid #333; text-align: center; padding-top: 5px;">
                Receiver's Signature
            </div>
            <div style="width: 200px; border-top: 1px solid #333; text-align: center; padding-top: 5px;">
                Authorized Signatory
            </div>
        </div>
        
        <div style="margin-top: 40px; text-align: center; font-size: 12px; color: #666;">
            Prepared by AGROVISE System | Thank you for your business.
        </div>
    </div>
</body>
</html>
