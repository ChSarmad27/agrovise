<?php
/**
 * AGROVISE - Banking
 */
require_once '../includes/db.php';
require_once '../includes/functions.php';
requirePermission('banking');

$conn = getDBConnection();

$accountsStmt = $conn->query("SELECT * FROM banking ORDER BY account_name");
$accounts = $accountsStmt->fetchAll();

$transStmt = $conn->query("
    SELECT t.*, b.account_name 
    FROM transactions t 
    JOIN banking b ON t.bank_id = b.id 
    ORDER BY t.transaction_date DESC, t.id DESC LIMIT 50
");
$transactions = $transStmt->fetchAll();
// Handle Account Delete
if (isset($_GET['delete_account']) && is_numeric($_GET['delete_account'])) {
    $acc_id = intval($_GET['delete_account']);
    try {
        $conn->prepare("DELETE FROM banking WHERE id = ?")->execute([$acc_id]);
        setFlashMessage('success', 'Bank account deleted successfully!');
    } catch(PDOException $e) {
        setFlashMessage('error', 'Cannot delete account (it has transaction history).');
    }
    header('Location: banking.php');
    exit;
}

// Handle Transaction Delete
if (isset($_GET['delete_transaction']) && is_numeric($_GET['delete_transaction'])) {
    $tx_id = intval($_GET['delete_transaction']);
    try {
        $conn->beginTransaction();
        $stmt = $conn->prepare("SELECT bank_id, amount, type, category, description FROM transactions WHERE id = ?");
        $stmt->execute([$tx_id]);
        $tx = $stmt->fetch();

        // Protect linked records: PR deposits and paid expense reimbursements must
        // be removed from their own page so the linked row stays consistent.
        $linked = $conn->prepare("SELECT id FROM expense_claims WHERE paid_transaction_id = ?");
        $linked->execute([$tx_id]);
        if ($tx && ((strpos($tx['description'] ?? '', 'Payment Received via PR:') === 0) || $tx['category'] === 'Employee Expenses' || $linked->fetch())) {
            $conn->rollBack();
            setFlashMessage('error', 'This transaction is linked to a Payment Receipt or an Expense claim — remove it from that page instead.');
            header('Location: banking.php');
            exit;
        }

        if ($tx) {
            if ($tx['type'] == 'DEPOSIT') {
                $conn->prepare("UPDATE banking SET balance = balance - ? WHERE id = ?")->execute([$tx['amount'], $tx['bank_id']]);
            } else {
                $conn->prepare("UPDATE banking SET balance = balance + ? WHERE id = ?")->execute([$tx['amount'], $tx['bank_id']]);
            }
            $conn->prepare("DELETE FROM transactions WHERE id = ?")->execute([$tx_id]);
        }
        $conn->commit();
        setFlashMessage('success', 'Transaction deleted and balance adjusted!');
    } catch(PDOException $e) {
        $conn->rollBack();
        setFlashMessage('error', dbError($e));
    }
    header('Location: banking.php');
    exit;
}


$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Banking & Transactions - AGROVISE</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=ed3">
</head>
<body class="admin-body">
    <div class="admin-wrapper">
        <?php require __DIR__ . '/../includes/admin-sidebar.php'; ?>
        <main class="admin-main">
            <div class="admin-header"><h1><i class="fas fa-university"></i> Banking Overview</h1></div>
            
            <?php if ($flash): ?>
            <div class="alert alert-<?php echo $flash['type']; ?>">
                <?php echo $flash['message']; ?>
            </div>
            <?php endif; ?>
            
            <div class="action-bar" style="margin-bottom: 20px;">
                <a href="add-account.php" class="btn btn-secondary"><i class="fas fa-plus"></i> New Account</a>
                <a href="add-transaction.php" class="btn btn-primary"><i class="fas fa-exchange-alt"></i> New Transaction</a>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
                <?php 
                $totalBal = 0;
                foreach ($accounts as $acc): 
                    $totalBal += $acc['balance'];
                ?>
                <div class="dashboard-stat" style="border-left: 5px solid var(--primary-green);">
                    <div class="stat-icon"><i class="fas fa-wallet"></i></div>
                    <div class="stat-content">
                        <h3><?php echo sanitize($acc['account_name']); ?></h3>
                        <div class="stat-number">Rs. <?php echo number_format($acc['balance'], 2); ?></div>
                        <div class="stat-desc"><?php echo sanitize($acc['account_number']); ?></div>
                        <div style="margin-top: 10px; display: flex; gap: 10px;">
                            <a href="edit-account.php?id=<?php echo $acc['id']; ?>" class="btn-icon edit" style="font-size: 0.8em; color: white;"><i class="fas fa-edit"></i></a>
                            <a href="?delete_account=<?php echo $acc['id']; ?>" class="btn-icon delete" style="font-size: 0.8em; color: rgba(255,255,255,0.7);" onclick="return confirm('Delete this account? History will be lost.');"><i class="fas fa-trash"></i></a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <div class="dashboard-stat" style="background-color: var(--dark-green); color: white;">
                    <div class="stat-icon" style="color: rgba(255,255,255,0.2);"><i class="fas fa-piggy-bank"></i></div>
                    <div class="stat-content">
                        <h3 style="color: rgba(255,255,255,0.8);">Total Liquidity</h3>
                        <div class="stat-number">Rs. <?php echo number_format($totalBal, 2); ?></div>
                    </div>
                </div>
            </div>
            
            <div class="data-card">
                <div class="data-card-header">
                    <h2>Recent Transactions</h2>
                </div>
                <div style="padding: 20px;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Account</th>
                                <th>Category</th>
                                <th>Description</th>
                                <th>Amount</th>
                                <th>Type</th><th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($transactions)): ?>
                            <tr><td colspan="7" style="text-align: center;">No transactions found.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($transactions as $tx):
                                // Linked transactions (payment receipts / paid expense claims) are managed
                                // from their own pages so records stay in sync.
                                $isPrLinked = (strpos($tx['description'] ?? '', 'Payment Received via PR:') === 0);
                                $isExpenseLinked = ($tx['category'] === 'Employee Expenses');
                                $locked = $isPrLinked || $isExpenseLinked;
                            ?>
                            <tr>
                                <td><?php echo date('d M Y', strtotime($tx['transaction_date'])); ?></td>
                                <td><strong><?php echo sanitize($tx['account_name']); ?></strong></td>
                                <td><?php echo sanitize($tx['category']); ?></td>
                                <td><?php echo sanitize($tx['description']); ?></td>
                                <td style="font-weight: bold; color: <?php echo $tx['type'] == 'DEPOSIT' ? 'var(--primary-green)' : 'var(--danger)'; ?>">
                                    <?php echo $tx['type'] == 'DEPOSIT' ? '+' : '-'; ?> Rs. <?php echo number_format($tx['amount'], 2); ?>
                                </td>
                                <td><span class="badge" style="background: <?php echo $tx['type'] == 'DEPOSIT' ? '#e8f5e9' : '#ffebee'; ?>; color: <?php echo $tx['type'] == 'DEPOSIT' ? 'var(--primary-green)' : 'var(--danger)'; ?>;"><?php echo $tx['type']; ?></span></td>
                                <td>
                                    <div class="action-btns">
                                        <?php if ($locked): ?>
                                            <span class="btn-icon" style="opacity:0.4; cursor:not-allowed;" title="<?php echo $isPrLinked ? 'Manage from Payment Receipts' : 'Manage from Expenses'; ?>"><i class="fas fa-lock"></i></span>
                                        <?php else: ?>
                                            <a href="edit-transaction.php?id=<?php echo $tx['id']; ?>" class="btn-icon edit" title="Edit"><i class="fas fa-edit"></i></a>
                                            <a href="?delete_transaction=<?php echo $tx['id']; ?>" class="btn-icon delete" title="Delete" onclick="return confirm('Delete this transaction? The account balance will be adjusted back.');"><i class="fas fa-trash"></i></a>
                                        <?php endif; ?>
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
