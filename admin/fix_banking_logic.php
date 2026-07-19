<?php
$file = 'F:/wamp64/www/agrovise/admin/banking.php';
$content = file_get_contents($file);

// 1. Add Deletion Logic
$search1 = '$transactions = $transStmt->fetchAll();' . "\n\n" . '$flash = getFlashMessage();';
$replace1 = '$transactions = $transStmt->fetchAll();

// Handle Account Delete
if (isset($_GET['delete_account']) && is_numeric($_GET['delete_account'])) {
    $acc_id = intval($_GET['delete_account']);
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
if (isset($_GET['delete_transaction']) && is_numeric($_GET['delete_transaction'])) {
    $tx_id = intval($_GET['delete_transaction']);
    try {
        $conn->beginTransaction();
        $stmt = $conn->prepare("SELECT bank_id, amount, type FROM transactions WHERE id = ?");
        $stmt->execute([$tx_id]);
        $tx = $stmt->fetch();
        
        if ($tx) {
            // Revert balance
            if ($tx[\'type\'] == \'DEPOSIT\') {
                $conn->prepare("UPDATE banking SET balance = balance - ? WHERE id = ?")->execute([$tx[\'amount\'], $tx[\'bank_id\']]);
            } else {
                $conn->prepare("UPDATE banking SET balance = balance + ? WHERE id = ?")->execute([$tx[\'amount\'], $tx[\'bank_id\']]);
            }
            // Delete record
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

$flash = getFlashMessage();';

$content = str_replace($search1, $replace1, $content);

file_put_contents($file, $content);
echo "Logic Updated\n";
