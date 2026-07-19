<?php
/**
 * AGROVISE - Edit Client
 */

require_once '../includes/db.php';
require_once '../includes/functions.php';
requirePermission('clients');

$conn = getDBConnection();

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$client = $conn->prepare("SELECT * FROM clients WHERE id = ?");
$client->execute([$id]);
$client = $client->fetch();

if (!$client) {
    setFlashMessage('error', 'Client not found.');
    header('Location: clients.php');
    exit;
}

$employeesList = $conn->query("SELECT id, name, role FROM employees ORDER BY name ASC")->fetchAll();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $cnic = trim($_POST['cnic'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $govt_reg_no = trim($_POST['govt_reg_no'] ?? '');
    $area = trim($_POST['area'] ?? '');
    $employee_id = !empty($_POST['employee_id']) ? intval($_POST['employee_id']) : null;

    // Auto-insert dashes when the digit count is right (03001234567 -> 0300-1234567)
    $phone = normalizePhonePK($phone) ?? $phone;
    $cnic  = normalizeCNIC($cnic) ?? $cnic;

    // Validation
    if (empty($name)) $errors[] = 'Client Name is required.';
    if (empty($address)) $errors[] = 'Address is required.';
    if (empty($area)) $errors[] = 'Area is required.';

    // CNIC Validation XXXXX-XXXXXXX-X
    if (!preg_match('/^\d{5}-\d{7}-\d{1}$/', $cnic)) {
        $errors[] = 'CNIC format must be XXXXX-XXXXXXX-X';
    }

    // Phone Validation XXXX-XXXXXXX
    if (!preg_match('/^\d{4}-\d{7}$/', $phone)) {
        $errors[] = 'Phone format must be XXXX-XXXXXXX';
    }

    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("UPDATE clients SET name = ?, address = ?, cnic = ?, phone = ?, govt_reg_no = ?, area = ?, employee_id = ? WHERE id = ?");
            $stmt->execute([$name, $address, $cnic, $phone, $govt_reg_no, $area, $employee_id, $id]);
            
            setFlashMessage('success', 'Client updated successfully!');
            header('Location: clients.php');
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
    <title>Edit Client - AGROVISE Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=ed3">
</head>
<body class="admin-body">
    <div class="admin-wrapper">
        <?php require __DIR__ . '/../includes/admin-sidebar.php'; ?>
        
        <main class="admin-main">
            <div class="admin-header">
                <h1><i class="fas fa-edit"></i> Edit Client</h1>
                <div class="admin-user">
                    <span>Welcome, <?php echo sanitize($_SESSION['admin_username']); ?></span>
                    <i class="fas fa-user-circle"></i>
                </div>
            </div>
            
            <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul style="margin: 0; padding-left: 20px;">
                    <?php foreach ($errors as $error) echo "<li>$error</li>"; ?>
                </ul>
            </div>
            <?php endif; ?>
            
            <div class="data-card">
                <div class="data-card-header">
                    <h2>Edit: <?php echo sanitize($client['name']); ?></h2>
                    <a href="clients.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Back to Clients</a>
                </div>
                
                <div style="padding: 30px;">
                    <form method="POST" action="">
                        <?php echo csrfField(); ?>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px;">
                            <div class="form-group">
                                <label class="form-label">Name *</label>
                                <input type="text" name="name" class="form-input" required value="<?php echo sanitize($_POST['name'] ?? $client['name']); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Area *</label>
                                <input type="text" name="area" class="form-input" required value="<?php echo sanitize($_POST['area'] ?? $client['area']); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">CNIC *</label>
                                <input type="text" name="cnic" class="form-input" placeholder="XXXXX-XXXXXXX-X" required pattern="\d{5}-\d{7}-\d{1}" value="<?php echo sanitize($_POST['cnic'] ?? $client['cnic']); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Phone *</label>
                                <input type="text" name="phone" class="form-input" placeholder="XXXX-XXXXXXX" required pattern="\d{4}-\d{7}" value="<?php echo sanitize($_POST['phone'] ?? $client['phone']); ?>">
                            </div>
                            
                            <div class="form-group" style="grid-column: 1 / -1;">
                                <label class="form-label">Address *</label>
                                <input type="text" name="address" class="form-input" required value="<?php echo sanitize($_POST['address'] ?? $client['address']); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Govt Registration No (Optional)</label>
                                <input type="text" name="govt_reg_no" class="form-input" value="<?php echo sanitize($_POST['govt_reg_no'] ?? $client['govt_reg_no']); ?>">
                            </div>

                            <div class="form-group">
                                <label class="form-label">Sales Officer (dealing with this client)</label>
                                <?php $selEmp = $_POST['employee_id'] ?? $client['employee_id']; ?>
                                <select name="employee_id" class="form-select">
                                    <option value="">-- None --</option>
                                    <?php foreach($employeesList as $e): ?>
                                    <option value="<?php echo $e['id']; ?>" <?php echo ($selEmp == $e['id']) ? 'selected' : ''; ?>><?php echo sanitize($e['name'] . ' — ' . $e['role']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div style="margin-top: 30px;">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Client</button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
    <script src="../assets/js/input-formats.js"></script>
</body>
</html>
