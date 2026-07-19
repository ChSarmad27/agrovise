<?php
/**
 * AGROVISE - Edit Bank Account
 */
require_once '../includes/db.php';
require_once '../includes/functions.php';
requirePermission('banking');

$conn = getDBConnection();
$id = intval($_GET['id'] ?? 0);
$errors = [];

$stmt = $conn->prepare("SELECT * FROM banking WHERE id = ?");
$stmt->execute([$id]);
$account = $stmt->fetch();

if (!$account) {
    header('Location: banking.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $account_name = trim($_POST['account_name'] ?? '');
    $account_number = trim($_POST['account_number'] ?? '');

    if (empty($account_name)) $errors[] = 'Account Name is required.';

    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("UPDATE banking SET account_name = ?, account_number = ? WHERE id = ?");
            $stmt->execute([$account_name, $account_number, $id]);
            setFlashMessage('success', 'Account updated successfully!');
            header('Location: banking.php');
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
    <title>Edit Account - AGROVISE</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=ed3">
</head>
<body class="admin-body">
    <div class="admin-wrapper">
        <?php require __DIR__ . '/../includes/admin-sidebar.php'; ?>
        
        <main class="admin-main">
            <div class="admin-header"><h1>Edit Bank Account: <?php echo sanitize($account['account_name']); ?></h1></div>
            
            <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul><?php foreach ($errors as $e) echo "<li>$e</li>"; ?></ul>
            </div>
            <?php endif; ?>
            
            <div class="data-card" style="max-width: 600px;">
                <div style="padding: 25px;">
                    <form method="POST">
                        <?php echo csrfField(); ?>
                        <div class="form-group">
                            <label class="form-label">Account Name (e.g. Cash, HBL Office) *</label>
                            <input type="text" name="account_name" class="form-input" required value="<?php echo sanitize($account['account_name']); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Account Number (Optional)</label>
                            <input type="text" name="account_number" class="form-input" value="<?php echo sanitize($account['account_number']); ?>">
                        </div>
                        <p style="color: #666; font-size: 0.9em; margin-bottom: 20px;">
                            <i class="fas fa-info-circle"></i> Opening balance cannot be edited here to maintain audit integrity. Please use transactions to adjust balance.
                        </p>

                        <div style="margin-top: 20px; display: flex; gap: 10px;">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
                            <a href="banking.php" class="btn btn-outline">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
