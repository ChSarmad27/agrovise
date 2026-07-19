<?php
/**
 * AGROVISE - Company Vehicles / Fleet
 */
require_once '../includes/db.php';
require_once '../includes/functions.php';
requirePermission('vehicles');

$conn = getDBConnection();

// Handle Delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = intval($_GET['delete']);
    try {
        $conn->prepare("DELETE FROM vehicle_readings WHERE vehicle_id = ?")->execute([$id]);
        $conn->prepare("DELETE FROM vehicles WHERE id = ?")->execute([$id]);
        setFlashMessage('success', 'Vehicle removed from the fleet.');
    } catch (PDOException $e) {
        setFlashMessage('error', dbError($e));
    }
    header('Location: vehicles.php');
    exit;
}

$vehicles = $conn->query("
    SELECT v.*, e.name as employee_name,
           (SELECT reading_km FROM vehicle_readings vr WHERE vr.vehicle_id = v.id ORDER BY vr.reading_month DESC LIMIT 1) as last_km,
           (SELECT reading_month FROM vehicle_readings vr WHERE vr.vehicle_id = v.id ORDER BY vr.reading_month DESC LIMIT 1) as last_km_month
    FROM vehicles v
    LEFT JOIN employees e ON v.assigned_employee_id = e.id
    ORDER BY v.created_at DESC
")->fetchAll();

$flash = getFlashMessage();
$thisMonth = date('Y-m');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Vehicles - AGROVISE</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=ed3">
</head>
<body class="admin-body">
    <div class="admin-wrapper">
        <?php require __DIR__ . '/../includes/admin-sidebar.php'; ?>

        <main class="admin-main">
            <div class="admin-header"><h1><i class="fas fa-car"></i> Company Vehicles</h1></div>

            <?php if ($flash): ?>
            <div class="alert alert-<?php echo $flash['type']; ?>"><?php echo $flash['message']; ?></div>
            <?php endif; ?>

            <div class="data-card">
                <div class="data-card-header">
                    <h2>Fleet List</h2>
                    <a href="add-vehicle.php" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Add Vehicle</a>
                </div>
                <div style="padding: 20px;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Plate No</th>
                                <th>Vehicle</th>
                                <th>Year</th>
                                <th>Financing</th>
                                <th>Issued To</th>
                                <th>Last Meter Reading</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($vehicles)): ?>
                            <tr><td colspan="7" style="text-align:center;">No vehicles logged yet.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($vehicles as $v): ?>
                            <tr>
                                <td><strong><?php echo sanitize($v['plate_number']); ?></strong></td>
                                <td><?php echo sanitize($v['make'] . ' ' . $v['model']); ?><br><small style="color:#888;"><?php echo sanitize($v['color'] ?? ''); ?></small></td>
                                <td><?php echo sanitize($v['year_of_issue'] ?? '—'); ?></td>
                                <td>
                                    <?php if ($v['financing'] === 'LOAN'): ?>
                                        <span class="badge" style="background:#FFF3E0; color:#E65100;">Bank Loan</span><br>
                                        <small><?php echo intval($v['installments_remaining']); ?> installments left / <?php echo intval($v['tenure_months']); ?> mo tenure</small>
                                        <?php if ($v['installment_amount'] > 0): ?><br><small>Rs. <?php echo number_format($v['installment_amount'], 0); ?>/mo</small><?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge" style="background:#e8f5e9; color:#2e7d32;">Cash / Owned</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $v['employee_name'] ? sanitize($v['employee_name']) : '<span style="color:#999;">Unassigned</span>'; ?></td>
                                <td>
                                    <?php if ($v['last_km'] !== null): ?>
                                        <strong><?php echo number_format($v['last_km'], 1); ?> km</strong>
                                        <br><small style="color:<?php echo $v['last_km_month'] === $thisMonth ? '#2e7d32' : '#E65100'; ?>;">
                                            <?php echo date('M Y', strtotime($v['last_km_month'] . '-01')); ?>
                                            <?php echo $v['last_km_month'] === $thisMonth ? '✓' : '(overdue)'; ?>
                                        </small>
                                    <?php elseif ($v['assigned_employee_id']): ?>
                                        <span style="color:#E65100;">No reading yet</span>
                                    <?php else: ?>
                                        <span style="color:#999;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-btns">
                                        <a href="edit-vehicle.php?id=<?php echo $v['id']; ?>" class="btn-icon edit" title="Edit / Readings"><i class="fas fa-edit"></i></a>
                                        <a href="?delete=<?php echo $v['id']; ?>" class="btn-icon delete" title="Delete" onclick="return confirm('Delete this vehicle and its reading history?');"><i class="fas fa-trash"></i></a>
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
