<?php
/**
 * AGROVISE - My Profile
 * The logged-in user's own space: account & employee details, sales
 * performance, salary payments, expense summary, and issued vehicle(s)
 * with the monthly odometer-reading upload.
 */
require_once '../includes/db.php';
require_once '../includes/functions.php';
requireAdminLogin();

$conn = getDBConnection();
$errors = [];

$account = $conn->prepare("SELECT * FROM admins WHERE id = ?");
$account->execute([$_SESSION['admin_id']]);
$account = $account->fetch();

$employee = null;
if (!empty($account['employee_id'])) {
    $st = $conn->prepare("SELECT * FROM employees WHERE id = ?");
    $st->execute([$account['employee_id']]);
    $employee = $st->fetch();
}

$thisMonth = date('Y-m');

// ---- Submit monthly meter reading for an issued vehicle ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_reading']) && $employee) {
    $vehicleId = intval($_POST['vehicle_id'] ?? 0);
    $km = floatval($_POST['reading_km'] ?? 0);

    // vehicle must actually be issued to this employee
    $vst = $conn->prepare("SELECT * FROM vehicles WHERE id = ? AND assigned_employee_id = ?");
    $vst->execute([$vehicleId, $employee['id']]);
    $vehicle = $vst->fetch();

    if (!$vehicle) {
        $errors[] = 'That vehicle is not issued to you.';
    } elseif ($km <= 0) {
        $errors[] = 'Enter the meter reading in kilometers.';
    } else {
        // odometer must not go backwards
        $prev = $conn->prepare("SELECT MAX(reading_km) FROM vehicle_readings WHERE vehicle_id = ?");
        $prev->execute([$vehicleId]);
        $prevKm = floatval($prev->fetchColumn());
        if ($km < $prevKm) {
            $errors[] = 'Reading (' . number_format($km, 1) . ' km) is lower than the last logged reading (' . number_format($prevKm, 1) . ' km).';
        }
    }

    $meterImage = null;
    if (empty($errors) && isset($_FILES['meter_image']) && $_FILES['meter_image']['error'] === UPLOAD_ERR_OK) {
        $meterImage = uploadMeterImage($_FILES['meter_image']);
        if (!$meterImage) $errors[] = 'Meter photo must be JPG, PNG, GIF or WebP (max 5MB).';
    }

    if (empty($errors)) {
        try {
            $ins = $conn->prepare("INSERT INTO vehicle_readings (vehicle_id, employee_id, reading_month, reading_km, meter_image) VALUES (?, ?, ?, ?, ?)");
            $ins->execute([$vehicleId, $employee['id'], $thisMonth, $km, $meterImage]);
            setFlashMessage('success', 'Meter reading for ' . date('F Y') . ' submitted — thank you!');
            header('Location: my-profile.php');
            exit;
        } catch (PDOException $e) {
            $errors[] = $e->getCode() == 23000
                ? 'A reading for this vehicle has already been submitted this month.'
                : dbError($e);
        }
    }
}

// ---- Profile data ----
$salesCount = 0; $salesTotal = 0.0; $salaryPaid = 0.0;
$monthlySales = []; $duePayments = 0.0; $thisMonthSales = 0.0;
$claimStats = ['WAITING' => 0, 'APPROVED' => 0, 'DECLINED' => 0, 'PAID' => 0, 'paid_total' => 0.0];
$myVehicles = [];
$myTarget = null; $yearSales = 0.0; $thisYear = intval(date('Y'));

if ($employee) {
    $row = $conn->prepare("SELECT COUNT(*) c, IFNULL(SUM(total_amount),0) t FROM invoices WHERE employee_id = ?");
    $row->execute([$employee['id']]);
    $row = $row->fetch();
    $salesCount = intval($row['c']);
    $salesTotal = floatval($row['t']);

    // Monthly sales (last 6 months present in data) for the officer's chart
    $ms = $conn->prepare("
        SELECT DATE_FORMAT(date, '%b %Y') as month, DATE_FORMAT(date, '%Y-%m') as ym, SUM(total_amount) as sales
        FROM invoices WHERE employee_id = ?
        GROUP BY ym, month ORDER BY ym DESC LIMIT 6
    ");
    $ms->execute([$employee['id']]);
    $monthlySales = array_reverse($ms->fetchAll());

    // This month's sales
    $tm = $conn->prepare("SELECT IFNULL(SUM(total_amount),0) FROM invoices WHERE employee_id = ? AND DATE_FORMAT(date, '%Y-%m') = ?");
    $tm->execute([$employee['id'], $thisMonth]);
    $thisMonthSales = floatval($tm->fetchColumn());

    // Due payments from this officer's sales = invoices (their name) - payments received through them
    $inv = $conn->prepare("SELECT IFNULL(SUM(total_amount),0) FROM invoices WHERE employee_id = ?");
    $inv->execute([$employee['id']]);
    $rec = $conn->prepare("SELECT IFNULL(SUM(amount_received),0) FROM payment_receipts WHERE employee_id = ?");
    $rec->execute([$employee['id']]);
    $duePayments = floatval($inv->fetchColumn()) - floatval($rec->fetchColumn());

    $sp = $conn->prepare("SELECT IFNULL(SUM(amount),0) FROM transactions WHERE employee_id = ? AND category = 'Salaries' AND type = 'WITHDRAWAL'");
    $sp->execute([$employee['id']]);
    $salaryPaid = floatval($sp->fetchColumn());

    $cs = $conn->prepare("SELECT status, COUNT(*) n, SUM(CASE WHEN status='PAID' THEN amount ELSE 0 END) pt FROM expense_claims WHERE employee_id = ? GROUP BY status");
    $cs->execute([$employee['id']]);
    foreach ($cs->fetchAll() as $r) {
        $claimStats[$r['status']] = intval($r['n']);
        $claimStats['paid_total'] += floatval($r['pt']);
    }

    $mv = $conn->prepare("
        SELECT v.*,
            (SELECT COUNT(*) FROM vehicle_readings vr WHERE vr.vehicle_id = v.id AND vr.reading_month = ?) as has_this_month,
            (SELECT reading_km FROM vehicle_readings vr WHERE vr.vehicle_id = v.id ORDER BY vr.reading_month DESC LIMIT 1) as last_km
        FROM vehicles v WHERE v.assigned_employee_id = ?
    ");
    $mv->execute([$thisMonth, $employee['id']]);
    $myVehicles = $mv->fetchAll();

    // Annual sales target progress (only shown when a target is set for this year)
    $thisYear = intval(date('Y'));
    $ts = $conn->prepare("SELECT target_amount FROM sales_targets WHERE employee_id = ? AND target_year = ?");
    $ts->execute([$employee['id'], $thisYear]);
    $myTarget = $ts->fetchColumn();
    $myTarget = $myTarget === false ? null : floatval($myTarget);
    $yearSales = 0.0;
    if ($myTarget) {
        $ys = $conn->prepare("SELECT IFNULL(SUM(total_amount),0) FROM invoices WHERE employee_id = ? AND YEAR(date) = ?");
        $ys->execute([$employee['id'], $thisYear]);
        $yearSales = floatval($ys->fetchColumn());
    }
}

$flash = getFlashMessage();
$roleLabel = ($account['role'] ?? 'admin') === 'admin' ? 'Admin — full access' : 'User — limited access';
$permLabels = [];
if (($account['role'] ?? '') === 'user') {
    $mods = adminModules();
    foreach ((json_decode($account['permissions'] ?? '', true) ?: []) as $p) {
        if (isset($mods[$p])) $permLabels[] = $mods[$p];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Profile - AGROVISE</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=ed3">
    <?php if ($employee): ?><script src="../assets/js/chart.umd.min.js"></script><?php endif; ?>
</head>
<body class="admin-body">
    <div class="admin-wrapper">
        <?php require __DIR__ . '/../includes/admin-sidebar.php'; ?>

        <main class="admin-main">
            <div class="admin-header"><h1><i class="fas fa-id-card"></i> My Profile</h1></div>

            <?php if ($flash): ?>
            <div class="alert alert-<?php echo $flash['type']; ?>"><?php echo $flash['message']; ?></div>
            <?php endif; ?>
            <?php if (!empty($errors)): ?>
            <div class="alert alert-error"><ul style="margin:0; padding-left:20px;"><?php foreach ($errors as $e) echo "<li>" . sanitize($e) . "</li>"; ?></ul></div>
            <?php endif; ?>

            <?php if ($employee): ?>
            <!-- My Sales snapshot -->
            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:0; border:1px solid rgba(15,26,14,0.14); background:#fff; margin-bottom:20px;">
                <div style="padding:22px 24px; border-right:1px solid rgba(15,26,14,0.1);">
                    <div style="font-size:0.6rem; letter-spacing:0.24em; text-transform:uppercase; color:#8a8f83;">This Month's Sales</div>
                    <div style="font-family:'Cormorant Garamond',serif; font-size:1.7rem; color:#0f1a0e;">Rs. <?php echo number_format($thisMonthSales, 0); ?></div>
                </div>
                <div style="padding:22px 24px; border-right:1px solid rgba(15,26,14,0.1);">
                    <div style="font-size:0.6rem; letter-spacing:0.24em; text-transform:uppercase; color:#8a8f83;">Total Sales</div>
                    <div style="font-family:'Cormorant Garamond',serif; font-size:1.7rem; color:#0f1a0e;">Rs. <?php echo number_format($salesTotal, 0); ?></div>
                </div>
                <div style="padding:22px 24px;">
                    <div style="font-size:0.6rem; letter-spacing:0.24em; text-transform:uppercase; color:#8a8f83;">Due Payments (My Clients)</div>
                    <div style="font-family:'Cormorant Garamond',serif; font-size:1.7rem; color:<?php echo $duePayments > 1 ? '#b3261e' : '#2e7d32'; ?>;">Rs. <?php echo number_format($duePayments, 0); ?></div>
                </div>
            </div>

            <?php if ($myTarget): $tgtPct = $yearSales / $myTarget * 100; $tgtDone = $tgtPct >= 100; ?>
            <!-- Annual sales target progress -->
            <div class="data-card" style="margin-bottom:20px;">
                <div class="data-card-header"><h2><i class="fas fa-bullseye"></i> My <?php echo $thisYear; ?> Sales Target</h2></div>
                <div style="padding:18px 20px; display:flex; align-items:center; gap:16px; flex-wrap:wrap;">
                    <div style="position:relative; flex:1; min-width:200px; height:24px; background:#eef0e9; border:1px solid rgba(15,26,14,0.14); overflow:hidden;">
                        <div style="height:100%; width:<?php echo min(100, $tgtPct); ?>%; background:<?php echo $tgtDone ? '#2e7d32' : '#c2a04f'; ?>; transition:width 0.6s ease;"></div>
                    </div>
                    <span style="font-weight:600; white-space:nowrap; color:<?php echo $tgtDone ? '#2e7d32' : '#23291f'; ?>;">
                        Rs. <?php echo number_format($yearSales, 0); ?> of Rs. <?php echo number_format($myTarget, 0); ?>
                        (<?php echo number_format($tgtPct, 1); ?>%)
                    </span>
                    <?php if ($tgtDone): ?>
                    <span class="badge" style="background:#E8F5E9; color:#2E7D32;"><i class="fas fa-trophy"></i> Target achieved!</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="data-card" style="margin-bottom:20px;">
                <div class="data-card-header"><h2><i class="fas fa-chart-column"></i> My Monthly Sales</h2></div>
                <div style="padding:20px;">
                    <?php if (empty($monthlySales)): ?>
                        <p style="color:#8a8f83; text-align:center; padding:20px 0;">No sales recorded under your name yet.</p>
                    <?php else: ?>
                    <div style="position:relative; height:260px;"><canvas id="mySalesChart"></canvas></div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; align-items:start;">

                <!-- Account & employee card -->
                <div class="data-card">
                    <div class="data-card-header"><h2><i class="fas fa-user-circle"></i> Account</h2></div>
                    <div style="padding:20px;">
                        <p><strong>Username:</strong> <?php echo sanitize($account['username']); ?></p>
                        <p><strong>Email:</strong> <?php echo sanitize($account['email'] ?? '') ?: '—'; ?></p>
                        <p><strong>Access Level:</strong> <span class="badge" style="background:<?php echo ($account['role'] ?? '') === 'admin' ? '#E8F5E9; color:#2E7D32' : '#E3F2FD; color:#1565C0'; ?>;"><?php echo $roleLabel; ?></span></p>
                        <?php if (!empty($permLabels)): ?>
                        <p><strong>My Modules:</strong> <?php echo sanitize(implode(', ', $permLabels)); ?></p>
                        <?php endif; ?>
                        <?php if ($employee): ?>
                        <hr style="border:0; border-top:1px dashed #ddd; margin:16px 0;">
                        <p><strong>Name:</strong> <?php echo sanitize($employee['name']); ?></p>
                        <p><strong>Designation:</strong> <?php echo sanitize($employee['role']); ?></p>
                        <p><strong>Phone:</strong> <?php echo sanitize($employee['phone']); ?></p>
                        <p><strong>CNIC:</strong> <?php echo sanitize($employee['cnic']); ?></p>
                        <p><strong>Monthly Salary:</strong> Rs. <?php echo number_format($employee['salary'], 2); ?></p>
                        <?php else: ?>
                        <p style="color:#888; margin-top:14px;"><i class="fas fa-info-circle"></i> This account is not linked to an employee record.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($employee): ?>
                <!-- Performance & money card -->
                <div class="data-card">
                    <div class="data-card-header"><h2><i class="fas fa-chart-line"></i> My Numbers</h2></div>
                    <div style="padding:20px;">
                        <p><strong>Sales Generated:</strong> <?php echo $salesCount; ?> invoice<?php echo $salesCount == 1 ? '' : 's'; ?> — Rs. <?php echo number_format($salesTotal, 2); ?></p>
                        <p><strong>Salary Paid To Date:</strong> Rs. <?php echo number_format($salaryPaid, 2); ?></p>
                        <hr style="border:0; border-top:1px dashed #ddd; margin:16px 0;">
                        <p><strong>Expense Claims:</strong></p>
                        <p style="font-size:0.9rem;">
                            <span class="badge" style="background:#FFF8E1; color:#B26A00;">Waiting: <?php echo $claimStats['WAITING']; ?></span>
                            <span class="badge" style="background:#E3F2FD; color:#1565C0;">Approved: <?php echo $claimStats['APPROVED']; ?></span>
                            <span class="badge" style="background:#FFEBEE; color:#B3261E;">Declined: <?php echo $claimStats['DECLINED']; ?></span>
                            <span class="badge" style="background:#E8F5E9; color:#2E7D32;">Paid: <?php echo $claimStats['PAID']; ?></span>
                        </p>
                        <p><strong>Total Reimbursed:</strong> Rs. <?php echo number_format($claimStats['paid_total'], 2); ?></p>
                        <a href="expenses.php" class="btn btn-outline btn-sm" style="margin-top:6px;"><i class="fas fa-hand-holding-dollar"></i> Go to Expenses</a>
                    </div>
                </div>

                <!-- Issued vehicle(s) + monthly reading -->
                <div class="data-card" style="grid-column: 1 / -1;">
                    <div class="data-card-header"><h2><i class="fas fa-car"></i> Vehicle Issued To Me</h2></div>
                    <div style="padding:20px;">
                        <?php if (empty($myVehicles)): ?>
                        <p style="color:#888;">No company vehicle is currently issued to you.</p>
                        <?php else: foreach ($myVehicles as $v): ?>
                        <div style="border:1px solid #e0e6dd; border-radius:10px; padding:18px; margin-bottom:16px;">
                            <div style="display:flex; justify-content:space-between; flex-wrap:wrap; gap:12px; align-items:center;">
                                <div>
                                    <strong style="font-size:1.05rem;"><?php echo sanitize($v['make'] . ' ' . $v['model']); ?></strong>
                                    — <?php echo sanitize($v['plate_number']); ?>
                                    <br><small style="color:#888;"><?php echo sanitize($v['color'] ?? ''); ?> <?php echo $v['year_of_issue'] ? '· ' . $v['year_of_issue'] : ''; ?>
                                    <?php echo $v['last_km'] !== null ? '· last reading ' . number_format($v['last_km'], 1) . ' km' : ''; ?></small>
                                </div>
                                <div>
                                    <?php if ($v['has_this_month']): ?>
                                    <span class="badge" style="background:#E8F5E9; color:#2E7D32;"><i class="fas fa-check"></i> <?php echo date('F'); ?> reading submitted</span>
                                    <?php else: ?>
                                    <span class="badge" style="background:#FFF8E1; color:#B26A00;"><i class="fas fa-triangle-exclamation"></i> <?php echo date('F'); ?> reading due</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if (!$v['has_this_month']): ?>
                            <form method="POST" enctype="multipart/form-data" style="margin-top:14px; display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end;">
                        <?php echo csrfField(); ?>
                                <input type="hidden" name="submit_reading" value="1">
                                <input type="hidden" name="vehicle_id" value="<?php echo $v['id']; ?>">
                                <div class="form-group" style="margin:0;">
                                    <label class="form-label">Meter Reading (km) *</label>
                                    <input type="number" name="reading_km" class="form-input" step="0.1" min="0" required style="width:170px;" placeholder="<?php echo $v['last_km'] !== null ? '≥ ' . $v['last_km'] : 'e.g. 45210'; ?>">
                                </div>
                                <div class="form-group" style="margin:0;">
                                    <label class="form-label">Meter Photo (recommended)</label>
                                    <input type="file" name="meter_image" class="form-input" accept="image/*" style="width:260px;">
                                </div>
                                <button type="submit" class="btn btn-primary"><i class="fas fa-gauge-high"></i> Submit <?php echo date('F'); ?> Reading</button>
                            </form>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <?php if ($employee && !empty($monthlySales)): ?>
    <script>
    new Chart(document.getElementById('mySalesChart'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_column($monthlySales, 'month')); ?>,
            datasets: [{
                label: 'Sales (Rs.)',
                data: <?php echo json_encode(array_map('floatval', array_column($monthlySales, 'sales'))); ?>,
                backgroundColor: 'rgba(194, 160, 79, 0.8)',
                hoverBackgroundColor: '#0f1a0e',
                borderRadius: 2,
                maxBarThickness: 46
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, grid: { color: 'rgba(15,26,14,0.06)' }, ticks: { callback: v => 'Rs. ' + v.toLocaleString() } },
                x: { grid: { display: false } }
            }
        }
    });
    </script>
    <?php endif; ?>
</body>
</html>
