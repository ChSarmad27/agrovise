<?php
/**
 * AGROVISE - Add Vehicle
 */
require_once '../includes/db.php';
require_once '../includes/functions.php';
requirePermission('vehicles');

$conn = getDBConnection();
$errors = [];
$employees = $conn->query("SELECT id, name, role FROM employees ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $plate = strtoupper(trim($_POST['plate_number'] ?? ''));
    $make = trim($_POST['make'] ?? '');
    $model = trim($_POST['model'] ?? '');
    $color = trim($_POST['color'] ?? '');
    $year = intval($_POST['year_of_issue'] ?? 0);
    $financing = ($_POST['financing'] ?? 'CASH') === 'LOAN' ? 'LOAN' : 'CASH';
    $installment_amount = $financing === 'LOAN' ? floatval($_POST['installment_amount'] ?? 0) : null;
    $installments_remaining = $financing === 'LOAN' ? intval($_POST['installments_remaining'] ?? 0) : null;
    $tenure_months = $financing === 'LOAN' ? intval($_POST['tenure_months'] ?? 0) : null;
    $assigned = !empty($_POST['assigned_employee_id']) ? intval($_POST['assigned_employee_id']) : null;

    if (empty($plate)) $errors[] = 'Plate number is required.';
    if (empty($make)) $errors[] = 'Make is required.';
    if (empty($model)) $errors[] = 'Model is required.';
    if ($year && ($year < 1980 || $year > intval(date('Y')) + 1)) $errors[] = 'Year of issue looks invalid.';
    if ($financing === 'LOAN') {
        if ($installments_remaining <= 0) $errors[] = 'Installments remaining is required for a bank-loan vehicle.';
        if ($tenure_months <= 0) $errors[] = 'Loan tenure (months) is required for a bank-loan vehicle.';
        if ($installments_remaining > $tenure_months) $errors[] = 'Installments remaining cannot exceed the loan tenure.';
    }

    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("INSERT INTO vehicles (plate_number, make, model, color, year_of_issue, financing, installment_amount, installments_remaining, tenure_months, assigned_employee_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$plate, $make, $model, $color, $year ?: null, $financing, $installment_amount, $installments_remaining, $tenure_months, $assigned]);
            setFlashMessage('success', 'Vehicle added to the fleet!');
            header('Location: vehicles.php');
            exit;
        } catch (PDOException $e) {
            $errors[] = $e->getCode() == 23000 ? 'That plate number is already registered.' : dbError($e);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Vehicle - AGROVISE</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=ed3">
</head>
<body class="admin-body">
    <div class="admin-wrapper">
        <?php require __DIR__ . '/../includes/admin-sidebar.php'; ?>

        <main class="admin-main">
            <div class="admin-header"><h1>Add Vehicle</h1></div>

            <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul style="margin:0; padding-left:20px;"><?php foreach ($errors as $e) echo "<li>" . sanitize($e) . "</li>"; ?></ul>
            </div>
            <?php endif; ?>

            <div class="data-card" style="max-width: 640px;">
                <div style="padding: 25px;">
                    <form method="POST">
                        <?php echo csrfField(); ?>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                            <div class="form-group">
                                <label class="form-label">Plate Number *</label>
                                <input type="text" name="plate_number" class="form-input" required placeholder="e.g. LEB-1234" value="<?php echo sanitize($_POST['plate_number'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Year of Issue</label>
                                <input type="number" name="year_of_issue" class="form-input" min="1980" max="<?php echo date('Y') + 1; ?>" placeholder="<?php echo date('Y'); ?>" value="<?php echo sanitize($_POST['year_of_issue'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Make *</label>
                                <input type="text" name="make" class="form-input" required placeholder="e.g. Toyota" value="<?php echo sanitize($_POST['make'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Model *</label>
                                <input type="text" name="model" class="form-input" required placeholder="e.g. Corolla GLi" value="<?php echo sanitize($_POST['model'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Color</label>
                                <input type="text" name="color" class="form-input" placeholder="e.g. White" value="<?php echo sanitize($_POST['color'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Issued To (Employee)</label>
                                <select name="assigned_employee_id" class="form-select">
                                    <option value="">-- Unassigned --</option>
                                    <?php foreach ($employees as $emp): ?>
                                    <option value="<?php echo $emp['id']; ?>" <?php echo (($_POST['assigned_employee_id'] ?? '') == $emp['id']) ? 'selected' : ''; ?>><?php echo sanitize($emp['name'] . ' — ' . $emp['role']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-group" style="margin-top:8px;">
                            <label class="form-label">Financing *</label>
                            <select name="financing" id="financing" class="form-select" onchange="toggleLoan()">
                                <option value="CASH" <?php echo (($_POST['financing'] ?? 'CASH') === 'CASH') ? 'selected' : ''; ?>>Bought fully in cash</option>
                                <option value="LOAN" <?php echo (($_POST['financing'] ?? '') === 'LOAN') ? 'selected' : ''; ?>>Bank loan</option>
                            </select>
                        </div>

                        <div id="loanFields" style="display:none; background:#f7f9f6; border:1px solid #e0e6dd; border-radius:10px; padding:16px; margin-bottom:16px;">
                            <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:14px;">
                                <div class="form-group" style="margin:0;">
                                    <label class="form-label">Loan Tenure (months) *</label>
                                    <input type="number" name="tenure_months" class="form-input" min="1" value="<?php echo sanitize($_POST['tenure_months'] ?? ''); ?>">
                                </div>
                                <div class="form-group" style="margin:0;">
                                    <label class="form-label">Installments Remaining *</label>
                                    <input type="number" name="installments_remaining" class="form-input" min="0" value="<?php echo sanitize($_POST['installments_remaining'] ?? ''); ?>">
                                </div>
                                <div class="form-group" style="margin:0;">
                                    <label class="form-label">Installment (Rs/month)</label>
                                    <input type="number" name="installment_amount" class="form-input" step="0.01" min="0" value="<?php echo sanitize($_POST['installment_amount'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Vehicle</button>
                        <a href="vehicles.php" class="btn btn-outline">Cancel</a>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script>
    function toggleLoan() {
        document.getElementById('loanFields').style.display =
            document.getElementById('financing').value === 'LOAN' ? 'block' : 'none';
    }
    toggleLoan();
    </script>
</body>
</html>
