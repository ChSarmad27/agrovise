<?php
/**
 * AGROVISE - Invoices List
 */
require_once '../includes/db.php';
require_once '../includes/functions.php';
requirePermission('invoices');

$conn = getDBConnection();

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->beginTransaction();
    try {
        $items = $conn->prepare("SELECT purchasing_id, quantity FROM invoice_items WHERE invoice_id = ?");
        $items->execute([$id]);
        foreach ($items->fetchAll() as $item) {
            $update = $conn->prepare("UPDATE purchasing SET quantity = quantity + ? WHERE id = ?");
            $update->execute([$item['quantity'], $item['purchasing_id']]);
        }
        $conn->prepare("DELETE FROM invoices WHERE id = ?")->execute([$id]);
        $conn->commit();
        setFlashMessage('success', 'Invoice deleted and stock restored.');
    } catch(PDOException $e) {
        $conn->rollBack();
        setFlashMessage('error', 'Error deleting invoice.');
    }
    header('Location: invoices.php');
    exit;
}

$search = trim($_GET['search'] ?? '');

$query = "
    SELECT i.*, c.name as client_name, po.name as policy_name
    FROM invoices i
    JOIN clients c ON i.client_id = c.id
    LEFT JOIN policies po ON po.id = i.policy_id
    WHERE 1=1
";
$params = [];

if (!empty($search)) {
    $query .= " AND (i.invoice_no LIKE ? OR c.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " ORDER BY i.created_at DESC";
$stmt = $conn->prepare($query);
$stmt->execute($params);
$invoices = $stmt->fetchAll();

$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoices - AGROVISE</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=ed3">
</head>
<body class="admin-body">
    <div class="admin-wrapper">
        <?php require __DIR__ . '/../includes/admin-sidebar.php'; ?>
        
        <main class="admin-main">
            <div class="admin-header">
                <h1><i class="fas fa-file-invoice-dollar"></i> Invoices</h1>
            </div>
            
            <?php if ($flash): ?>
            <div class="alert alert-<?php echo $flash['type']; ?>">
                <?php echo $flash['message']; ?>
            </div>
            <?php endif; ?>
            
            <div class="data-card">
                <div class="data-card-header">
                    <h2><i class="fas fa-list"></i> Invoice List</h2>
                    <a href="add-invoice.php" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Create Invoice</a>
                </div>
                
                <div style="padding: 20px;">
                    <form method="GET" style="display: flex; gap: 10px; margin-bottom: 20px;">
                        <input type="text" name="search" class="form-input" placeholder="Search..." value="<?php echo sanitize($search); ?>" style="max-width: 300px;">
                        <button type="submit" class="btn btn-secondary btn-sm"><i class="fas fa-search"></i> Search</button>
                    </form>
                    
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Invoice No</th>
                                <th>Date</th>
                                <th>Client</th>
                                <th>Policy</th>
                                <th>Total Amount</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($invoices as $inv): ?>
                            <tr>
                                <td><strong><?php echo sanitize($inv['invoice_no']); ?></strong></td>
                                <td><?php echo date('d M Y', strtotime($inv['date'])); ?></td>
                                <td><?php echo sanitize($inv['client_name']); ?></td>
                                <td>
                                    <?php if (!empty($inv['policy_name'])): ?>
                                        <span style="display:inline-block; padding:3px 9px; font-size:0.66rem; font-weight:600; background:rgba(194,160,79,0.16); color:#7a6320;">
                                            <?php echo sanitize($inv['policy_name']); ?>
                                        </span>
                                    <?php else: ?>
                                        <small style="color:#8a8f83;">&mdash;</small>
                                    <?php endif; ?>
                                </td>
                                <td><strong style="color: var(--primary-green);">Rs. <?php echo number_format($inv['total_amount'], 2); ?></strong></td>
                                <td>
                                    <div class="action-btns">
                                        <a href="print-invoice.php?id=<?php echo $inv['id']; ?>" target="_blank" class="btn-icon" style="background:#17a2b8; color:white;" title="Print"><i class="fas fa-print"></i></a>
                                        <a href="edit-invoice.php?id=<?php echo $inv['id']; ?>" class="btn-icon" style="background:var(--warning); color:white;" title="Edit"><i class="fas fa-edit"></i></a>
                                        <a href="?delete=<?php echo $inv['id']; ?>" class="btn-icon delete" title="Delete" onclick="return confirm('Ensure stock is restored upon deletion.');"><i class="fas fa-trash"></i></a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
