<?php
$file = 'F:/wamp64/www/agrovise/admin/banking.php';
$content = file_get_contents($file);

// 1. Add Deletion Logic
$logic = '
// Handle Account Delete
if (isset($_GET[\'delete_account\']) && is_numeric($_GET[\'delete_account\'])) {
    $acc_id = intval($_GET[\'delete_account\']);
    try {
        $conn->prepare("DELETE FROM banking WHERE id = ?")->execute([$acc_id]);
        setFlashMessage(\'success\', \'Bank account deleted successfully!\');
    } catch(PDOException $e) {
        setFlashMessage(\'error\', \'Cannot delete account (it has transaction history).\');
    }
    header(\'Location: banking.php\');
    exit;
}

// Handle Transaction Delete
if (isset($_GET[\'delete_transaction\']) && is_numeric($_GET[\'delete_transaction\'])) {
    $tx_id = intval($_GET[\'delete_transaction\']);
    try {
        $conn->beginTransaction();
        $stmt = $conn->prepare("SELECT bank_id, amount, type FROM transactions WHERE id = ?");
        $stmt->execute([$tx_id]);
        $tx = $stmt->fetch();
        
        if ($tx) {
            if ($tx[\'type\'] == \'DEPOSIT\') {
                $conn->prepare("UPDATE banking SET balance = balance - ? WHERE id = ?")->execute([$tx[\'amount\'], $tx[\'bank_id\']]);
            } else {
                $conn->prepare("UPDATE banking SET balance = balance + ? WHERE id = ?")->execute([$tx[\'amount\'], $tx[\'bank_id\']]);
            }
            $conn->prepare("DELETE FROM transactions WHERE id = ?")->execute([$tx_id]);
        }
        $conn->commit();
        setFlashMessage(\'success\', \'Transaction deleted and balance adjusted!\');
    } catch(PDOException $e) {
        $conn->rollBack();
        setFlashMessage(\'error\', \'Error: \' . $e->getMessage());
    }
    header(\'Location: banking.php\');
    exit;
}
';

if (strpos($content, 'delete_account') === false) {
    if (preg_match('/\$transactions = \$transStmt->fetchAll\(\);/', $content, $matches)) {
        $content = str_replace($matches[0], $matches[0] . $logic, $content);
    }
}

// 2. Add Account Buttons
$accButtons = '
                        <div style="margin-top: 10px; display: flex; gap: 10px;">
                            <a href="edit-account.php?id=<?php echo $acc[\'id\']; ?>" class="btn-icon edit" style="font-size: 0.8em; color: white;"><i class="fas fa-edit"></i></a>
                            <a href="?delete_account=<?php echo $acc[\'id\']; ?>" class="btn-icon delete" style="font-size: 0.8em; color: rgba(255,255,255,0.7);" onclick="return confirm(\'Delete this account? History will be lost.\');"><i class="fas fa-trash"></i></a>
                        </div>';
if (strpos($content, 'edit-account.php') === false) {
    $content = str_replace('<div class="stat-desc"><?php echo sanitize($acc[\'account_number\']); ?></div>', '<div class="stat-desc"><?php echo sanitize($acc[\'account_number\']); ?></div>' . $accButtons, $content);
}

// 3. Add Transaction Column
if (strpos($content, '<th>Actions</th>') === false) {
    $content = str_replace('<th>Type</th>', '<th>Type</th><th>Actions</th>', $content);
}

// 4. Add Transaction Delete Button
$txDelete = '
                                <td>
                                    <div class="action-btns">
                                        <a href="?delete_transaction=<?php echo $tx[\'id\']; ?>" class="btn-icon delete" onclick="return confirm(\'Delete this transaction and revert balance?\');"><i class="fas fa-trash"></i></a>
                                    </div>
                                </td>';
if (strpos($content, 'delete_transaction') === false) {
    $content = str_replace('<td><span class="badge" style="background: <?php echo $tx[\'type\'] == \'DEPOSIT\' ? \'#e8f5e9\' : \'#ffebee\'; ?>; color: <?php echo $tx[\'type\'] == \'DEPOSIT\' ? \'var(--primary-green)\' : \'var(--danger)\'; ?>;"><?php echo $tx[\'type\']; ?></span></td>', '<td><span class="badge" style="background: <?php echo $tx[\'type\'] == \'DEPOSIT\' ? \'#e8f5e9\' : \'#ffebee\'; ?>; color: <?php echo $tx[\'type\'] == \'DEPOSIT\' ? \'var(--primary-green)\' : \'var(--danger)\'; ?>;"><?php echo $tx[\'type\']; ?></span></td>' . $txDelete, $content);
}

file_put_contents($file, $content);
echo "Banking Updated\n";
