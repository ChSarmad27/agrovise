<?php
/**
 * AGROVISE - Sales Targets
 * Admin-only. List view: every employee with an annual sales target for the
 * selected year and a completion bar (achieved = invoices carrying their
 * name that year). Detail view (?id=): the employee's info, target progress,
 * monthly sales chart and the year's invoices. Linked from the employee
 * list's View button, so it also works for employees without a target.
 */
require_once '../includes/db.php';
require_once '../includes/functions.php';
requirePermission('employees');

$conn = getDBConnection();

$yNow = intval(date('Y'));
$year = intval($_GET['year'] ?? $yNow);
if ($year < 2000 || $year > 2100) $year = $yNow;
$viewId = intval($_GET['id'] ?? 0);

// Years offered in the selector: any year having a target, plus current
$yearOptions = $conn->query("SELECT DISTINCT target_year FROM sales_targets ORDER BY target_year DESC")->fetchAll(PDO::FETCH_COLUMN);
$yearOptions = array_map('intval', $yearOptions);
if (!in_array($yNow, $yearOptions, true)) $yearOptions[] = $yNow;
rsort($yearOptions);

$detail = null;
if ($viewId) {
    // ---------- DETAIL: one employee ----------
    $st = $conn->prepare("
        SELECT e.*, a.role AS access_role, a.username AS access_username
        FROM employees e LEFT JOIN admins a ON a.employee_id = e.id
        WHERE e.id = ?
    ");
    $st->execute([$viewId]);
    $detail = $st->fetch();
    if (!$detail) {
        setFlashMessage('error', 'Employee not found.');
        header('Location: sales-targets.php');
        exit;
    }

    $tt = $conn->prepare("SELECT target_amount FROM sales_targets WHERE employee_id = ? AND target_year = ?");
    $tt->execute([$viewId, $year]);
    $targetAmount = $tt->fetchColumn();
    $targetAmount = $targetAmount === false ? null : floatval($targetAmount);

    $as = $conn->prepare("SELECT COUNT(*) c, IFNULL(SUM(total_amount),0) t FROM invoices WHERE employee_id = ? AND YEAR(date) = ?");
    $as->execute([$viewId, $year]);
    $aRow = $as->fetch();
    $achieved = floatval($aRow['t']);
    $invoiceCount = intval($aRow['c']);

    $pc = $conn->prepare("SELECT IFNULL(SUM(amount_received),0) FROM payment_receipts WHERE employee_id = ? AND YEAR(date) = ?");
    $pc->execute([$viewId, $year]);
    $collected = floatval($pc->fetchColumn());

    // Sales per month of the selected year (zero-filled Jan..Dec)
    $mm = $conn->prepare("SELECT MONTH(date) m, SUM(total_amount) s FROM invoices WHERE employee_id = ? AND YEAR(date) = ? GROUP BY m");
    $mm->execute([$viewId, $year]);
    $byMonth = array_fill(1, 12, 0.0);
    foreach ($mm->fetchAll() as $r) $byMonth[intval($r['m'])] = floatval($r['s']);

    $iv = $conn->prepare("
        SELECT i.id, i.invoice_no, i.date, i.total_amount, c.name AS client_name
        FROM invoices i JOIN clients c ON i.client_id = c.id
        WHERE i.employee_id = ? AND YEAR(i.date) = ?
        ORDER BY i.date DESC, i.id DESC
        LIMIT 100
    ");
    $iv->execute([$viewId, $year]);
    $yearInvoices = $iv->fetchAll();
} else {
    // ---------- LIST: all employees with a target this year ----------
    $ls = $conn->prepare("
        SELECT st.employee_id, st.target_amount, e.name, e.role, IFNULL(s.total, 0) AS achieved
        FROM sales_targets st
        JOIN employees e ON e.id = st.employee_id
        LEFT JOIN (
            SELECT employee_id, SUM(total_amount) AS total
            FROM invoices WHERE YEAR(date) = ? GROUP BY employee_id
        ) s ON s.employee_id = st.employee_id
        WHERE st.target_year = ?
        ORDER BY IFNULL(s.total, 0) / st.target_amount DESC, e.name ASC
    ");
    $ls->execute([$year, $year]);
    $rows = $ls->fetchAll();
}

$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sales Targets - AGROVISE</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=ed3">
    <?php if ($viewId): ?><script src="../assets/js/chart.umd.min.js"></script><?php endif; ?>
    <style>
        .tgt-bar {
            position: relative; height: 18px; min-width: 160px;
            background: #eef0e9; border: 1px solid rgba(15, 26, 14, 0.14);
            overflow: hidden;
        }
        .tgt-fill { height: 100%; background: #c2a04f; transition: width 0.6s ease; }
        .tgt-fill.done { background: #2e7d32; }
        .tgt-pct { font-weight: 600; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .tgt-pct.done { color: #2e7d32; }
        .tgt-kpis {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 0; border: 1px solid rgba(15, 26, 14, 0.14); background: #fff; margin-bottom: 20px;
        }
        .tgt-kpi { padding: 22px 24px; border-right: 1px solid rgba(15, 26, 14, 0.1); }
        .tgt-kpi:last-child { border-right: 0; }
        .tgt-kpi .lbl { font-size: 0.6rem; letter-spacing: 0.24em; text-transform: uppercase; color: #8a8f83; }
        .tgt-kpi .val { font-family: 'Cormorant Garamond', Georgia, serif; font-size: 1.7rem; color: #0f1a0e; }
        .year-form select { max-width: 140px; display: inline-block; }
    </style>
</head>
<body class="admin-body">
    <div class="admin-wrapper">
        <?php require __DIR__ . '/../includes/admin-sidebar.php'; ?>
        <main class="admin-main">
            <div class="admin-header">
                <h1><i class="fas fa-bullseye"></i> Sales Targets<?php if ($detail) echo ': ' . sanitize($detail['name']); ?></h1>
                <form method="GET" class="year-form">
                    <?php if ($viewId): ?><input type="hidden" name="id" value="<?php echo $viewId; ?>"><?php endif; ?>
                    <select name="year" class="form-select" onchange="this.form.submit()">
                        <?php foreach ($yearOptions as $yo): ?>
                        <option value="<?php echo $yo; ?>" <?php echo $yo === $year ? 'selected' : ''; ?>><?php echo $yo; ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>

            <?php if ($flash): ?>
            <div class="alert alert-<?php echo $flash['type']; ?>"><?php echo $flash['message']; ?></div>
            <?php endif; ?>

            <?php if ($detail): ?>
            <!-- ================= DETAIL VIEW ================= -->
            <p style="margin-bottom:16px;">
                <a href="sales-targets.php?year=<?php echo $year; ?>" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> All Targets</a>
                <a href="edit-employee.php?id=<?php echo $viewId; ?>" class="btn btn-outline btn-sm"><i class="fas fa-edit"></i> Edit Employee</a>
            </p>

            <div class="tgt-kpis">
                <div class="tgt-kpi">
                    <div class="lbl"><?php echo $year; ?> Target</div>
                    <div class="val"><?php echo $targetAmount !== null ? 'Rs. ' . number_format($targetAmount, 0) : '—'; ?></div>
                </div>
                <div class="tgt-kpi">
                    <div class="lbl">Achieved (<?php echo $invoiceCount; ?> invoice<?php echo $invoiceCount == 1 ? '' : 's'; ?>)</div>
                    <div class="val">Rs. <?php echo number_format($achieved, 0); ?></div>
                </div>
                <div class="tgt-kpi">
                    <div class="lbl">Payments Collected</div>
                    <div class="val">Rs. <?php echo number_format($collected, 0); ?></div>
                </div>
                <?php if ($targetAmount): $pct = $achieved / $targetAmount * 100; ?>
                <div class="tgt-kpi">
                    <div class="lbl">Completion</div>
                    <div class="val <?php echo $pct >= 100 ? 'tgt-pct done' : ''; ?>"><?php echo number_format($pct, 1); ?>%</div>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($targetAmount): ?>
            <div class="data-card" style="margin-bottom:20px;">
                <div style="padding:18px 20px; display:flex; align-items:center; gap:16px; flex-wrap:wrap;">
                    <div class="tgt-bar" style="flex:1; height:24px;">
                        <div class="tgt-fill <?php echo $pct >= 100 ? 'done' : ''; ?>" style="width:<?php echo min(100, $pct); ?>%;"></div>
                    </div>
                    <span class="tgt-pct <?php echo $pct >= 100 ? 'done' : ''; ?>">
                        Rs. <?php echo number_format($achieved, 0); ?> of Rs. <?php echo number_format($targetAmount, 0); ?>
                        (<?php echo number_format($pct, 1); ?>%)
                    </span>
                </div>
            </div>
            <?php else: ?>
            <div class="alert alert-error" style="margin-bottom:20px;">
                <i class="fas fa-info-circle"></i> No sales target is set for <?php echo sanitize($detail['name']); ?> in <?php echo $year; ?>.
                <a href="edit-employee.php?id=<?php echo $viewId; ?>">Set one on the employee form</a>.
            </div>
            <?php endif; ?>

            <div style="display:grid; grid-template-columns: 1fr 1.4fr; gap:20px; align-items:start;">
                <!-- Employee info -->
                <div class="data-card">
                    <div class="data-card-header"><h2><i class="fas fa-id-card"></i> Employee</h2></div>
                    <div style="padding:20px;">
                        <p><strong>Name:</strong> <?php echo sanitize($detail['name']); ?></p>
                        <p><strong>Designation:</strong> <?php echo sanitize($detail['role']); ?></p>
                        <p><strong>Phone:</strong> <?php echo sanitize($detail['phone']); ?></p>
                        <p><strong>CNIC:</strong> <?php echo sanitize($detail['cnic']); ?></p>
                        <p><strong>Monthly Salary:</strong> Rs. <?php echo number_format($detail['salary'], 2); ?></p>
                        <p><strong>System Access:</strong>
                            <?php if ($detail['access_role'] === 'admin'): ?>
                                <span class="badge" style="background:#e8f5e9; color:#2e7d32;">Admin (<?php echo sanitize($detail['access_username']); ?>)</span>
                            <?php elseif ($detail['access_role'] === 'user'): ?>
                                <span class="badge" style="background:#e3f2fd; color:#1565c0;">User (<?php echo sanitize($detail['access_username']); ?>)</span>
                            <?php else: ?>
                                <span class="badge" style="background:#f1f1f1; color:#888;">No login</span>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>

                <!-- Monthly sales chart -->
                <div class="data-card">
                    <div class="data-card-header"><h2><i class="fas fa-chart-column"></i> <?php echo $year; ?> Sales by Month</h2></div>
                    <div style="padding:20px;">
                        <?php if ($achieved <= 0): ?>
                        <p style="color:#8a8f83; text-align:center; padding:20px 0;">No sales recorded under this employee in <?php echo $year; ?>.</p>
                        <?php else: ?>
                        <div style="position:relative; height:260px;"><canvas id="monthChart"></canvas></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Invoices of the year -->
            <div class="data-card" style="margin-top:20px;">
                <div class="data-card-header"><h2><i class="fas fa-file-invoice-dollar"></i> Invoices in <?php echo $year; ?></h2></div>
                <div style="padding:20px;">
                    <?php if (empty($yearInvoices)): ?>
                    <p style="color:#8a8f83; text-align:center; padding:10px 0;">No invoices in <?php echo $year; ?>.</p>
                    <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr><th>Invoice #</th><th>Date</th><th>Client</th><th style="text-align:right;">Amount (Rs)</th><th></th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($yearInvoices as $inv): ?>
                            <tr>
                                <td><strong><?php echo sanitize($inv['invoice_no']); ?></strong></td>
                                <td><?php echo date('d M Y', strtotime($inv['date'])); ?></td>
                                <td><?php echo sanitize($inv['client_name']); ?></td>
                                <td style="text-align:right;"><strong><?php echo number_format($inv['total_amount'], 0); ?></strong></td>
                                <td><a href="print-invoice.php?id=<?php echo $inv['id']; ?>" class="btn-icon" title="Print" target="_blank"><i class="fas fa-print"></i></a></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($achieved > 0): ?>
            <script>
            new Chart(document.getElementById('monthChart'), {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode(array_map(fn($m) => date('M', mktime(0, 0, 0, $m, 1)), range(1, 12))); ?>,
                    datasets: [{
                        label: 'Sales (Rs.)',
                        data: <?php echo json_encode(array_values($byMonth)); ?>,
                        backgroundColor: 'rgba(194, 160, 79, 0.8)',
                        hoverBackgroundColor: '#0f1a0e',
                        borderRadius: 2,
                        maxBarThickness: 40
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

            <?php else: ?>
            <!-- ================= LIST VIEW ================= -->
            <div class="data-card">
                <div class="data-card-header">
                    <h2><?php echo $year; ?> Targets</h2>
                    <a href="employees.php" class="btn btn-primary btn-sm"><i class="fas fa-id-badge"></i> Manage Employees</a>
                </div>
                <div style="padding: 20px;">
                    <?php if (empty($rows)): ?>
                    <p style="color:#8a8f83; text-align:center; padding:20px 0;">
                        No sales targets set for <?php echo $year; ?> yet.
                        Set one from an employee's <a href="employees.php">edit form</a>.
                    </p>
                    <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Designation</th>
                                <th style="text-align:right;">Target (Rs)</th>
                                <th style="text-align:right;">Achieved (Rs)</th>
                                <th style="width:32%;">Completion</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $r):
                                $pct = floatval($r['target_amount']) > 0 ? floatval($r['achieved']) / floatval($r['target_amount']) * 100 : 0;
                                $done = $pct >= 100;
                            ?>
                            <tr>
                                <td><a href="sales-targets.php?id=<?php echo $r['employee_id']; ?>&year=<?php echo $year; ?>" style="font-weight:600; color:inherit;"><?php echo sanitize($r['name']); ?></a></td>
                                <td><span class="badge" style="background:#e9ecef; color:#333;"><?php echo sanitize($r['role']); ?></span></td>
                                <td style="text-align:right;"><?php echo number_format($r['target_amount'], 0); ?></td>
                                <td style="text-align:right;"><strong><?php echo number_format($r['achieved'], 0); ?></strong></td>
                                <td>
                                    <div style="display:flex; align-items:center; gap:10px;">
                                        <div class="tgt-bar" style="flex:1;">
                                            <div class="tgt-fill <?php echo $done ? 'done' : ''; ?>" style="width:<?php echo min(100, $pct); ?>%;"></div>
                                        </div>
                                        <span class="tgt-pct <?php echo $done ? 'done' : ''; ?>"><?php echo number_format($pct, 1); ?>%</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="action-btns">
                                        <a href="sales-targets.php?id=<?php echo $r['employee_id']; ?>&year=<?php echo $year; ?>" class="btn-icon" title="View sales detail"><i class="fas fa-eye"></i></a>
                                        <a href="edit-employee.php?id=<?php echo $r['employee_id']; ?>" class="btn-icon edit" title="Edit employee / target"><i class="fas fa-edit"></i></a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
