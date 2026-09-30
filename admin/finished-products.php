<?php
/**
 * AGROVISE - Finished Stock (own-brand packed products)
 * FINISHED lots created by our packing operations: bulk we already bought,
 * repacked into our own packaging. These are deliberately NOT shown on the
 * purchasing page — they were produced, not purchased. A FINISHED lot bought
 * ready-made from a vendor stays on purchasing.php.
 */
require_once '../includes/db.php';
require_once '../includes/functions.php';
requireAdminLogin();
if (!hasPermission('purchasing') && !hasPermission('packing')) {
    setFlashMessage('error', 'You do not have permission to access that section. Contact an administrator.');
    header('Location: dashboard.php');
    exit;
}

$conn = getDBConnection();

$rows = $conn->query("
    SELECT p.id, p.batch_number, p.quantity, p.purchase_price, p.total_price, p.date_added, p.expiry_date,
           pr.name AS product_name, pr.packing_type,
           po.id AS op_id, po.pack_label, po.cartons AS cartons_produced, po.quantity_used,
           ps.packs_per_carton,
           bpr.name AS bulk_name, bp.batch_number AS bulk_batch
    FROM purchasing p
    JOIN products pr ON pr.id = p.product_id
    JOIN packing_operations po ON po.finished_purchase_id = p.id
    JOIN purchasing bp ON bp.id = po.bulk_purchase_id
    JOIN products bpr ON bpr.id = bp.product_id
    LEFT JOIN pack_sizes ps ON ps.id = po.pack_size_id
    WHERE p.type = 'FINISHED'
    ORDER BY p.date_added DESC
")->fetchAll();

$totalValue = 0.0;
foreach ($rows as $r) $totalValue += floatval($r['quantity']) * floatval($r['purchase_price']);

$flash = getFlashMessage();
$fmt = fn($v) => rtrim(rtrim(number_format($v, 2), '0'), '.');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finished Stock - AGROVISE Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=ed3">
</head>
<body class="admin-body">
    <div class="admin-wrapper">
        <?php require __DIR__ . '/../includes/admin-sidebar.php'; ?>

        <main class="admin-main">
            <div class="admin-header">
                <h1><i class="fas fa-box"></i> Finished Stock <span style="font-size: 0.55em; color: #888; font-weight: 400;">(packed in-house)</span></h1>
            </div>

            <?php if ($flash): ?>
            <div class="alert alert-<?php echo $flash['type']; ?>"><?php echo $flash['message']; ?></div>
            <?php endif; ?>

            <div class="action-bar" style="margin-bottom: 20px; display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                <?php if (hasPermission('packing')): ?>
                <a href="add-packing.php" class="btn btn-primary"><i class="fas fa-box-open"></i> New Packing Operation</a>
                <?php endif; ?>
                <?php if (hasPermission('purchasing')): ?>
                <a href="purchasing.php" class="btn btn-outline"><i class="fas fa-shopping-cart"></i> Purchased Stock</a>
                <?php endif; ?>
                <span class="badge" style="background: #e8f5e9; color: #2e7d32; font-size: 0.85rem;">Stock value (cost): Rs <?php echo number_format($totalValue, 2); ?></span>
            </div>

            <div class="data-card">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Finished Product</th>
                                <th>Made From (Bulk)</th>
                                <th>In Stock</th>
                                <th>Unit Cost</th>
                                <th>Stock Value</th>
                                <th>Expiry</th>
                                <th>Produced</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($rows)): ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 20px;">
                                    No in-house packed stock yet. Finished lots appear here after a
                                    <a href="add-packing.php">packing operation</a>.
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($rows as $r): ?>
                                <tr>
                                    <td>#<?php echo $r['id']; ?></td>
                                    <td>
                                        <div style="font-weight: 500; font-size: 1.05rem;"><?php echo sanitize($r['product_name']); ?></div>
                                        <div style="font-size: 0.85em; color: gray;">Batch: <?php echo sanitize($r['batch_number']); ?></div>
                                        <?php if (!empty($r['pack_label'])): ?>
                                        <div style="font-size: 0.85em; color: var(--primary-green);"><i class="fas fa-box"></i> <?php echo sanitize($r['pack_label']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div><strong><?php echo sanitize($r['bulk_name']); ?></strong></div>
                                        <div style="font-size: 0.85em; color: gray;">
                                            Batch: <?php echo sanitize($r['bulk_batch']); ?> | Used: <?php echo $fmt($r['quantity_used']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <strong><?php echo $fmt($r['quantity']); ?></strong> pack<?php echo floatval($r['quantity']) == 1 ? '' : 's'; ?>
                                        <?php if (!empty($r['packs_per_carton']) && intval($r['packs_per_carton']) > 0): ?>
                                        <div style="font-size: 0.85em; color: gray;">≈ <?php echo $fmt(floatval($r['quantity']) / intval($r['packs_per_carton'])); ?> carton(s)</div>
                                        <?php endif; ?>
                                    </td>
                                    <td>Rs <?php echo number_format($r['purchase_price'], 2); ?></td>
                                    <td style="font-weight: bold; color: var(--primary-green);">Rs <?php echo number_format(floatval($r['quantity']) * floatval($r['purchase_price']), 2); ?></td>
                                    <td><?php echo $r['expiry_date'] ? date('M d, Y', strtotime($r['expiry_date'])) : '—'; ?></td>
                                    <td><?php echo date('M d, Y', strtotime($r['date_added'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <p style="font-size: 0.8rem; color: #888; margin-top: 14px;">
                <i class="fas fa-info-circle"></i> Unit cost carries the bulk + material cost forward from the packing
                operation. Deleting an operation on the <a href="packing.php">Packing</a> page removes its finished lot
                and restores the bulk stock.
            </p>
        </main>
    </div>
</body>
</html>
