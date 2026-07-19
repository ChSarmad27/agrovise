<?php
/**
 * AGROVISE - Edit Vehicle (details, assignment, loan status, reading history)
 */
require_once '../includes/db.php';
require_once '../includes/functions.php';
requirePermission('vehicles');

$conn = getDBConnection();
$id = intval($_GET['id'] ?? 0);
$errors = [];

$stmt = $conn->prepare("SELECT * FROM vehicles WHERE id = ?");
$stmt->execute([$id]);
$vehicle = $stmt->fetch();

if (!$vehicle) {
    setFlashMessage('error', 'Vehicle not found.');
    header('Location: vehicles.php');
    exit;
}

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
    if ($financing === 'LOAN') {
        if ($tenure_months <= 0) $errors[] = 'Loan tenure (months) is required for a bank-loan vehicle.';
        if ($installments_remaining < 0 || $installments_remaining > $tenure_months) $errors[] = 'Installments remaining must be between 0 and the tenure.';
    }

    if (empty($errors)) {
        try {
            $upd = $conn->prepare("UPDATE vehicles SET plate_number = ?, make = ?, model = ?, color = ?, year_of_issue = ?, financing = ?, installment_amount = ?, installments_remaining = ?, tenure_months = ?, assigned_employee_id = ? WHERE id = ?");
            $upd->execute([$plate, $make, $model, $color, $year ?: null, $financing, $installment_amount, $installments_remaining, $tenure_months, $assigned, $id]);
            setFlashMessage('success', 'Vehicle updated.');
            header('Location: vehicles.php');
            exit;
        } catch (PDOException $e) {
            $errors[] = $e->getCode() == 23000 ? 'That plate number is already registered.' : dbError($e);
        }
    }
    // repopulate on error
    $vehicle = array_merge($vehicle, [
        'plate_number' => $plate, 'make' => $make, 'model' => $model, 'color' => $color,
        'year_of_issue' => $year, 'financing' => $financing, 'installment_amount' => $installment_amount,
        'installments_remaining' => $installments_remaining, 'tenure_months' => $tenure_months,
        'assigned_employee_id' => $assigned,
    ]);
}

$readings = $conn->prepare("
    SELECT vr.*, e.name as employee_name
    FROM vehicle_readings vr LEFT JOIN employees e ON vr.employee_id = e.id
    WHERE vr.vehicle_id = ? ORDER BY vr.reading_month DESC
");
$readings->execute([$id]);
$readings = $readings->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Vehicle - AGROVISE</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=ed3">
</head>
<body class="admin-body">
    <div class="admin-wrapper">
        <?php require __DIR__ . '/../includes/admin-sidebar.php'; ?>

        <main class="admin-main">
            <div class="admin-header"><h1>Edit Vehicle: <?php echo sanitize($vehicle['plate_number']); ?></h1></div>

            <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul style="margin:0; padding-left:20px;"><?php foreach ($errors as $e) echo "<li>" . sanitize($e) . "</li>"; ?></ul>
            </div>
            <?php endif; ?>

            <div class="data-card" style="max-width: 640px; margin-bottom: 20px;">
                <div style="padding: 25px;">
                    <form method="POST">
                        <?php echo csrfField(); ?>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                            <div class="form-group">
                                <label class="form-label">Plate Number *</label>
                                <input type="text" name="plate_number" class="form-input" required value="<?php echo sanitize($vehicle['plate_number']); ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Year of Issue</label>
                                <input type="number" name="year_of_issue" class="form-input" min="1980" max="<?php echo date('Y') + 1; ?>" value="<?php echo sanitize($vehicle['year_of_issue'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Make *</label>
                                <input type="text" name="make" class="form-input" required value="<?php echo sanitize($vehicle['make']); ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Model *</label>
                                <input type="text" name="model" class="form-input" required value="<?php echo sanitize($vehicle['model']); ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Color</label>
                                <input type="text" name="color" class="form-input" value="<?php echo sanitize($vehicle['color'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Issued To (Employee)</label>
                                <select name="assigned_employee_id" class="form-select">
                                    <option value="">-- Unassigned --</option>
                                    <?php foreach ($employees as $emp): ?>
                                    <option value="<?php echo $emp['id']; ?>" <?php echo $vehicle['assigned_employee_id'] == $emp['id'] ? 'selected' : ''; ?>><?php echo sanitize($emp['name'] . ' — ' . $emp['role']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-group" style="margin-top:8px;">
                            <label class="form-label">Financing *</label>
                            <select name="financing" id="financing" class="form-select" onchange="toggleLoan()">
                                <option value="CASH" <?php echo $vehicle['financing'] === 'CASH' ? 'selected' : ''; ?>>Bought fully in cash</option>
                                <option value="LOAN" <?php echo $vehicle['financing'] === 'LOAN' ? 'selected' : ''; ?>>Bank loan</option>
                            </select>
                        </div>

                        <div id="loanFields" style="display:none; background:#f7f9f6; border:1px solid #e0e6dd; border-radius:10px; padding:16px; margin-bottom:16px;">
                            <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:14px;">
                                <div class="form-group" style="margin:0;">
                                    <label class="form-label">Loan Tenure (months) *</label>
                                    <input type="number" name="tenure_months" class="form-input" min="1" value="<?php echo sanitize($vehicle['tenure_months'] ?? ''); ?>">
                                </div>
                                <div class="form-group" style="margin:0;">
                                    <label class="form-label">Installments Remaining *</label>
                                    <input type="number" name="installments_remaining" class="form-input" min="0" value="<?php echo sanitize($vehicle['installments_remaining'] ?? ''); ?>">
                                </div>
                                <div class="form-group" style="margin:0;">
                                    <label class="form-label">Installment (Rs/month)</label>
                                    <input type="number" name="installment_amount" class="form-input" step="0.01" min="0" value="<?php echo sanitize($vehicle['installment_amount'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
                        <a href="vehicles.php" class="btn btn-outline">Cancel</a>
                    </form>
                </div>
            </div>

            <!-- Monthly meter reading history -->
            <div class="data-card">
                <div class="data-card-header"><h2><i class="fas fa-gauge-high"></i> Meter Reading History</h2></div>
                <div style="padding: 20px;">
                    <table class="data-table">
                        <thead>
                            <tr><th>Month</th><th>Reading (km)</th><th>Distance Since Prev.</th><th>Submitted By</th><th>Meter Photo</th><th>Logged On</th></tr>
                        </thead>
                        <tbody>
                            <?php if (empty($readings)): ?>
                            <tr><td colspan="6" style="text-align:center;">No readings submitted yet. The assigned employee uploads these monthly from My Profile.</td></tr>
                            <?php endif; ?>
                            <?php
                            $count = count($readings);
                            foreach ($readings as $i => $r):
                                $prev = $readings[$i + 1]['reading_km'] ?? null; // list is DESC
                                $delta = $prev !== null ? $r['reading_km'] - $prev : null;
                            ?>
                            <tr>
                                <td><strong><?php echo date('F Y', strtotime($r['reading_month'] . '-01')); ?></strong></td>
                                <td><?php echo number_format($r['reading_km'], 1); ?> km</td>
                                <td><?php echo $delta !== null ? '+' . number_format($delta, 1) . ' km' : '—'; ?></td>
                                <td><?php echo sanitize($r['employee_name'] ?? '—'); ?></td>
                                <td><?php echo $r['meter_image'] ? '<a href="../uploads/meters/' . sanitize($r['meter_image']) . '" target="_blank" class="btn btn-outline btn-sm"><i class="fas fa-image"></i> View</a>' : '—'; ?></td>
                                <td><?php echo date('d M Y', strtotime($r['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
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
