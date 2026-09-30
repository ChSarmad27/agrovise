<?php
/**
 * AGROVISE - Edit Packing Operation (Restricted)
 */
require_once '../includes/db.php';
require_once '../includes/functions.php';
requirePermission('packing');

$conn = getDBConnection();
$id = intval($_GET['id'] ?? 0);
$errors = [];

$stmt = $conn->prepare("SELECT * FROM packing_operations WHERE id = ?");
$stmt->execute([$id]);
$op = $stmt->fetch();

if (!$op) {
    header('Location: packing.php');
    exit;
}

$allProducts = $conn->query("SELECT id, name, packing_type FROM products ORDER BY name ASC")->fetchAll();

// The finished lot this operation produced (for its expiry date)
$finStmt = $conn->prepare("SELECT id, expiry_date FROM purchasing WHERE id = ?");
$finStmt->execute([$op['finished_purchase_id']]);
$finishedLot = $finStmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selling_price = floatval($_POST['selling_price'] ?? 0);
    $finished_expiry = trim($_POST['finished_expiry'] ?? '');
    $finished_expiry = $finished_expiry !== '' ? $finished_expiry : null;

    if ($finished_expiry !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $finished_expiry)) {
        $errors[] = 'Invalid expiry date.';
    }

    // Changing quantities or batches is locked in this simple edit to prevent stock chaos
    // A full edit would require reversing the entire operation and re-executing.
    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("UPDATE packing_operations SET selling_price = ? WHERE id = ?");
            $stmt->execute([$selling_price, $id]);
            // Expiry lives on the finished lot itself (metadata — safe to edit)
            if ($finishedLot) {
                $conn->prepare("UPDATE purchasing SET expiry_date = ? WHERE id = ?")
                     ->execute([$finished_expiry, $finishedLot['id']]);
            }
            setFlashMessage('success', 'Packing operation metadata updated (price / expiry).');
            header('Location: packing.php');
            exit;
        } catch (PDOException $e) {
            $errors[] = dbError($e);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Packing - AGROVISE</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=ed3">
</head>
<body class="admin-body">
    <div class="admin-wrapper">
        <?php require __DIR__ . '/../includes/admin-sidebar.php'; ?>
        
        <main class="admin-main">
            <div class="admin-header"><h1>Edit Packing Metadata</h1></div>
            
            <div class="alert alert-info">
                <i class="fas fa-lock"></i> Batch numbers and quantities are locked to maintain stock integrity. To change those, please <strong>Delete</strong> this operation and create a new one.
            </div>

            <div class="data-card" style="max-width: 600px;">
                <div style="padding: 25px;">
                    <form method="POST">
                        <?php echo csrfField(); ?>
                        <div class="form-group">
                            <label class="form-label">Planned Selling Price (Manual) Rs.</label>
                            <input type="number" step="0.01" name="selling_price" class="form-input" value="<?php echo $op['selling_price']; ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Finished Lot Expiry Date</label>
                            <input type="date" name="finished_expiry" class="form-input" value="<?php echo sanitize($finishedLot['expiry_date'] ?? ''); ?>">
                            <small style="color:#888;">Shown on Finished Stock and on invoices selling this lot.</small>
                        </div>

                        <div style="margin-top: 20px; display: flex; gap: 10px;">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update</button>
                            <a href="packing.php" class="btn btn-outline">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
