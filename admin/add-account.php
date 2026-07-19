<?php
/**
 * AGROVISE - Add Account
 */
require_once '../includes/db.php';
require_once '../includes/functions.php';
requirePermission('banking');

$conn = getDBConnection();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['account_name'] ?? '');
    $number = trim($_POST['account_number'] ?? '');
    $balance = floatval($_POST['balance'] ?? 0);
    
    if(empty($name)) $errors[] = "Account name is required.";
    
    if(empty($errors)) {
        try {
            $stmt = $conn->prepare("INSERT INTO banking (account_name, account_number, balance) VALUES (?, ?, ?)");
            $stmt->execute([$name, $number, $balance]);
            setFlashMessage('success', 'Bank account added successfully!');
            header('Location: banking.php');
            exit;
        } catch(PDOException $e) {
            $errors[] = dbError($e);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Account - AGROVISE</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=ed3">
</head>
<body class="admin-body">
    <div class="admin-wrapper">
        <?php require __DIR__ . '/../includes/admin-sidebar.php'; ?>
        <main class="admin-main">
            <div class="admin-header"><h1>Add Bank / Cash Account</h1></div>
            <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul style="margin: 0; padding-left: 20px;">
                    <?php foreach ($errors as $error) echo "<li>$error</li>"; ?>
                </ul>
            </div>
            <?php endif; ?>
            <div class="data-card" style="max-width: 500px;">
                <div style="padding: 20px;">
                    <form method="POST">
                        <?php echo csrfField(); ?>
                        <div class="form-group">
                            <label class="form-label">Account Name *</label>
                            <input type="text" name="account_name" class="form-input" required placeholder="e.g. Meezan Bank, Office Cash, etc." value="<?php echo sanitize($_POST['account_name'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Account Number (Optional)</label>
                            <input type="text" name="account_number" class="form-input" value="<?php echo sanitize($_POST['account_number'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Initial Balance (Rs)</label>
                            <input type="number" name="balance" class="form-input" step="0.01" value="<?php echo sanitize($_POST['balance'] ?? ''); ?>">
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Account</button>
                    </form>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
