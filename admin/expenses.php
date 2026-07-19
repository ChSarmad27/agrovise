<?php
/**
 * AGROVISE - Expense Claims
 * Every logged-in account can see this page:
 *  - Accounts linked to an employee can file claims and track their status.
 *  - Admins and 'expenses'-permission users additionally review claims here.
 * Employees never see banking details: payment shows only "Bank" or "Cash".
 */
require_once '../includes/db.php';
require_once '../includes/functions.php';
requireAdminLogin();

$conn = getDBConnection();
$myEmployeeId = currentEmployeeId();
$isApprover = canApproveExpenses();
$errors = [];

$claimTypes = ['Travel Expense', 'Car Maintenance', 'Food & Lodging', 'Other'];

// ---- Submit a new claim (employee-linked accounts only) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_claim'])) {
    if (!$myEmployeeId) {
        $errors[] = 'Your login is not linked to an employee record, so you cannot file claims.';
    } else {
        $type = in_array($_POST['claim_type'] ?? '', $claimTypes, true) ? $_POST['claim_type'] : '';
        $amount = floatval($_POST['claim_amount'] ?? 0);
        $description = trim($_POST['claim_description'] ?? '');

        if ($type === '') $errors[] = 'Select the type of expense.';
        if ($amount <= 0) $errors[] = 'Enter the claimed amount.';

        $billImage = null;
        if (isset($_FILES['bill_image']) && $_FILES['bill_image']['error'] === UPLOAD_ERR_OK) {
            $billImage = uploadBillImage($_FILES['bill_image']);
            if (!$billImage) $errors[] = 'Bill image must be JPG, PNG, GIF or WebP (max 5MB).';
        }

        if (empty($errors)) {
            $ins = $conn->prepare("INSERT INTO expense_claims (employee_id, type, amount, description, bill_image) VALUES (?, ?, ?, ?, ?)");
            $ins->execute([$myEmployeeId, $type, $amount, $description ?: null, $billImage]);

            $empName = $conn->query("SELECT name FROM employees WHERE id = " . intval($myEmployeeId))->fetchColumn();
            notifyExpenseApprovers($conn, sanitize($empName) . ' submitted a ' . sanitize($type) . ' claim of Rs. ' . number_format($amount, 2) . ' for approval.');

            setFlashMessage('success', 'Expense claim submitted! It is now Waiting for approval.');
            header('Location: expenses.php');
            exit;
        }
    }
}

// ---- Approve / Decline (approvers only) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['review_claim']) && $isApprover) {
    $claimId = intval($_POST['claim_id'] ?? 0);
    $decision = $_POST['decision'] === 'approve' ? 'APPROVED' : 'DECLINED';

    $st = $conn->prepare("SELECT * FROM expense_claims WHERE id = ? AND status = 'WAITING'");
    $st->execute([$claimId]);
    if ($claim = $st->fetch()) {
        $conn->prepare("UPDATE expense_claims SET status = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?")
             ->execute([$decision, $_SESSION['admin_id'], $claimId]);
        notifyEmployeeAccount($conn, $claim['employee_id'],
            'Your ' . sanitize($claim['type']) . ' claim of Rs. ' . number_format($claim['amount'], 2) . ' was ' . strtolower($decision) . '.');
        setFlashMessage('success', 'Claim ' . strtolower($decision) . '.');
    } else {
        setFlashMessage('error', 'Claim not found or already reviewed.');
    }
    header('Location: expenses.php');
    exit;
}

// ---- Mark this account's notifications as read (they're viewing them now) ----
$conn->prepare("UPDATE notifications SET is_read = 1 WHERE admin_id = ? AND is_read = 0")->execute([$_SESSION['admin_id']]);

// ---- Data for the page ----
$myNotifications = $conn->prepare("SELECT * FROM notifications WHERE admin_id = ? ORDER BY created_at DESC LIMIT 8");
$myNotifications->execute([$_SESSION['admin_id']]);
$myNotifications = $myNotifications->fetchAll();

$myClaims = [];
if ($myEmployeeId) {
    $st = $conn->prepare("SELECT * FROM expense_claims WHERE employee_id = ? ORDER BY created_at DESC");
    $st->execute([$myEmployeeId]);
    $myClaims = $st->fetchAll();
}

$allClaims = [];
if ($isApprover) {
    $allClaims = $conn->query("
        SELECT ec.*, e.name as employee_name, e.role as employee_role, a.username as reviewer_name
        FROM expense_claims ec
        JOIN employees e ON ec.employee_id = e.id
        LEFT JOIN admins a ON ec.reviewed_by = a.id
        ORDER BY FIELD(ec.status, 'WAITING') DESC, ec.created_at DESC
    ")->fetchAll();
}

$flash = getFlashMessage();

function claimStatusBadge($claim) {
    switch ($claim['status']) {
        case 'WAITING':  return '<span class="badge" style="background:#FFF8E1; color:#B26A00;"><i class="fas fa-hourglass-half"></i> Waiting</span>';
        case 'APPROVED': return '<span class="badge" style="background:#E3F2FD; color:#1565C0;"><i class="fas fa-check"></i> Approved</span>';
        case 'DECLINED': return '<span class="badge" style="background:#FFEBEE; color:#B3261E;"><i class="fas fa-times"></i> Declined</span>';
        case 'PAID':
            $via = $claim['paid_via'] === 'CASH' ? 'Cash' : 'Bank';
            return '<span class="badge" style="background:#E8F5E9; color:#2E7D32;"><i class="fas fa-money-check"></i> Amount Paid — ' . $via . '</span>';
    }
    return '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Expenses - AGROVISE</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=ed3">
</head>
<body class="admin-body">
    <div class="admin-wrapper">
        <?php require __DIR__ . '/../includes/admin-sidebar.php'; ?>

        <main class="admin-main">
            <div class="admin-header">
                <h1><i class="fas fa-hand-holding-dollar"></i> Expenses</h1>
                <?php if ($myEmployeeId): ?>
                <button class="btn btn-primary" onclick="document.getElementById('claimCard').style.display='block'; window.scrollTo({top:0, behavior:'smooth'});">
                    <i class="fas fa-plus"></i> Claim Expenses
                </button>
                <?php endif; ?>
            </div>

            <?php if ($flash): ?>
            <div class="alert alert-<?php echo $flash['type']; ?>"><?php echo $flash['message']; ?></div>
            <?php endif; ?>
            <?php if (!empty($errors)): ?>
            <div class="alert alert-error"><ul style="margin:0; padding-left:20px;"><?php foreach ($errors as $e) echo "<li>" . sanitize($e) . "</li>"; ?></ul></div>
            <?php endif; ?>

            <!-- Claim form (hidden until "Claim Expenses" is clicked, or shown after errors) -->
            <?php if ($myEmployeeId): ?>
            <div class="data-card" id="claimCard" style="max-width:640px; margin-bottom:20px; display:<?php echo !empty($errors) ? 'block' : 'none'; ?>;">
                <div class="data-card-header"><h2>New Expense Claim</h2></div>
                <div style="padding:20px;">
                    <form method="POST" enctype="multipart/form-data">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="submit_claim" value="1">
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                            <div class="form-group">
                                <label class="form-label">Type of Expense *</label>
                                <select name="claim_type" class="form-select" required>
                                    <option value="">-- Select --</option>
                                    <?php foreach ($claimTypes as $t): ?>
                                    <option value="<?php echo $t; ?>" <?php echo (($_POST['claim_type'] ?? '') === $t) ? 'selected' : ''; ?>><?php echo $t; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Amount (Rs) *</label>
                                <input type="number" name="claim_amount" class="form-input" step="0.01" min="0.01" required value="<?php echo sanitize($_POST['claim_amount'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Bill / Receipt Image (JPG, PNG, WebP — max 5MB)</label>
                            <input type="file" name="bill_image" class="form-input" accept="image/*">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Description (optional — nature of the expense)</label>
                            <textarea name="claim_description" class="form-textarea" placeholder="e.g. Fuel for client visits in Multan region, 12-14 June"><?php echo sanitize($_POST['claim_description'] ?? ''); ?></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Submit Claim</button>
                        <button type="button" class="btn btn-outline" onclick="document.getElementById('claimCard').style.display='none';">Cancel</button>
                    </form>
                </div>
            </div>
            <?php else: ?>
            <div class="alert alert-error" style="background:#FFF8E1; color:#B26A00; border-color:#FFE082;">
                <i class="fas fa-info-circle"></i> Your login is not linked to an employee record, so you cannot file claims yourself.
                <?php echo $isApprover ? 'You can still review claims below.' : 'Contact an administrator.'; ?>
            </div>
            <?php endif; ?>

            <!-- Recent notifications -->
            <?php if (!empty($myNotifications)): ?>
            <div class="data-card" style="margin-bottom:20px;">
                <div class="data-card-header"><h2><i class="fas fa-bell"></i> Notifications</h2></div>
                <div style="padding: 12px 20px;">
                    <?php foreach ($myNotifications as $n): ?>
                    <p style="padding:8px 0; border-bottom:1px dashed #e0e0e0; margin:0; font-size:0.88rem;">
                        <span style="color:#888; font-size:0.75rem;"><?php echo date('d M, H:i', strtotime($n['created_at'])); ?></span> —
                        <?php echo $n['message']; ?>
                    </p>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- My claims -->
            <?php if ($myEmployeeId): ?>
            <div class="data-card" style="margin-bottom:20px;">
                <div class="data-card-header"><h2><i class="fas fa-list"></i> My Expense Claims</h2></div>
                <div style="padding:20px;">
                    <table class="data-table">
                        <thead><tr><th>Date</th><th>Type</th><th>Amount</th><th>Bill</th><th>Description</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php if (empty($myClaims)): ?>
                            <tr><td colspan="6" style="text-align:center;">No claims yet — click "Claim Expenses" to file one.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($myClaims as $c): ?>
                            <tr>
                                <td><?php echo date('d M Y', strtotime($c['created_at'])); ?></td>
                                <td><?php echo sanitize($c['type']); ?></td>
                                <td><strong>Rs. <?php echo number_format($c['amount'], 2); ?></strong></td>
                                <td><?php echo $c['bill_image'] ? '<a href="../uploads/expenses/' . sanitize($c['bill_image']) . '" target="_blank" class="btn btn-outline btn-sm"><i class="fas fa-image"></i> View</a>' : '—'; ?></td>
                                <td style="max-width:260px;"><?php echo sanitize($c['description'] ?? '') ?: '—'; ?></td>
                                <td><?php echo claimStatusBadge($c); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- Approvals (admins + accounts users) -->
            <?php if ($isApprover): ?>
            <div class="data-card">
                <div class="data-card-header"><h2><i class="fas fa-clipboard-check"></i> All Claims — Review &amp; Approval</h2></div>
                <div style="padding:20px;">
                    <table class="data-table">
                        <thead><tr><th>Date</th><th>Employee</th><th>Type</th><th>Amount</th><th>Bill</th><th>Description</th><th>Status</th><th>Actions</th></tr></thead>
                        <tbody>
                            <?php if (empty($allClaims)): ?>
                            <tr><td colspan="8" style="text-align:center;">No claims filed yet.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($allClaims as $c): ?>
                            <tr>
                                <td><?php echo date('d M Y', strtotime($c['created_at'])); ?></td>
                                <td><strong><?php echo sanitize($c['employee_name']); ?></strong><br><small style="color:#888;"><?php echo sanitize($c['employee_role']); ?></small></td>
                                <td><?php echo sanitize($c['type']); ?></td>
                                <td><strong>Rs. <?php echo number_format($c['amount'], 2); ?></strong></td>
                                <td><?php echo $c['bill_image'] ? '<a href="../uploads/expenses/' . sanitize($c['bill_image']) . '" target="_blank" class="btn btn-outline btn-sm"><i class="fas fa-image"></i> View</a>' : '—'; ?></td>
                                <td style="max-width:220px;"><?php echo sanitize($c['description'] ?? '') ?: '—'; ?></td>
                                <td><?php echo claimStatusBadge($c); ?><?php if ($c['reviewer_name']): ?><br><small style="color:#888;">by <?php echo sanitize($c['reviewer_name']); ?></small><?php endif; ?></td>
                                <td>
                                    <?php if ($c['status'] === 'WAITING'): ?>
                                    <form method="POST" style="display:inline;">
                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="review_claim" value="1">
                                        <input type="hidden" name="claim_id" value="<?php echo $c['id']; ?>">
                                        <button type="submit" name="decision" value="approve" class="btn btn-primary btn-sm" onclick="return confirm('Approve this claim?');"><i class="fas fa-check"></i></button>
                                        <button type="submit" name="decision" value="decline" class="btn btn-outline btn-sm" onclick="return confirm('Decline this claim?');" style="color:#B3261E;"><i class="fas fa-times"></i></button>
                                    </form>
                                    <?php elseif ($c['status'] === 'APPROVED'): ?>
                                    <a href="add-transaction.php?expense_claim=<?php echo $c['id']; ?>" class="btn btn-secondary btn-sm" title="Log the payment in Banking"><i class="fas fa-money-bill-transfer"></i> Pay</a>
                                    <?php else: ?>—<?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
