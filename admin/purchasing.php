<?php
/**
 * AGROVISE - Purchasing
 */

require_once '../includes/db.php';
require_once '../includes/functions.php';

requirePermission('purchasing');

$conn = getDBConnection();

// Fetch purchases
$stmt = $conn->query("
    SELECT p.*, pr.name as product_name
    FROM purchasing p
    JOIN products pr ON p.product_id = pr.id
    ORDER BY p.date_added DESC
");
$purchases = $stmt->fetchAll();

// Handle Delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $del_id = intval($_GET['delete']);
    try {
        $delStmt = $conn->prepare("DELETE FROM purchasing WHERE id = ?");
        $delStmt->execute([$del_id]);
        setFlashMessage('success', 'Purchase record deleted successfully!');
    } catch (PDOException $e) {
        setFlashMessage('error', 'Cannot delete this record because it is referenced in other modules (e.g. Packing or Invoices).');
    }
    header('Location: purchasing.php');
    exit;
}

$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchasing - AGROVISE Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=ed3">
</head>
<body class="admin-body">
    <div class="admin-wrapper">
        <?php require __DIR__ . '/../includes/admin-sidebar.php'; ?>
        
        <main class="admin-main">
            <div class="admin-header">
                <h1><i class="fas fa-shopping-cart"></i> Purchasing Management</h1>
            </div>
            
            <?php if ($flash): ?>
            <div class="alert alert-<?php echo $flash['type']; ?>">
                <i class="fas fa-check-circle"></i> <?php echo $flash['message']; ?>
            </div>
            <?php endif; ?>
            
            <div class="action-bar" style="margin-bottom: 20px;">
                <a href="add-purchase.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add Purchase</a>
            </div>
            
            <div class="data-card">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Product</th>
                                <th>Batch / Type</th>
                                <th>Quantity</th>
                                <th>Purchase Price</th>
                                <th>Total Cost</th>
                                <th>Date</th>
                                <th class="action-btns">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($purchases)): ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 20px;">No purchases found.</td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($purchases as $p): ?>
                                <tr>
                                    <td>#<?php echo $p['id']; ?></td>
                                    <td style="font-weight: 500; font-size: 1.1rem;"><?php echo sanitize($p['product_name']); ?></td>
                                    <td>
                                        <div><strong>Batch:</strong> <?php echo sanitize($p['batch_number']); ?></div>
                                        <span class="badge" style="background: var(--light-gray); color: var(--text-dark); margin-top: 5px;"><?php echo sanitize($p['type']); ?></span>
                                    </td>
                                    <td><?php echo number_format($p['quantity'], 2); ?></td>
                                    <td>Rs <?php echo number_format($p['purchase_price'], 2); ?></td>
                                    <td style="font-weight: bold; color: var(--primary-green);">Rs <?php echo number_format($p['total_price'], 2); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($p['date_added'])); ?></td>
                                    <td class="action-btns">
                                        <div style="display: flex; gap: 5px;">
                                            <a href="edit-purchase.php?id=<?php echo $p['id']; ?>" class="btn-icon edit" title="Edit"><i class="fas fa-edit"></i></a>
                                            <a href="?delete=<?php echo $p['id']; ?>" class="btn-icon delete" title="Delete" onclick="return confirm('Are you sure you want to delete this purchase record? Note: Deletion may fail if this item is used in packing or invoices.');"><i class="fas fa-trash"></i></a>
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
