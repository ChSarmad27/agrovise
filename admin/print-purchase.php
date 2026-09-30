<?php
/**
 * AGROVISE - Print Purchase Bill
 * Same document template as the sales invoice (print-invoice.php), but for
 * a purchasing lot: vendor details, batch, quantity, unit price and total.
 */
require_once '../includes/db.php';
require_once '../includes/functions.php';
requirePermission('purchasing');

$id = intval($_GET['id'] ?? 0);
$conn = getDBConnection();

$stmt = $conn->prepare("SELECT p.*, pr.name AS product_name, pr.packing_type,
                               v.name AS vendor_name, v.contact_person, v.phone AS vendor_phone, v.address AS vendor_address
                        FROM purchasing p
                        JOIN products pr ON p.product_id = pr.id
                        LEFT JOIN vendors v ON p.vendor_id = v.id
                        WHERE p.id = ?");
$stmt->execute([$id]);
$purchase = $stmt->fetch();

if (!$purchase) {
    die("Purchase record not found.");
}

$billNo = 'PUR-' . str_pad($purchase['id'], 6, '0', STR_PAD_LEFT);

// purchasing.quantity is the LIVE stock level (depleted by invoices/packing);
// the bill shows the originally purchased quantity = total / unit price.
$origQty = floatval($purchase['purchase_price']) > 0
    ? floatval($purchase['total_price']) / floatval($purchase['purchase_price'])
    : floatval($purchase['quantity']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Purchase Bill - <?php echo $billNo; ?></title>
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
    <button class="print-btn" onclick="window.print()">Print Bill</button>
    <div class="invoice-container">
        <div class="header">
            <div class="company-details">
                <h1>AGROVISE</h1>
                <p>123 Agriculture Road, Farming District</p>
                <p>Phone: +1 234 567 8900</p>
            </div>
            <div class="invoice-details">
                <h2>PURCHASE BILL</h2>
                <p>Bill No: <?php echo $billNo; ?></p>
                <p>Date: <?php echo date('F d, Y', strtotime($purchase['date_added'])); ?></p>
                <p>Stock Type: <strong><?php echo sanitize($purchase['type']); ?></strong></p>
            </div>
        </div>

        <div class="bill-to">
            <h3>Purchased From:</h3>
            <?php if (!empty($purchase['vendor_name'])): ?>
            <p><strong><?php echo sanitize($purchase['vendor_name']); ?></strong></p>
            <?php if (!empty($purchase['contact_person'])): ?>
            <p><strong>Contact Person:</strong> <?php echo sanitize($purchase['contact_person']); ?></p>
            <?php endif; ?>
            <?php if (!empty($purchase['vendor_phone'])): ?>
            <p><strong>Phone:</strong> <?php echo sanitize($purchase['vendor_phone']); ?></p>
            <?php endif; ?>
            <?php if (!empty($purchase['vendor_address'])): ?>
            <p><strong>Address:</strong> <?php echo sanitize($purchase['vendor_address']); ?></p>
            <?php endif; ?>
            <?php else: ?>
            <p><em>No vendor recorded for this purchase.</em></p>
            <?php endif; ?>
        </div>

        <table>
            <thead>
                <tr>
                    <th>S.No</th>
                    <th>Product Details</th>
                    <th>Batch Number</th>
                    <th>Expiry</th>
                    <th>Quantity</th>
                    <th style="text-align:right;">Unit Price (Rs)</th>
                    <th style="text-align:right;">Amount (Rs)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>1</td>
                    <td>
                        <strong><?php echo sanitize($purchase['product_name']); ?></strong>
                        <?php if (($purchase['packing_type'] ?? 'None') !== 'None'): ?>
                        <br><span style="font-size:12px; color:#666;"><?php echo sanitize($purchase['packing_type']); ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo sanitize($purchase['batch_number']); ?></td>
                    <td><?php echo $purchase['expiry_date'] ? date('M d, Y', strtotime($purchase['expiry_date'])) : '—'; ?></td>
                    <td><strong><?php echo number_format($origQty, 2); ?></strong></td>
                    <td style="text-align:right;"><?php echo number_format($purchase['purchase_price'], 2); ?></td>
                    <td style="text-align:right;"><strong><?php echo number_format($purchase['total_price'], 2); ?></strong></td>
                </tr>
            </tbody>
        </table>

        <div style="display: flex; justify-content: flex-end; margin-bottom: 30px;">
            <table style="width: 320px; margin: 0;">
                <tr>
                    <td style="text-align:right; border-top:2px solid #333; padding:8px 12px;"><strong>Grand Total</strong></td>
                    <td style="text-align:right; border-top:2px solid #333; padding:8px 12px; width:130px;"><strong>Rs. <?php echo number_format($purchase['total_price'], 2); ?></strong></td>
                </tr>
            </table>
        </div>

        <div style="margin-top: 60px; display: flex; justify-content: space-between;">
            <div style="width: 200px; border-top: 1px solid #333; text-align: center; padding-top: 5px;">
                Vendor's Signature
            </div>
            <div style="width: 200px; border-top: 1px solid #333; text-align: center; padding-top: 5px;">
                Authorized Signatory
            </div>
        </div>

        <div style="margin-top: 40px; text-align: center; font-size: 12px; color: #666;">
            Prepared by AGROVISE System | Purchase record #<?php echo $purchase['id']; ?>.
        </div>
    </div>
</body>
</html>
