<?php
/**
 * AGROVISE - Packing Operations
 */

require_once '../includes/db.php';
require_once '../includes/functions.php';

requirePermission('packing');

$conn = getDBConnection();

$stmt = $conn->query("
    SELECT po.*, 
           b_p.batch_number as bulk_batch,
           b_pr.name as bulk_name,
           f_p.batch_number as finished_batch,
           f_pr.name as finished_name,
           f_p.quantity as finished_qty
    FROM packing_operations po
    JOIN purchasing b_p ON po.bulk_purchase_id = b_p.id
    JOIN products b_pr ON b_p.product_id = b_pr.id
    JOIN purchasing f_p ON po.finished_purchase_id = f_p.id
    JOIN products f_pr ON f_p.product_id = f_pr.id
    ORDER BY po.date_added DESC
");
$operations = $stmt->fetchAll();

$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Packing Operations - AGROVISE Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=ed3">
</head>
<body class="admin-body">
    <div class="admin-wrapper">
        <?php require __DIR__ . '/../includes/admin-sidebar.php'; ?>
        
        <main class="admin-main">
            <div class="admin-header">
                <h1><i class="fas fa-box-open"></i> Packing Operations</h1>
            </div>
            
            <?php if ($flash): ?>
            <div class="alert alert-<?php echo $flash['type']; ?>">
                <i class="fas fa-check-circle"></i> <?php echo $flash['message']; ?>
            </div>
            <?php endif; ?>
            
            <div class="action-bar" style="margin-bottom: 20px;">
                <a href="add-packing.php" class="btn btn-primary"><i class="fas fa-plus"></i> New Packing Operation</a>
            </div>
            
            <div class="data-card">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Bulk Consumed</th>
                                <th>Finished Product</th>
                                <th>Total Cost</th>
                                <th>Packing Cost</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($operations)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 20px;">No packing operations found.</td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($operations as $op): ?>
                                <tr>
                                    <td>#<?php echo $op['id']; ?></td>
                                    <td>
                                        <div><strong><?php echo sanitize($op['bulk_name']); ?></strong></div>
                                        <div style="font-size: 0.9em; color: gray;">
                                            Qty: <?php echo number_format($op['quantity_used'], 2); ?> | Batch: <?php echo sanitize($op['bulk_batch']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div><strong><?php echo sanitize($op['finished_name']); ?></strong></div>
                                        <div style="font-size: 0.9em; color: gray;">
                                            Produced: <?php echo number_format($op['finished_qty'], 2); ?> | Batch: <?php echo sanitize($op['finished_batch']); ?>
                                        </div>
                                    </td>
                                    <td style="font-weight: bold;">Rs <?php echo number_format($op['bulk_cost'] + $op['total_material_cost'], 2); ?></td>
                                    <td style="color: var(--primary-green);">Rs <?php echo number_format($op['packing_cost'], 2); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($op['date_added'])); ?></td>
                                    <td>
                                        <div class="action-btns">
                                            <a href="edit-packing.php?id=<?php echo $op['id']; ?>" class="btn-icon edit" title="Edit"><i class="fas fa-edit"></i></a>
                                            <a href="delete-packing.php?id=<?php echo $op['id']; ?>" class="btn-icon delete" onclick="return confirm('Deleting this operation will restore the batch quantities. Proceed?');">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
