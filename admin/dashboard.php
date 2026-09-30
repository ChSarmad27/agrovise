<?php
/**
 * AGROVISE - Admin Dashboard
 * Permission-aware: every KPI, chart, panel and action renders only for
 * accounts that hold the matching module permission (admins hold all).
 */

require_once '../includes/db.php';
require_once '../includes/functions.php';

// Check if admin is logged in
requireAdminLogin();

// Sales officers (users with no granted modules) have their profile as home.
if (!isSuperAdmin() && empty($_SESSION['admin_permissions'])) {
    header('Location: my-profile.php');
    exit;
}

$conn = getDBConnection();

// Module visibility for this account
$canProducts   = hasPermission('products');
$canPurchasing = hasPermission('purchasing');
$canInvoices   = hasPermission('invoices');
$canPayments   = hasPermission('payments');
$canClients    = hasPermission('clients');
$canBanking    = hasPermission('banking');
$canLedgers    = hasPermission('ledgers');
$showSales     = $canInvoices || $canLedgers;
$showRecv      = ($canInvoices && $canPayments) || $canLedgers;

// Handle delete action (before any data is fetched; products permission required)
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    if (!$canProducts) {
        setFlashMessage('error', 'You do not have permission to delete products.');
        header('Location: dashboard.php');
        exit;
    }
    $id = intval($_GET['delete']);
    $product = getProductById($id);

    if ($product) {
        // Delete image
        deleteProductImage($product['image']);

        // Delete from database
        $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$id]);

        setFlashMessage('success', 'Product deleted successfully!');
        header('Location: dashboard.php');
        exit;
    }
}

// Get statistics (products module)
$stats = ['total_products' => 0, 'insecticides' => 0, 'weedicides' => 0, 'fungicides' => 0, 'granulars' => 0, 'micronutrients' => 0];
if ($canProducts) {
    $stats = [
        'total_products' => $conn->query("SELECT COUNT(*) FROM products")->fetchColumn(),
        'insecticides' => $conn->query("SELECT COUNT(*) FROM products WHERE category = 'insecticides'")->fetchColumn(),
        'weedicides' => $conn->query("SELECT COUNT(*) FROM products WHERE category = 'weedicides'")->fetchColumn(),
        'fungicides' => $conn->query("SELECT COUNT(*) FROM products WHERE category = 'fungicides'")->fetchColumn(),
        'granulars' => $conn->query("SELECT COUNT(*) FROM products WHERE category = 'granulars'")->fetchColumn(),
        'micronutrients' => $conn->query("SELECT COUNT(*) FROM products WHERE category = 'micronutrients'")->fetchColumn(),
    ];
}

// Business KPIs (read-only aggregates, computed only when visible)
$totalSales = $totalReceived = $receivables = $bankTotal = $stockValue = 0.0;
$invoiceCount = $clientCount = 0;
if ($showSales)   $totalSales    = floatval($conn->query("SELECT IFNULL(SUM(total_amount), 0) FROM invoices")->fetchColumn());
if ($showRecv) {
    if (!$showSales) $totalSales = floatval($conn->query("SELECT IFNULL(SUM(total_amount), 0) FROM invoices")->fetchColumn());
    $totalReceived = floatval($conn->query("SELECT IFNULL(SUM(amount_received), 0) FROM payment_receipts")->fetchColumn());
    $receivables   = $totalSales - $totalReceived;
}
if ($canBanking)    $bankTotal  = floatval($conn->query("SELECT IFNULL(SUM(balance), 0) FROM banking")->fetchColumn());
if ($canPurchasing) $stockValue = floatval($conn->query("SELECT IFNULL(SUM(quantity * purchase_price), 0) FROM purchasing WHERE quantity > 0")->fetchColumn());
if ($canInvoices)   $invoiceCount = intval($conn->query("SELECT COUNT(*) FROM invoices")->fetchColumn());
if ($canClients)    $clientCount  = intval($conn->query("SELECT COUNT(*) FROM clients")->fetchColumn());

// Monthly sales, last 6 months present in data
$monthlySales = [];
if ($showSales) {
    $monthlySales = $conn->query("
        SELECT DATE_FORMAT(date, '%b %Y') as month, DATE_FORMAT(date, '%Y-%m') as ym, SUM(total_amount) as sales
        FROM invoices
        GROUP BY ym, month
        ORDER BY ym DESC
        LIMIT 6
    ")->fetchAll();
    $monthlySales = array_reverse($monthlySales);
}

// Recent invoices
$recentInvoices = [];
if ($canInvoices) {
    $recentInvoices = $conn->query("
        SELECT i.id, i.invoice_no, i.date, i.total_amount, c.name as client_name
        FROM invoices i JOIN clients c ON i.client_id = c.id
        ORDER BY i.created_at DESC LIMIT 6
    ")->fetchAll();
}

// Low stock lots (running out)
$lowStock = [];
if ($canPurchasing) {
    $lowStock = $conn->query("
        SELECT p.quantity, p.batch_number, p.type, pr.name as product_name
        FROM purchasing p JOIN products pr ON p.product_id = pr.id
        WHERE p.quantity > 0 AND p.quantity <= 20
        ORDER BY p.quantity ASC LIMIT 5
    ")->fetchAll();
}

// Stock lots approaching (or past) their expiry date, tiered by urgency:
// yellow = within 3 months, orange = within 2, red = within 1, expired = past.
$expiryAlerts = [];
if ($canPurchasing) {
    $expiryAlerts = $conn->query("
        SELECT p.id, p.batch_number, p.type, p.quantity, p.expiry_date, pr.name AS product_name,
               DATEDIFF(p.expiry_date, CURDATE()) AS days_left,
               CASE
                   WHEN p.expiry_date < CURDATE() THEN 'expired'
                   WHEN p.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 1 MONTH) THEN 'red'
                   WHEN p.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 2 MONTH) THEN 'orange'
                   ELSE 'yellow'
               END AS tier
        FROM purchasing p
        JOIN products pr ON p.product_id = pr.id
        WHERE p.quantity > 0 AND p.expiry_date IS NOT NULL
          AND p.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 3 MONTH)
        ORDER BY p.expiry_date ASC
        LIMIT 12
    ")->fetchAll();
}

// Clients still owing money with no payment received for 3+ months.
// Aging = months since their last payment receipt (or since their first
// invoice when they have never paid). 3 mo = yellow, 4 = orange, 5+ = red.
$overdueClients = [];
if ($showRecv) {
    $overdueClients = $conn->query("
        SELECT * FROM (
            SELECT c.id, c.name, c.area,
                   IFNULL(inv.total, 0) - IFNULL(pay.total, 0) AS balance,
                   pay.last_date,
                   COALESCE(pay.last_date, inv.first_date) AS ref_date,
                   TIMESTAMPDIFF(MONTH, COALESCE(pay.last_date, inv.first_date), CURDATE()) AS months_overdue
            FROM clients c
            JOIN (SELECT client_id, SUM(total_amount) AS total, MIN(date) AS first_date
                  FROM invoices GROUP BY client_id) inv ON inv.client_id = c.id
            LEFT JOIN (SELECT client_id, SUM(amount_received) AS total, MAX(date) AS last_date
                       FROM payment_receipts GROUP BY client_id) pay ON pay.client_id = c.id
        ) t
        WHERE t.balance > 0.009 AND t.months_overdue >= 3
        ORDER BY t.months_overdue DESC, t.balance DESC
        LIMIT 12
    ")->fetchAll();
}

// Vendors we still owe with no payment made for 3+ months (toggle view in the
// same container). Owed = Σ purchases from the vendor − Σ 'Purchasing'
// withdrawals to them; aging counts from our last payment (or first purchase).
$canVendorDue = $canPurchasing || $canBanking || $canLedgers;
$overdueVendors = [];
if ($canVendorDue) {
    $overdueVendors = $conn->query("
        SELECT * FROM (
            SELECT v.id, v.name,
                   IFNULL(pur.total, 0) - IFNULL(pay.total, 0) AS balance,
                   pay.last_date,
                   COALESCE(pay.last_date, pur.first_date) AS ref_date,
                   TIMESTAMPDIFF(MONTH, COALESCE(pay.last_date, pur.first_date), CURDATE()) AS months_overdue
            FROM vendors v
            JOIN (SELECT vendor_id, SUM(total_price) AS total, MIN(date_added) AS first_date
                  FROM purchasing WHERE vendor_id IS NOT NULL GROUP BY vendor_id) pur ON pur.vendor_id = v.id
            LEFT JOIN (SELECT vendor_id, SUM(amount) AS total, MAX(transaction_date) AS last_date
                       FROM transactions WHERE vendor_id IS NOT NULL AND category = 'Purchasing' AND type = 'WITHDRAWAL'
                       GROUP BY vendor_id) pay ON pay.vendor_id = v.id
        ) t
        WHERE t.balance > 0.009 AND t.months_overdue >= 3
        ORDER BY t.months_overdue DESC, t.balance DESC
        LIMIT 12
    ")->fetchAll();
}

// Top 3 clients by outstanding balance (any age)
$topPending = [];
if ($showRecv) {
    $topPending = $conn->query("
        SELECT * FROM (
            SELECT c.id, c.name, c.area, IFNULL(inv.total, 0) - IFNULL(pay.total, 0) AS balance
            FROM clients c
            JOIN (SELECT client_id, SUM(total_amount) AS total FROM invoices GROUP BY client_id) inv ON inv.client_id = c.id
            LEFT JOIN (SELECT client_id, SUM(amount_received) AS total FROM payment_receipts GROUP BY client_id) pay ON pay.client_id = c.id
        ) t
        WHERE t.balance > 0.009
        ORDER BY t.balance DESC
        LIMIT 3
    ")->fetchAll();
}

// Get recent products
$recentProducts = $canProducts
    ? $conn->query("SELECT * FROM products ORDER BY created_at DESC LIMIT 10")->fetchAll()
    : [];

$flash = getFlashMessage();

$categoryChart = [
    'labels' => ['Insecticides', 'Weedicides', 'Fungicides', 'Granulars', 'Micronutrients'],
    'data' => [
        intval($stats['insecticides']),
        intval($stats['weedicides']),
        intval($stats['fungicides']),
        intval($stats['granulars']),
        intval($stats['micronutrients']),
    ],
];

$anyKpi   = $showSales || $showRecv || $canBanking || $canPurchasing;
$anyChart = $showSales || $canProducts;
$anyPanel = $canInvoices || $canPurchasing;
$nothingVisible = !$anyKpi && !$anyChart && !$anyPanel && !$canProducts;

$accountRole = isSuperAdmin() ? 'Admin' : 'User';

// --- Admin-only: monthly Profit/Loss by area + overall company ---
$plMonths = [];      // ['2026-01' => 'Jan 2026', ...]
$plSeries = [];      // ['Overall Company' => [ym => profit], 'AreaX' => [...]]
$isAdminMain = isSuperAdmin();
if ($isAdminMain) {
    $plRows = $conn->query("
        SELECT DATE_FORMAT(i.date, '%Y-%m') ym, DATE_FORMAT(i.date, '%b %Y') label,
               COALESCE(NULLIF(c.area, ''), 'Unspecified') area,
               SUM(ii.quantity * ii.price) revenue,
               SUM(ii.quantity * pur.purchase_price) cost
        FROM invoices i
        JOIN clients c ON i.client_id = c.id
        JOIN invoice_items ii ON ii.invoice_id = i.id
        JOIN purchasing pur ON ii.purchasing_id = pur.id
        GROUP BY ym, label, area
        ORDER BY ym
    ")->fetchAll();

    foreach ($plRows as $r) {
        $plMonths[$r['ym']] = $r['label'];
        $profit = floatval($r['revenue']) - floatval($r['cost']);
        $plSeries['Overall Company'][$r['ym']] = ($plSeries['Overall Company'][$r['ym']] ?? 0) + $profit;
        $plSeries[$r['area']][$r['ym']] = ($plSeries[$r['area']][$r['ym']] ?? 0) + $profit;
    }
    ksort($plMonths);
    // Build aligned series for JS (profit per month in $plMonths order)
    $plChart = ['labels' => array_values($plMonths), 'series' => []];
    foreach ($plSeries as $area => $byMonth) {
        $row = [];
        foreach (array_keys($plMonths) as $ym) $row[] = round($byMonth[$ym] ?? 0, 2);
        $plChart['series'][$area] = $row;
    }
}

// Top 3 profit-making and loss-making products (admin only, like the P/L chart)
$topProfit = $topLoss = [];
if ($isAdminMain) {
    $topProfit = $conn->query("
        SELECT pr.name, SUM(ii.quantity * (ii.price - pur.purchase_price)) AS profit
        FROM invoice_items ii
        JOIN products pr ON pr.id = ii.product_id
        JOIN purchasing pur ON pur.id = ii.purchasing_id
        GROUP BY pr.id, pr.name
        HAVING profit > 0
        ORDER BY profit DESC
        LIMIT 3
    ")->fetchAll();
    $topLoss = $conn->query("
        SELECT pr.name, SUM(ii.quantity * (ii.price - pur.purchase_price)) AS profit
        FROM invoice_items ii
        JOIN products pr ON pr.id = ii.product_id
        JOIN purchasing pur ON pur.id = ii.purchasing_id
        GROUP BY pr.id, pr.name
        HAVING profit < 0
        ORDER BY profit ASC
        LIMIT 3
    ")->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - AGROVISE</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;1,500&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=ed3">
    <script src="../assets/js/chart.umd.min.js"></script>

    <style>
        /* ---- Dashboard-only styles (Harvest Editorial) ---- */
        .dash-hero {
            background: var(--adm-ink, #0f1a0e);
            border: 1px solid rgba(242, 239, 230, 0.14);
            padding: 34px 38px;
            color: #f2efe6;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 18px;
            margin-bottom: 26px;
            position: relative;
            overflow: hidden;
        }
        .dash-hero::after {
            content: '\f4d8';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            position: absolute;
            right: -16px;
            bottom: -30px;
            font-size: 10rem;
            color: rgba(242, 239, 230, 0.05);
            pointer-events: none;
        }
        .dash-hero .micro-label {
            font-size: 0.62rem;
            font-weight: 500;
            letter-spacing: 0.34em;
            text-transform: uppercase;
            color: #c2a04f;
            display: block;
            margin-bottom: 10px;
        }
        .dash-hero h1 {
            color: #f2efe6;
            font-family: 'Cormorant Garamond', Georgia, serif;
            font-weight: 500;
            font-size: 2.1rem;
            line-height: 1.1;
            margin-bottom: 8px;
        }
        .dash-hero h1 em { font-style: italic; color: #c2a04f; }
        .dash-hero p { color: rgba(242, 239, 230, 0.6); margin: 0; font-size: 0.85rem; letter-spacing: 0.04em; }
        .dash-quick-actions { display: flex; gap: 10px; flex-wrap: wrap; z-index: 1; }
        .dash-quick-actions a {
            background: transparent;
            border: 1px solid rgba(242, 239, 230, 0.3);
            color: #f2efe6;
            padding: 12px 18px;
            font-size: 0.66rem;
            font-weight: 500;
            letter-spacing: 0.22em;
            text-transform: uppercase;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.35s cubic-bezier(0.22, 1, 0.36, 1);
        }
        .dash-quick-actions a:hover {
            background: #c2a04f;
            border-color: #c2a04f;
            color: #0f1a0e;
        }

        .dash-kpis {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(215px, 1fr));
            gap: 0;
            border: 1px solid rgba(15, 26, 14, 0.14);
            background: #fff;
            margin-bottom: 26px;
        }
        .dash-kpi {
            padding: 26px 24px;
            display: flex;
            align-items: center;
            gap: 16px;
            border-right: 1px solid rgba(15, 26, 14, 0.14);
            transition: background 0.4s ease;
        }
        .dash-kpi:last-child { border-right: 0; }
        .dash-kpi:hover { background: #faf8f2; }
        .dash-kpi .icon {
            width: 50px; height: 50px;
            border: 1px solid rgba(15, 26, 14, 0.16);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.05rem;
            color: #c2a04f;
            background: #0f1a0e;
            flex-shrink: 0;
        }
        .dash-kpi h3 {
            font-family: 'Cormorant Garamond', Georgia, serif;
            font-weight: 600;
            font-size: 1.5rem;
            margin: 0;
            color: #23291f;
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
        }
        .dash-kpi p {
            margin: 3px 0 0;
            color: #8a8f83;
            font-size: 0.62rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.26em;
        }

        .dash-grid {
            display: grid;
            grid-template-columns: 1.6fr 1fr;
            gap: 20px;
            margin-bottom: 26px;
        }
        @media (max-width: 1100px) { .dash-grid { grid-template-columns: 1fr; } }
        .dash-panel {
            background: #fff;
            border: 1px solid rgba(15, 26, 14, 0.14);
            padding: 26px;
        }
        .dash-panel h2 {
            font-size: 0.7rem;
            font-weight: 500;
            letter-spacing: 0.3em;
            text-transform: uppercase;
            margin: 0 0 20px;
            color: #23291f;
            display: flex; align-items: center; gap: 12px;
        }
        .dash-panel h2 i { color: #c2a04f; font-size: 0.8rem; }
        .dash-panel .chart-wrap { position: relative; height: 260px; }

        .dash-list { list-style: none; margin: 0; padding: 0; }
        .dash-list li {
            display: flex; justify-content: space-between; align-items: center;
            padding: 12px 2px;
            border-bottom: 1px solid rgba(15, 26, 14, 0.1);
            font-size: 0.88rem;
        }
        .dash-list li:last-child { border-bottom: none; }
        .dash-list .muted { color: #8a8f83; font-size: 0.76rem; }
        .dash-list .amount {
            font-family: 'Cormorant Garamond', Georgia, serif;
            font-weight: 600; font-size: 1.05rem;
            color: #0f1a0e; white-space: nowrap;
        }
        .dash-list .pill {
            border: 1px solid #c2a04f; color: #9a7c33;
            padding: 3px 12px;
            font-size: 0.66rem; font-weight: 500;
            letter-spacing: 0.14em; text-transform: uppercase;
            white-space: nowrap;
        }
        .dash-empty { color: #8a8f83; font-size: 0.85rem; text-align: center; padding: 26px 0; }

        .role-chip {
            display: inline-flex; align-items: center; gap: 7px;
            border: 1px solid rgba(15, 26, 14, 0.2);
            padding: 5px 12px;
            font-size: 0.6rem; font-weight: 500;
            letter-spacing: 0.24em; text-transform: uppercase;
        }
        .role-chip i { color: #c2a04f; font-size: 0.65rem; }

        /* ---- Alert containers (expiry / overdue payments) ---- */
        .dash-grid.halves { grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); }
        .tier-legend { display: flex; flex-wrap: wrap; gap: 8px; margin: -8px 0 16px; }
        .tier-legend span {
            font-size: 0.62rem; font-weight: 500; letter-spacing: 0.1em;
            text-transform: uppercase; padding: 3px 10px; border-left: 3px solid;
        }
        .tier-list { list-style: none; margin: 0; padding: 0; }
        .tier-list li {
            display: flex; justify-content: space-between; align-items: center; gap: 12px;
            padding: 10px 14px; margin-bottom: 8px;
            border-left: 4px solid; font-size: 0.85rem;
        }
        .tier-list li:last-child { margin-bottom: 0; }
        .tier-list .muted { font-size: 0.74rem; opacity: 0.75; }
        .tier-list .amount {
            font-family: 'Cormorant Garamond', Georgia, serif;
            font-weight: 600; font-size: 1.05rem; white-space: nowrap;
        }
        .tier-yellow  { background: #fdf6dc; border-color: #d8b429; color: #6b5a10; }
        .tier-orange  { background: #fdead8; border-color: #e07c26; color: #7a3d08; }
        .tier-red     { background: #fbe2df; border-color: #c62f26; color: #7c1810; }
        .tier-expired { background: #b3261e; border-color: #6e100b; color: #fff; }
        .tier-expired .muted { opacity: 0.85; }
        .tier-chip {
            font-size: 0.6rem; font-weight: 600; letter-spacing: 0.12em;
            text-transform: uppercase; white-space: nowrap;
            padding: 2px 8px; border: 1px solid currentColor;
        }
        .due-toggle { display: inline-flex; border: 1px solid rgba(15, 26, 14, 0.25); }
        .due-toggle button {
            background: #fff; border: 0; padding: 7px 16px; cursor: pointer;
            font-family: inherit; font-size: 0.64rem; font-weight: 600;
            letter-spacing: 0.14em; text-transform: uppercase; color: #8a8f83;
            transition: all 0.25s ease;
        }
        .due-toggle button.active { background: #0f1a0e; color: #c2a04f; }
        .due-caption { font-size: 0.74rem; color: #8a8f83; margin: 0 0 10px; }

        /* ---- Top-3 panels ---- */
        .dash-grid.thirds { grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); }
        .rank-num {
            width: 26px; height: 26px; flex-shrink: 0;
            display: inline-flex; align-items: center; justify-content: center;
            background: #0f1a0e; color: #c2a04f;
            font-size: 0.72rem; font-weight: 600;
        }
        .amount.pos { color: #2e7d32; }
        .amount.neg { color: #b3261e; }
    </style>
</head>
<body class="admin-body">
    <div class="admin-wrapper">
        <!-- Sidebar -->
        <?php require __DIR__ . '/../includes/admin-sidebar.php'; ?>

        <!-- Main Content -->
        <main class="admin-main">
            <div class="admin-header">
                <h1>Dashboard</h1>
                <div class="admin-user">
                    <span class="role-chip"><i class="fas fa-<?php echo $accountRole === 'Admin' ? 'shield-halved' : 'user'; ?>"></i><?php echo $accountRole; ?></span>
                    <span>Welcome, <?php echo sanitize($_SESSION['admin_username']); ?></span>
                </div>
            </div>

            <?php if ($flash): ?>
            <div class="alert alert-<?php echo $flash['type']; ?>">
                <i class="fas fa-<?php echo $flash['type'] === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo $flash['message']; ?>
            </div>
            <?php endif; ?>

            <!-- Welcome / Quick actions -->
            <div class="dash-hero">
                <div>
                    <span class="micro-label">Agrovise &middot; The Back Office</span>
                    <h1>Good <?php $h = intval(date('G')); echo $h < 12 ? 'Morning' : ($h < 17 ? 'Afternoon' : 'Evening'); ?>, <em><?php echo sanitize($_SESSION['admin_username']); ?></em></h1>
                    <p><?php
                        $bits = [date('l, d F Y')];
                        if ($canClients)  $bits[] = $clientCount . ' clients';
                        if ($canInvoices) $bits[] = $invoiceCount . ' invoices to date';
                        echo implode(' &middot; ', $bits);
                    ?></p>
                </div>
                <div class="dash-quick-actions">
                    <?php if ($canInvoices): ?><a href="add-invoice.php"><i class="fas fa-file-invoice-dollar"></i> New Invoice</a><?php endif; ?>
                    <?php if ($canPayments): ?><a href="add-pr.php"><i class="fas fa-receipt"></i> Receive Payment</a><?php endif; ?>
                    <?php if ($canPurchasing): ?><a href="add-purchase.php"><i class="fas fa-shopping-cart"></i> New Purchase</a><?php endif; ?>
                    <?php if ($canProducts): ?><a href="add-product.php"><i class="fas fa-plus-circle"></i> Add Product</a><?php endif; ?>
                </div>
            </div>

            <?php if ($nothingVisible): ?>
            <div class="dash-panel">
                <div class="dash-empty">
                    <i class="fas fa-lock" style="font-size:1.6rem; color:#c2a04f; display:block; margin-bottom:12px;"></i>
                    Your account has no modules assigned yet.<br>Please contact an administrator to be granted access.
                </div>
            </div>
            <?php endif; ?>

            <?php if ($anyKpi): ?>
            <!-- Business KPIs -->
            <div class="dash-kpis">
                <?php if ($showSales): ?>
                <div class="dash-kpi">
                    <div class="icon"><i class="fas fa-chart-line"></i></div>
                    <div>
                        <h3>Rs. <span class="countup" data-value="<?php echo $totalSales; ?>">0</span></h3>
                        <p>Total Sales</p>
                    </div>
                </div>
                <?php endif; ?>
                <?php if ($showRecv): ?>
                <div class="dash-kpi">
                    <div class="icon"><i class="fas fa-hand-holding-dollar"></i></div>
                    <div>
                        <h3>Rs. <span class="countup" data-value="<?php echo $receivables; ?>">0</span></h3>
                        <p>Receivables Due</p>
                    </div>
                </div>
                <?php endif; ?>
                <?php if ($canBanking): ?>
                <div class="dash-kpi">
                    <div class="icon"><i class="fas fa-university"></i></div>
                    <div>
                        <h3>Rs. <span class="countup" data-value="<?php echo $bankTotal; ?>">0</span></h3>
                        <p>Cash &amp; Bank</p>
                    </div>
                </div>
                <?php endif; ?>
                <?php if ($canPurchasing): ?>
                <div class="dash-kpi">
                    <div class="icon"><i class="fas fa-warehouse"></i></div>
                    <div>
                        <h3>Rs. <span class="countup" data-value="<?php echo $stockValue; ?>">0</span></h3>
                        <p>Stock Value (Cost)</p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($expiryAlerts) || $showRecv || $canVendorDue): ?>
            <!-- Alert containers: expiring stock + overdue payments (clients/vendors) -->
            <div class="dash-grid halves">
                <?php if ($canPurchasing && !empty($expiryAlerts)): ?>
                <div class="dash-panel">
                    <h2><i class="fas fa-hourglass-half"></i> Expiry Alerts</h2>
                    <div class="tier-legend">
                        <span class="tier-yellow">&le; 3 months</span>
                        <span class="tier-orange">&le; 2 months</span>
                        <span class="tier-red">&le; 1 month</span>
                        <span class="tier-expired">Expired</span>
                    </div>
                    <ul class="tier-list">
                        <?php foreach ($expiryAlerts as $ea): ?>
                        <li class="tier-<?php echo $ea['tier']; ?>">
                            <div>
                                <strong><?php echo sanitize($ea['product_name']); ?></strong>
                                <span class="muted">&middot; Batch <?php echo sanitize($ea['batch_number']); ?> &middot; <?php echo sanitize($ea['type']); ?></span><br>
                                <span class="muted">
                                    <?php echo rtrim(rtrim(number_format($ea['quantity'], 2), '0'), '.'); ?> in stock &middot;
                                    expires <?php echo date('d M Y', strtotime($ea['expiry_date'])); ?>
                                </span>
                            </div>
                            <span class="tier-chip">
                                <?php
                                    $d = intval($ea['days_left']);
                                    if ($d < 0)      echo 'Expired ' . abs($d) . 'd ago';
                                    elseif ($d == 0) echo 'Expires today';
                                    else             echo $d . ' days left';
                                ?>
                            </span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
                <?php
                    // Both views exist whenever the account may see them — the
                    // toggle is always offered even when a list is currently empty.
                    $hasDueClients = $showRecv;
                    $hasDueVendors = $canVendorDue;
                    if ($hasDueClients || $hasDueVendors):
                ?>
                <div class="dash-panel">
                    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:16px;">
                        <h2 style="margin:0;"><i class="fas fa-user-clock"></i> Overdue Payments</h2>
                        <?php if ($hasDueClients && $hasDueVendors): ?>
                        <div class="due-toggle">
                            <button type="button" id="dueBtnClients" class="active" onclick="showDue('clients')">Clients</button>
                            <button type="button" id="dueBtnVendors" onclick="showDue('vendors')">Vendors</button>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="tier-legend">
                        <span class="tier-yellow">3 months unpaid</span>
                        <span class="tier-orange">4 months</span>
                        <span class="tier-red">5+ months</span>
                    </div>

                    <?php if ($hasDueClients): ?>
                    <div id="dueClients">
                        <p class="due-caption">Clients who owe us and haven't paid:</p>
                        <?php if (empty($overdueClients)): ?>
                        <div class="dash-empty"><i class="fas fa-check-circle" style="color:#c2a04f;"></i> No clients are 3+ months overdue.</div>
                        <?php else: ?>
                        <ul class="tier-list">
                            <?php foreach ($overdueClients as $oc):
                                $m = intval($oc['months_overdue']);
                                $tier = $m >= 5 ? 'red' : ($m >= 4 ? 'orange' : 'yellow');
                            ?>
                            <li class="tier-<?php echo $tier; ?>">
                                <div>
                                    <strong><?php echo sanitize($oc['name']); ?></strong>
                                    <span class="muted">&middot; <?php echo sanitize($oc['area']); ?></span><br>
                                    <span class="muted">
                                        <?php echo $oc['last_date']
                                            ? 'last payment ' . date('d M Y', strtotime($oc['last_date']))
                                            : 'never paid — first invoice ' . date('d M Y', strtotime($oc['ref_date'])); ?>
                                        &middot; <?php echo $m; ?> month<?php echo $m == 1 ? '' : 's'; ?> ago
                                    </span>
                                </div>
                                <a href="ledger-client.php?client_id=<?php echo $oc['id']; ?>" class="amount" style="text-decoration:none; color:inherit;" title="Open client ledger">
                                    Rs. <?php echo number_format($oc['balance'], 0); ?>
                                </a>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <?php if ($hasDueVendors): ?>
                    <div id="dueVendors" <?php if ($hasDueClients) echo 'style="display:none;"'; ?>>
                        <p class="due-caption">Vendors we owe and haven't paid:</p>
                        <?php if (empty($overdueVendors)): ?>
                        <div class="dash-empty"><i class="fas fa-check-circle" style="color:#c2a04f;"></i> No vendors are 3+ months overdue.</div>
                        <?php else: ?>
                        <ul class="tier-list">
                            <?php foreach ($overdueVendors as $ov):
                                $m = intval($ov['months_overdue']);
                                $tier = $m >= 5 ? 'red' : ($m >= 4 ? 'orange' : 'yellow');
                            ?>
                            <li class="tier-<?php echo $tier; ?>">
                                <div>
                                    <strong><?php echo sanitize($ov['name']); ?></strong><br>
                                    <span class="muted">
                                        <?php echo $ov['last_date']
                                            ? 'last paid ' . date('d M Y', strtotime($ov['last_date']))
                                            : 'never paid — first purchase ' . date('d M Y', strtotime($ov['ref_date'])); ?>
                                        &middot; <?php echo $m; ?> month<?php echo $m == 1 ? '' : 's'; ?> ago
                                    </span>
                                </div>
                                <span class="amount">Rs. <?php echo number_format($ov['balance'], 0); ?></span>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if ($isAdminMain): ?>
            <!-- Profit / Loss by area + overall company (admin only) -->
            <div class="dash-panel" style="margin-bottom:26px;">
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:14px; margin-bottom:16px;">
                    <h2 style="margin:0;"><i class="fas fa-chart-area"></i> Profit &amp; Loss</h2>
                    <select id="plAreaSelect" class="form-select" style="max-width:260px;" onchange="renderPL()">
                        <?php foreach (array_keys($plChart['series']) as $area): ?>
                        <option value="<?php echo sanitize($area); ?>"><?php echo $area === 'Overall Company' ? 'Overall Company' : sanitize($area); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if (empty($plChart['labels'])): ?>
                    <div class="dash-empty">No sales data yet to chart profit &amp; loss.</div>
                <?php else: ?>
                <div class="chart-wrap" style="height:300px;"><canvas id="plChart"></canvas></div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if ($isAdminMain || !empty($topPending)): ?>
            <!-- Top 3: profit-makers, loss-makers, pending payments -->
            <div class="dash-grid thirds">
                <?php if ($isAdminMain): ?>
                <div class="dash-panel">
                    <h2><i class="fas fa-arrow-trend-up"></i> Top 3 Profits</h2>
                    <?php if (empty($topProfit)): ?>
                        <div class="dash-empty">No profitable product sales yet.</div>
                    <?php else: ?>
                    <ul class="dash-list">
                        <?php foreach ($topProfit as $i => $tp): ?>
                        <li>
                            <div style="display:flex; align-items:center; gap:12px;">
                                <span class="rank-num"><?php echo $i + 1; ?></span>
                                <strong><?php echo sanitize($tp['name']); ?></strong>
                            </div>
                            <span class="amount pos">+ Rs. <?php echo number_format($tp['profit'], 0); ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </div>
                <div class="dash-panel">
                    <h2><i class="fas fa-arrow-trend-down"></i> Top 3 Losses</h2>
                    <?php if (empty($topLoss)): ?>
                        <div class="dash-empty"><i class="fas fa-check-circle" style="color:#c2a04f;"></i> No loss-making products.</div>
                    <?php else: ?>
                    <ul class="dash-list">
                        <?php foreach ($topLoss as $i => $tl): ?>
                        <li>
                            <div style="display:flex; align-items:center; gap:12px;">
                                <span class="rank-num"><?php echo $i + 1; ?></span>
                                <strong><?php echo sanitize($tl['name']); ?></strong>
                            </div>
                            <span class="amount neg">&minus; Rs. <?php echo number_format(abs($tl['profit']), 0); ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <?php if ($showRecv): ?>
                <div class="dash-panel">
                    <h2><i class="fas fa-hand-holding-dollar"></i> Top 3 Pending Payments</h2>
                    <?php if (empty($topPending)): ?>
                        <div class="dash-empty"><i class="fas fa-check-circle" style="color:#c2a04f;"></i> No outstanding client balances.</div>
                    <?php else: ?>
                    <ul class="dash-list">
                        <?php foreach ($topPending as $i => $tpp): ?>
                        <li>
                            <div style="display:flex; align-items:center; gap:12px;">
                                <span class="rank-num"><?php echo $i + 1; ?></span>
                                <div>
                                    <strong><?php echo sanitize($tpp['name']); ?></strong><br>
                                    <span class="muted"><?php echo sanitize($tpp['area']); ?></span>
                                </div>
                            </div>
                            <a href="ledger-client.php?client_id=<?php echo $tpp['id']; ?>" class="amount neg" style="text-decoration:none;" title="Open client ledger">
                                Rs. <?php echo number_format($tpp['balance'], 0); ?>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if ($anyChart): ?>
            <!-- Charts -->
            <div class="dash-grid">
                <?php if ($showSales): ?>
                <div class="dash-panel">
                    <h2><i class="fas fa-chart-column"></i> Sales by Month</h2>
                    <?php if (empty($monthlySales)): ?>
                        <div class="dash-empty">No invoices yet — the chart appears with your first sale.</div>
                    <?php else: ?>
                    <div class="chart-wrap"><canvas id="salesChart"></canvas></div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <?php if ($canProducts): ?>
                <div class="dash-panel">
                    <h2><i class="fas fa-chart-pie"></i> Catalog Mix (<?php echo $stats['total_products']; ?> products)</h2>
                    <?php if (intval($stats['total_products']) === 0): ?>
                        <div class="dash-empty">No products in the catalog yet.</div>
                    <?php else: ?>
                    <div class="chart-wrap"><canvas id="categoryChart"></canvas></div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if ($anyPanel): ?>
            <div class="dash-grid">
                <?php if ($canInvoices): ?>
                <div class="dash-panel">
                    <h2><i class="fas fa-file-invoice"></i> Latest Invoices</h2>
                    <?php if (empty($recentInvoices)): ?>
                        <div class="dash-empty">No invoices yet — create your first one.</div>
                    <?php else: ?>
                    <ul class="dash-list">
                        <?php foreach ($recentInvoices as $inv): ?>
                        <li>
                            <div>
                                <strong><?php echo sanitize($inv['invoice_no']); ?></strong>
                                <span class="muted"> &middot; <?php echo sanitize($inv['client_name']); ?></span><br>
                                <span class="muted"><?php echo date('d M Y', strtotime($inv['date'])); ?></span>
                            </div>
                            <div style="display:flex; align-items:center; gap:12px;">
                                <span class="amount">Rs. <?php echo number_format($inv['total_amount'], 0); ?></span>
                                <a href="print-invoice.php?id=<?php echo $inv['id']; ?>" class="btn-icon" title="Print" target="_blank"><i class="fas fa-print"></i></a>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <?php if ($canPurchasing): ?>
                <div class="dash-panel">
                    <h2><i class="fas fa-triangle-exclamation"></i> Low Stock Alerts</h2>
                    <?php if (empty($lowStock)): ?>
                        <div class="dash-empty"><i class="fas fa-check-circle" style="color: #c2a04f;"></i> All stock levels are healthy.</div>
                    <?php else: ?>
                    <ul class="dash-list">
                        <?php foreach ($lowStock as $ls): ?>
                        <li>
                            <div>
                                <strong><?php echo sanitize($ls['product_name']); ?></strong><br>
                                <span class="muted">Batch <?php echo sanitize($ls['batch_number']); ?> &middot; <?php echo sanitize($ls['type']); ?></span>
                            </div>
                            <span class="pill"><?php echo rtrim(rtrim(number_format($ls['quantity'], 2), '0'), '.'); ?> left</span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if ($canProducts): ?>
            <!-- Recent Products -->
            <div class="data-card">
                <div class="data-card-header">
                    <h2><i class="fas fa-list"></i> Recent Products</h2>
                    <a href="add-product.php" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus"></i> Add New Product
                    </a>
                </div>

                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Image</th>
                            <th>Product Name</th>
                            <th>Category</th>
                            <th>Packing</th>
                            <th>Published</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentProducts as $product): ?>
                        <tr>
                            <td>
                                <img src="<?php echo getProductImagePath($product['image'], '..'); ?>" alt="<?php echo sanitize($product['name']); ?>" class="product-thumb">
                            </td>
                            <td><strong><?php echo sanitize($product['name']); ?></strong></td>
                            <td>
                                <span class="category-badge <?php echo $product['category']; ?>">
                                    <?php echo getCategoryName($product['category']); ?>
                                </span>
                            </td>
                            <td><span class="badge" style="background:#eef0e9; color:#3d5237;"><?php echo sanitize($product['packing_type'] ?? 'None'); ?></span></td>
                            <td>
                                <?php $is_pub = $product['is_published'] ?? 0; ?>
                                <span style="color: <?php echo $is_pub ? '#3d5237' : '#999'; ?>; font-weight: bold;">
                                    <?php echo $is_pub ? 'Yes' : 'No'; ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-btns">
                                    <a href="edit-product.php?id=<?php echo $product['id']; ?>" class="btn-icon edit" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="?delete=<?php echo $product['id']; ?>" class="btn-icon delete" title="Delete" onclick="return confirm('Are you sure you want to delete this product?');">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
    // ---- KPI count-up animation ----
    document.querySelectorAll('.countup').forEach(el => {
        const target = parseFloat(el.dataset.value) || 0;
        const dur = 900;
        const t0 = performance.now();
        function tick(t) {
            const p = Math.min((t - t0) / dur, 1);
            const eased = 1 - Math.pow(1 - p, 3);
            el.textContent = Math.round(target * eased).toLocaleString('en-PK');
            if (p < 1) requestAnimationFrame(tick);
        }
        requestAnimationFrame(tick);
    });

    // ---- Overdue payments: client / vendor toggle ----
    function showDue(which) {
        const c = document.getElementById('dueClients'), v = document.getElementById('dueVendors');
        if (c) c.style.display = which === 'clients' ? '' : 'none';
        if (v) v.style.display = which === 'vendors' ? '' : 'none';
        const bc = document.getElementById('dueBtnClients'), bv = document.getElementById('dueBtnVendors');
        if (bc) bc.classList.toggle('active', which === 'clients');
        if (bv) bv.classList.toggle('active', which === 'vendors');
    }

    // ---- Charts (editorial palette; canvases render only when permitted) ----
    const brand = { ink: '#0f1a0e', gold: '#c2a04f', sage: '#8a9a82', olive: '#4a5d43', wheat: '#dcc389' };

    // ---- Profit / Loss by area (admin) ----
    <?php if ($isAdminMain && !empty($plChart['labels'])): ?>
    const plData = <?php echo json_encode($plChart); ?>;
    let plChartObj = null;
    const plProfitColor = 'rgba(74, 93, 67, 0.9)', plLossColor = 'rgba(179, 38, 30, 0.9)';
    function renderPL() {
        const area = document.getElementById('plAreaSelect').value;
        const series = plData.series[area] || [];
        const pointColors = series.map(v => v >= 0 ? plProfitColor : plLossColor);
        if (plChartObj) {
            plChartObj.data.datasets[0].data = series;
            plChartObj.data.datasets[0].pointBackgroundColor = pointColors;
            plChartObj.data.datasets[0].label = area + ' — Profit/Loss (Rs.)';
            plChartObj.update();
            return;
        }
        plChartObj = new Chart(document.getElementById('plChart'), {
            type: 'line',
            data: { labels: plData.labels, datasets: [{
                label: area + ' — Profit/Loss (Rs.)',
                data: series,
                tension: 0.35,
                borderWidth: 2.5,
                pointRadius: 4,
                pointHoverRadius: 6,
                pointBackgroundColor: pointColors,
                pointBorderColor: '#fff',
                // line turns red on segments dipping below zero; fill tints by sign
                segment: { borderColor: c => (c.p0.parsed.y < 0 || c.p1.parsed.y < 0) ? plLossColor : plProfitColor },
                borderColor: plProfitColor,
                fill: { target: 'origin', above: 'rgba(74, 93, 67, 0.10)', below: 'rgba(179, 38, 30, 0.10)' }
            }]},
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false },
                    tooltip: { callbacks: { label: c => 'Rs. ' + c.raw.toLocaleString() } } },
                scales: {
                    y: { grid: { color: 'rgba(15,26,14,0.06)' }, ticks: { callback: v => 'Rs. ' + v.toLocaleString() } },
                    x: { grid: { display: false } }
                }
            }
        });
    }
    renderPL();
    <?php endif; ?>

    const salesCanvas = document.getElementById('salesChart');
    if (salesCanvas) {
        new Chart(salesCanvas, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($monthlySales, 'month')); ?>,
                datasets: [{
                    label: 'Sales (Rs.)',
                    data: <?php echo json_encode(array_map('floatval', array_column($monthlySales, 'sales'))); ?>,
                    borderColor: brand.gold,
                    backgroundColor: 'rgba(194, 160, 79, 0.15)',
                    fill: true,
                    tension: 0.35,
                    borderWidth: 2.5,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    pointBackgroundColor: brand.ink,
                    pointBorderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, grid: { color: 'rgba(15,26,14,0.06)' }, ticks: { callback: v => 'Rs. ' + v.toLocaleString() } },
                    x: { grid: { display: false } }
                }
            }
        });
    }

    const catCanvas = document.getElementById('categoryChart');
    if (catCanvas) {
        new Chart(catCanvas, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($categoryChart['labels']); ?>,
                datasets: [{
                    data: <?php echo json_encode($categoryChart['data']); ?>,
                    backgroundColor: [brand.gold, brand.sage, brand.olive, brand.wheat, brand.ink],
                    borderWidth: 2,
                    borderColor: '#fff',
                    hoverOffset: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '62%',
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 10, padding: 14, font: { size: 11 } } }
                }
            }
        });
    }
    </script>
</body>
</html>
