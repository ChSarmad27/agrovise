<?php
/**
 * AGROVISE - Edit Vendor
 */
require_once '../includes/db.php';
require_once '../includes/functions.php';
requirePermission('vendors');

$conn = getDBConnection();
$errors = [];
$id = intval($_GET['id'] ?? 0);

if (!$id) {
    header('Location: vendors.php');
    exit;
}

$stmt = $conn->prepare("SELECT * FROM vendors WHERE id = ?");
$stmt->execute([$id]);
$vendor = $stmt->fetch();

if (!$vendor) {
    header('Location: vendors.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $contact_person = trim($_POST['contact_person'] ?? '');

    // Auto-insert the dash when the digit count is right (03001234567 -> 0300-1234567)
    $phone = normalizePhonePK($phone) ?? $phone;

    // Validation (Compulsory: Name, Address, Phone)
    if (empty($name)) $errors[] = 'Vendor Name is required.';
    if (empty($address)) $errors[] = 'Address is required.';
    if (empty($phone)) $errors[] = 'Phone is required.';
    
    // Phone Validation XXXX-XXXXXXX
    if (!empty($phone) && !preg_match('/^\d{4}-\d{7}$/', $phone)) {
        $errors[] = 'Phone format must be XXXX-XXXXXXX';
    }
    
    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("UPDATE vendors SET name = ?, address = ?, phone = ?, contact_person = ? WHERE id = ?");
            $stmt->execute([$name, $address, $phone, $contact_person, $id]);
            
            setFlashMessage('success', 'Vendor updated successfully!');
            header('Location: vendors.php');
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Vendor - AGROVISE Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=ed3">
</head>
<body class="admin-body">
    <div class="admin-wrapper">
        <?php require __DIR__ . '/../includes/admin-sidebar.php'; ?>
        
        <main class="admin-main">
            <div class="admin-header">
                <h1><i class="fas fa-edit"></i> Edit Vendor / Supplier</h1>
            </div>
            
            <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul style="margin: 0; padding-left: 20px;">
                    <?php foreach ($errors as $error) echo "<li>$error</li>"; ?>
                </ul>
            </div>
            <?php endif; ?>
            
            <div class="data-card" style="max-width: 800px;">
                <div class="data-card-header">
                    <h2>Update Vendor Details</h2>
                    <a href="vendors.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Back to Vendors</a>
                </div>
                
                <div style="padding: 30px;">
                    <form method="POST" action="">
                        <?php echo csrfField(); ?>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px;">
                            <div class="form-group">
                                <label class="form-label">Full Name / Company Name *</label>
                                <input type="text" name="name" class="form-input" required value="<?php echo sanitize($vendor['name']); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Phone No * (XXXX-XXXXXXX)</label>
                                <input type="text" name="phone" class="form-input" placeholder="0300-1234567" required value="<?php echo sanitize($vendor['phone']); ?>">
                            </div>

                            <div class="form-group" style="grid-column: 1 / -1;">
                                <label class="form-label">Company Address *</label>
                                <input type="text" name="address" class="form-input" required value="<?php echo sanitize($vendor['address']); ?>">
                            </div>

                            <div class="form-group">
                                <label class="form-label">Contact Person (Optional)</label>
                                <input type="text" name="contact_person" class="form-input" value="<?php echo sanitize($vendor['contact_person']); ?>">
                            </div>
                        </div>
                        
                        <div style="margin-top: 30px;">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Vendor</button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
    <script src="../assets/js/input-formats.js"></script>
</body>
</html>
