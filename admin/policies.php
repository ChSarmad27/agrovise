<?php
/**
 * AGROVISE - Sales Policies
 * Named product bundles at agreed prices (e.g. "Eid Policy"), priced by the
 * Policy Calculator and applicable to an invoice in one click.
 */
require_once '../includes/db.php';
require_once '../includes/functions.php';
requirePermission('policies');

$conn = getDBConnection();

// Delete (the policy_items rows go with it; invoices keep their own line items)
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = intval($_GET['delete']);
    try {
        $conn->beginTransaction();
        $conn->prepare("DELETE FROM policy_items WHERE policy_id = ?")->execute([$id]);
        $conn->prepare("DELETE FROM policies WHERE id = ?")->execute([$id]);
        $conn->commit();
        setFlashMessage('success', 'Policy deleted. Invoices already raised under it are unaffected.');
    } catch (PDOException $e) {
        $conn->rollBack();
        setFlashMessage('error', dbError($e));
    }
    header('Location: policies.php');
    exit;
}

// Toggle active / inactive
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $id = intval($_GET['toggle']);
    try {
        $conn->prepare("UPDATE policies SET status = IF(status = 'ACTIVE', 'INACTIVE', 'ACTIVE') WHERE id = ?")->execute([$id]);
        setFlashMessage('success', 'Policy status updated.');
    } catch (PDOException $e) {
        setFlashMessage('error', dbError($e));
    }
    header('Location: policies.php');
    exit;
}

$search = trim($_GET['search'] ?? '');

$query = "
    SELECT p.*,
           (SELECT COUNT(*) FROM policy_items pi WHERE pi.policy_id = p.id) AS item_count,
           (SELECT COUNT(*) FROM invoices i WHERE i.policy_id = p.id) AS invoice_count
    FROM policies p
    WHERE 1=1";
$params = [];

if ($search !== '') {
    $query .= " AND (p.name LIKE ? OR p.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$query .= " ORDER BY p.status ASC, p.id DESC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$policies = $stmt->fetchAll();

// Product lines per policy, shown inline so the bundle is readable at a glance
$lines = [];
if ($policies) {
    $ids = array_column($policies, 'id');
    $in  = implode(',', array_fill(0, count($ids), '?'));
    $ls  = $conn->prepare("
        SELECT pi.policy_id, pi.quantity, pi.price, pi.sales_tax, pr.name
        FROM policy_items pi JOIN products pr ON pr.id = pi.product_id
        WHERE pi.policy_id IN ($in) ORDER BY pi.id ASC");
    $ls->execute($ids);
    foreach ($ls->fetchAll() as $l) {
        $lines[$l['policy_id']][] = $l;
    }
}

$flash = getFlashMessage();
$today = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Policies - AGROVISE Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=ed3">
    <style>
        .filters-bar { display: flex; gap: 15px; margin-bottom: 20px; align-items: center; }
        .pol-lines { list-style: none; margin: 6px 0 0; padding: 0; }
        .pol-lines li { font-size: 0.78rem; color: #5c6356; padding: 2px 0; }
        .pol-lines li i { color: #c2a04f; font-size: 0.5rem; vertical-align: middle; margin-right: 6px; }
        .pill {
            display: inline-block; padding: 4px 11px; font-size: 0.62rem; font-weight: 700;
            letter-spacing: 0.12em; text-transform: uppercase;
        }
        .pill.on  { background: rgba(46, 125, 50, 0.14); color: #2e7d32; }
        .pill.off { background: rgba(15, 26, 14, 0.09); color: #8a8f83; }
        .pill.exp { background: rgba(198, 40, 40, 0.12); color: #c62828; }
        .amount-cell {
            font-family: 'Cormorant Garamond', Georgia, serif;
            font-weight: 600; font-size: 1.2rem; color: #0f1a0e; white-space: nowrap;
        }
    </style>
</head>
<body class="admin-body">
    <div class="admin-wrapper">
        <?php require __DIR__ . '/../includes/admin-sidebar.php'; ?>

        <main class="admin-main">
            <div class="admin-header">
                <h1><i class="fas fa-scroll"></i> Sales Policies</h1>
            </div>

            <?php if ($flash): ?>
            <div class="alert alert-<?php echo $flash['type']; ?>"><?php echo $flash['message']; ?></div>
            <?php endif; ?>

            <div class="data-card">
                <div class="data-card-header">
                    <h2><i class="fas fa-list"></i> Policy List</h2>
                    <div style="display: flex; gap: 8px;">
                        <a href="policy-calculator.php" class="btn btn-outline btn-sm"><i class="fas fa-calculator"></i> Policy Calculator</a>
                        <a href="add-policy.php" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> New Policy</a>
                    </div>
                </div>

                <div style="padding: 20px;">
                    <form method="GET" class="filters-bar">
                        <input type="text" name="search" class="form-input" placeholder="Search policies..." value="<?php echo sanitize($search); ?>" style="max-width: 300px;">
                        <button type="submit" class="btn btn-secondary btn-sm"><i class="fas fa-search"></i> Search</button>
                        <a href="policies.php" class="btn btn-outline btn-sm"><i class="fas fa-redo"></i> Reset</a>
                    </form>

                    <table class="data-table">
                        <thead>
                            <tr>
                                <th style="width: 34%;">Policy</th>
                                <th>Validity</th>
                                <th>Products</th>
                                <th>Policy Price</th>
                                <th>Used On</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($policies)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 34px 10px; color: #8a8f83;">
                                    No policies yet. Build one in the <a href="policy-calculator.php" style="color:#c2a04f; font-weight:600;">Policy Calculator</a>.
                                </td>
                            </tr>
                            <?php endif; ?>

                            <?php foreach ($policies as $p): ?>
                            <?php
                                $expired = $p['end_date'] && $p['end_date'] < $today;
                                $notYet  = $p['start_date'] && $p['start_date'] > $today;
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo sanitize($p['name']); ?></strong>
                                    <?php if ($p['description']): ?>
                                    <div style="color:#8a8f83; font-size:0.78rem; margin-top:3px;"><?php echo sanitize($p['description']); ?></div>
                                    <?php endif; ?>
                                    <ul class="pol-lines">
                                        <?php foreach (($lines[$p['id']] ?? []) as $l): ?>
                                        <li>
                                            <i class="fas fa-circle"></i>
                                            <?php echo sanitize($l['name']); ?> &mdash;
                                            <?php echo rtrim(rtrim(number_format($l['quantity'], 2, '.', ''), '0'), '.'); ?>
                                            &times; Rs. <?php echo number_format($l['price'], 2); ?>
                                            <?php if ($l['sales_tax'] > 0): ?>
                                                <small>(+<?php echo rtrim(rtrim(number_format($l['sales_tax'], 2, '.', ''), '0'), '.'); ?>% tax)</small>
                                            <?php endif; ?>
                                        </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </td>
                                <td>
                                    <?php if ($p['start_date'] || $p['end_date']): ?>
                                        <small>
                                            <?php echo $p['start_date'] ? date('d M Y', strtotime($p['start_date'])) : 'Any time'; ?>
                                            &rarr;
                                            <?php echo $p['end_date'] ? date('d M Y', strtotime($p['end_date'])) : 'Open'; ?>
                                        </small>
                                    <?php else: ?>
                                        <small style="color:#8a8f83;">No date limit</small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo intval($p['item_count']); ?></td>
                                <td class="amount-cell">Rs. <?php echo number_format($p['total_amount'], 2); ?></td>
                                <td>
                                    <?php if ($p['invoice_count']): ?>
                                        <?php echo intval($p['invoice_count']); ?> invoice(s)
                                    <?php else: ?>
                                        <small style="color:#8a8f83;">Not used yet</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($p['status'] === 'INACTIVE'): ?>
                                        <span class="pill off">Inactive</span>
                                    <?php elseif ($expired): ?>
                                        <span class="pill exp">Expired</span>
                                    <?php elseif ($notYet): ?>
                                        <span class="pill off">Scheduled</span>
                                    <?php else: ?>
                                        <span class="pill on">Active</span>
                                    <?php endif; ?>
                                </td>
                                <td class="action-btns">
                                    <div style="display: flex; gap: 5px;">
                                        <a href="?toggle=<?php echo $p['id']; ?>" class="btn-icon edit" title="<?php echo $p['status'] === 'ACTIVE' ? 'Deactivate' : 'Activate'; ?>">
                                            <i class="fas <?php echo $p['status'] === 'ACTIVE' ? 'fa-toggle-on' : 'fa-toggle-off'; ?>"></i>
                                        </a>
                                        <a href="edit-policy.php?id=<?php echo $p['id']; ?>" class="btn-icon edit" title="Edit"><i class="fas fa-edit"></i></a>
                                        <a href="?delete=<?php echo $p['id']; ?>" class="btn-icon delete" title="Delete" onclick="return confirm('Delete this policy? Invoices already raised under it are unaffected.');"><i class="fas fa-trash"></i></a>
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
