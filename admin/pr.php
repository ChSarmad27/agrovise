<?php
/**
 * AGROVISE - Payment Receipts
 */

require_once '../includes/db.php';
require_once '../includes/functions.php';
requirePermission('payments');

$conn = getDBConnection();

// Handle Delete (also reverts the bank balance and removes the linked deposit transaction)
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = intval($_GET['delete']);
    try {
        $conn->beginTransaction();

        $stmt = $conn->prepare("SELECT * FROM payment_receipts WHERE id = ?");
        $stmt->execute([$id]);
        $pr = $stmt->fetch();

        if ($pr) {
            // Find the banking transaction created with this PR (same matching as edit-pr.php)
            if (!empty($pr['bank_id'])) {
                $descMatch = "Payment Received via PR: " . $pr['pr_no'];
                $tStmt = $conn->prepare("SELECT id FROM transactions WHERE description = ? AND amount = ? AND bank_id = ?");
                $tStmt->execute([$descMatch, $pr['amount_received'], $pr['bank_id']]);
                $trans = $tStmt->fetch();

                if ($trans) {
                    $conn->prepare("UPDATE banking SET balance = balance - ? WHERE id = ?")
                         ->execute([$pr['amount_received'], $pr['bank_id']]);
                    $conn->prepare("DELETE FROM transactions WHERE id = ?")->execute([$trans['id']]);
                }
            }

            $conn->prepare("DELETE FROM payment_receipts WHERE id = ?")->execute([$id]);
        }

        $conn->commit();
        setFlashMessage('success', 'Payment receipt deleted and banking records adjusted!');
    } catch (PDOException $e) {
        $conn->rollBack();
        setFlashMessage('error', dbError($e));
    }
    header('Location: pr.php');
    exit;
}

$search = trim($_GET['search'] ?? '');

$query = "
    SELECT pr.*, c.name as client_name, b.account_name 
    FROM payment_receipts pr 
    JOIN clients c ON pr.client_id = c.id 
    LEFT JOIN banking b ON pr.bank_id = b.id
    WHERE 1=1
";
$params = [];

if (!empty($search)) {
    $query .= " AND (pr.pr_no LIKE ? OR c.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " ORDER BY pr.date DESC, pr.created_at DESC";
$stmt = $conn->prepare($query);
$stmt->execute($params);
$receipts = $stmt->fetchAll();

$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Receipts - AGROVISE Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=ed3">
</head>
<body class="admin-body">
    <div class="admin-wrapper">
        <?php require __DIR__ . '/../includes/admin-sidebar.php'; ?>
        
        <main class="admin-main">
            <div class="admin-header">
                <h1><i class="fas fa-receipt"></i> Payment Receipts (PR)</h1>
                <div class="admin-user">
                    <span>Welcome, <?php echo sanitize($_SESSION['admin_username']); ?></span>
                </div>
            </div>
            
            <?php if ($flash): ?>
            <div class="alert alert-<?php echo $flash['type']; ?>">
                <?php echo $flash['message']; ?>
            </div>
            <?php endif; ?>
            
            <div class="data-card">
                <div class="data-card-header">
                    <h2><i class="fas fa-list"></i> PR List</h2>
                    <a href="add-pr.php" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus"></i> Create Receipt
                    </a>
                </div>
                
                <div style="padding: 20px;">
                    <form method="GET" style="display: flex; gap: 10px; margin-bottom: 20px;">
                        <input type="text" name="search" class="form-input" placeholder="Search by PR No or Client..." value="<?php echo sanitize($search); ?>" style="max-width: 300px;">
                        <button type="submit" class="btn btn-secondary btn-sm">Search</button>
                    </form>
                    
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Client</th>
                                <th>PR No</th>
                                <th>Deposit Account</th>
                                <th>Amount Received</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($receipts)): ?>
                            <tr><td colspan="6" style="text-align: center;">No payment receipts found.</td></tr>
                            <?php endif; ?>
                            
                            <?php foreach ($receipts as $pr): ?>
                            <tr>
                                <td><?php echo date('d M Y', strtotime($pr['date'])); ?></td>
                                <td><?php echo sanitize($pr['client_name']); ?></td>
                                <td><strong><?php echo sanitize($pr['pr_no']); ?></strong></td>
                                <td><span class="badge" style="background:#e3f2fd; color:#1565c0;"><?php echo sanitize($pr['account_name'] ?? 'Manual/Legacy'); ?></span></td>
                                <td><strong style="color: var(--primary-green);">Rs. <?php echo number_format($pr['amount_received'], 2); ?></strong></td>
                                <td>
                                    <div class="action-btns">
                                        <a href="edit-pr.php?id=<?php echo $pr['id']; ?>" class="btn-icon edit" title="Edit"><i class="fas fa-edit"></i></a>
                                        <a href="?delete=<?php echo $pr['id']; ?>" class="btn-icon delete" title="Delete" onclick="return confirm('Delete this receipt? Note: This will NOT delete the banking transaction automatically.');"><i class="fas fa-trash"></i></a>
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
