<?php
/**
 * AGROVISE - ERP Ledger & Reporting System
 */
require_once '../includes/db.php';
require_once '../includes/functions.php';
requirePermission('ledgers');

$conn = getDBConnection();

// --- 1. OVERALL FINANCIAL SUMMARY ---
$salesRes = $conn->query("SELECT SUM(total_amount) as total_sales FROM invoices")->fetch();
$totalSales = floatval($salesRes['total_sales'] ?? 0);

$purchRes = $conn->query("SELECT SUM(total_price) as total_purchases FROM purchasing WHERE type != 'FINISHED'")->fetch();
$totalPurchases = floatval($purchRes['total_purchases'] ?? 0);

// Operating Expenses: Withdrawals for Salaries, Office, etc.
$opExpRes = $conn->query("SELECT SUM(amount) as op_exp FROM transactions WHERE type = 'WITHDRAWAL' AND category IN ('Salaries', 'Office Expenses', 'Custom')")->fetch();
$operatingExpenses = floatval($opExpRes['op_exp'] ?? 0);

$netProfit = $totalSales - ($totalPurchases + $operatingExpenses);

// --- 2. CLIENT BALANCES (RECEIVABLES) ---
$clientsStmt = $conn->query("
    SELECT c.id, c.name, c.area,
           IFNULL((SELECT SUM(total_amount) FROM invoices WHERE client_id = c.id), 0) as total_invoiced,
           IFNULL((SELECT SUM(amount_received) FROM payment_receipts WHERE client_id = c.id), 0) as total_paid
    FROM clients c
    ORDER BY c.name
");
$clientLedger = $clientsStmt->fetchAll();

// --- 3. VENDOR BALANCES (PAYABLES) ---
$vendorsStmt = $conn->query("
    SELECT v.id, v.name,
           IFNULL((SELECT SUM(total_price) FROM purchasing WHERE vendor_id = v.id), 0) as total_purchased,
           IFNULL((SELECT SUM(amount) FROM transactions WHERE vendor_id = v.id AND category = 'Purchasing' AND type = 'WITHDRAWAL'), 0) as total_paid
    FROM vendors v
    ORDER BY v.name
");
$vendorLedger = $vendorsStmt->fetchAll();

// --- 4. EMPLOYEE SALARY TRACKING + SALES-OFFICER RECEIVABLES ---
// Receivable for an officer = invoices bearing their name - payments received through them.
$employeesStmt = $conn->query("
    SELECT e.id, e.name, e.role, e.salary,
           IFNULL((SELECT SUM(amount) FROM transactions WHERE employee_id = e.id AND category = 'Salaries' AND type = 'WITHDRAWAL'), 0) as paid_to_date,
           IFNULL((SELECT SUM(total_amount) FROM invoices WHERE employee_id = e.id), 0) as sales_generated,
           IFNULL((SELECT SUM(amount_received) FROM payment_receipts WHERE employee_id = e.id), 0) as collected
    FROM employees e
    ORDER BY e.name
");
$employeeLedger = $employeesStmt->fetchAll();

// --- 5. PRODUCT PROFITABILITY ---
$productPerfStmt = $conn->query("
    SELECT p.name, 
           SUM(ii.quantity) as total_sold,
           SUM(ii.quantity * ii.price) as revenue,
           SUM(ii.quantity * pur.purchase_price) as cost,
           (SUM(ii.quantity * ii.price) - SUM(ii.quantity * pur.purchase_price)) as profit
    FROM products p
    JOIN invoice_items ii ON p.id = ii.product_id
    JOIN purchasing pur ON ii.purchasing_id = pur.id
    GROUP BY p.id
    ORDER BY profit DESC
");
$productPerformance = $productPerfStmt->fetchAll();

$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>ERP Ledger - AGROVISE</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=ed3">
    <style>
        .report-section { margin-bottom: 40px; }
        .tab-btn { padding: 10px 20px; cursor: pointer; border: none; background: #f1f1f1; font-weight: 600; border-radius: 8px 8px 0 0; margin-right: 5px; transition: 0.3s; }
        .tab-btn.active { background: var(--primary-green); color: white; }
        .tab-content { display: none; padding: 25px; border: 1px solid #eee; background: white; border-radius: 0 8px 8px 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .tab-content.active { display: block; }
        .status-positive { color: var(--primary-green); font-weight: bold; }
        .status-negative { color: var(--danger); font-weight: bold; }
        .tabs { border-bottom: 2px solid #eee; margin-bottom: 0px; }
        
        @media print {
            .admin-sidebar, .admin-header, .tabs, .tab-btn, .btn, .dashboard-stat:not(.print-summary) {
                display: none !important;
            }
            .admin-main { margin: 0 !important; padding: 0 !important; width: 100% !important; }
            .tab-content { display: block !important; border: none !important; box-shadow: none !important; padding: 0 !important; }
            h2 { border-bottom: 2px solid #558b2f; padding-bottom: 5px; margin-top: 30px; }
            .data-table { border-collapse: collapse; width: 100%; font-size: 10pt; }
            .data-table th, .data-table td { border: 1px solid #ddd; padding: 8px; }
            .data-table th { background-color: #f2f2f2 !important; -webkit-print-color-adjust: exact; }
            .dashboard-stat.print-summary {
                display: grid !important; grid-template-columns: repeat(4, 1fr) !important;
                border: 1px solid #eee !important;
            }
        }
    </style>
</head>
<body class="admin-body">
    <div class="admin-wrapper">
        <?php require __DIR__ . '/../includes/admin-sidebar.php'; ?>
        
        <main class="admin-main">
            <div class="admin-header"><h1><i class="fas fa-book"></i> Comprehensive ERP Ledger</h1></div>

            <!-- EXECUTIVE CARDS -->
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px;">
                <div class="dashboard-stat" style="border-left: 5px solid #2e7d32;">
                    <h3>Total Sales</h3>
                    <div class="stat-number">Rs. <?php echo number_format($totalSales, 0); ?></div>
                </div>
                <div class="dashboard-stat" style="border-left: 5px solid #f44336;">
                    <h3>Total COGS</h3>
                    <div class="stat-number">Rs. <?php echo number_format($totalPurchases, 0); ?></div>
                </div>
                <div class="dashboard-stat" style="border-left: 5px solid #ff9800;">
                    <h3>Total Expenses</h3>
                    <div class="stat-number">Rs. <?php echo number_format($operatingExpenses, 0); ?></div>
                </div>
                <div class="dashboard-stat" style="background: <?php echo $netProfit >= 0 ? 'var(--dark-green)' : '#c62828'; ?>; color: white;">
                    <h3 style="color: rgba(255,255,255,0.8);">Net Profit</h3>
                    <div class="stat-number">Rs. <?php echo number_format($netProfit, 0); ?></div>
                </div>
            </div>

            <!-- TABBED REPORTS -->
            <div class="report-section">
                <div class="tabs">
                    <button class="tab-btn active" onclick="openTab(event, 'clients')">Client Ledger</button>
                    <button class="tab-btn" onclick="openTab(event, 'vendors')">Vendor Ledger</button>
                    <button class="tab-btn" onclick="openTab(event, 'employees')">Sales Officers</button>
                    <button class="tab-btn" onclick="openTab(event, 'products')">Product Profit</button>
                    <button class="tab-btn" onclick="openTab(event, 'exports')">Exports</button>
                </div>

                <!-- CLIENTS -->
                <div id="clients" class="tab-content active">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                        <h2>Client Balances (Receivables)</h2>
                        <button onclick="window.print()" class="btn btn-outline btn-sm"><i class="fas fa-file-pdf"></i> Generate PDF</button>
                    </div>
                    <table class="data-table">
                        <thead>
                            <tr><th>Client Name</th><th>Area</th><th>Total Invoiced</th><th>Total Paid</th><th>Balance Due</th><th>Actions</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach($clientLedger as $row): $bal = $row['total_invoiced'] - $row['total_paid']; ?>
                            <tr>
                                <td><strong><?php echo sanitize($row['name']); ?></strong></td>
                                <td><?php echo sanitize($row['area']); ?></td>
                                <td>Rs. <?php echo number_format($row['total_invoiced'], 2); ?></td>
                                <td>Rs. <?php echo number_format($row['total_paid'], 2); ?></td>
                                <td class="<?php echo $bal > 1 ? 'status-negative' : ''; ?>">Rs. <?php echo number_format($bal, 2); ?></td>
                                <td><a href="detailed-report.php?type=client&id=<?php echo $row['id']; ?>" class="btn-icon edit" title="Full Statement"><i class="fas fa-file-alt"></i></a></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- VENDORS -->
                <div id="vendors" class="tab-content">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                        <h2>Vendor Balances (Payables)</h2>
                        <button onclick="window.print()" class="btn btn-outline btn-sm"><i class="fas fa-file-pdf"></i> Generate PDF</button>
                    </div>
                    <table class="data-table">
                        <thead>
                            <tr><th>Vendor Name</th><th>Total Purchases</th><th>Total Paid</th><th>Remaining Payable</th><th>Actions</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach($vendorLedger as $row): $bal = $row['total_purchased'] - $row['total_paid']; ?>
                            <tr>
                                <td><strong><?php echo sanitize($row['name']); ?></strong></td>
                                <td>Rs. <?php echo number_format($row['total_purchased'], 2); ?></td>
                                <td>Rs. <?php echo number_format($row['total_paid'], 2); ?></td>
                                <td class="<?php echo $bal > 1 ? 'status-negative' : ''; ?>">Rs. <?php echo number_format($bal, 2); ?></td>
                                <td><a href="detailed-report.php?type=vendor&id=<?php echo $row['id']; ?>" class="btn-icon edit" title="Full Statement"><i class="fas fa-file-alt"></i></a></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- EMPLOYEES -->
                <div id="employees" class="tab-content">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                        <h2>Sales Officers — Performance, Salary &amp; Receivables</h2>
                        <button onclick="window.print()" class="btn btn-outline btn-sm"><i class="fas fa-file-pdf"></i> Generate PDF</button>
                    </div>
                    <p style="color:#666; font-size:0.85rem; margin-bottom:12px;"><i class="fas fa-info-circle"></i> Receivable = value of invoices carrying the officer's name, minus payments collected through that officer from their dealers.</p>
                    <table class="data-table">
                        <thead>
                            <tr><th>Officer</th><th>Role</th><th>Monthly Salary</th><th>Salary Paid</th><th>Sales (Invoiced)</th><th>Collected</th><th>Outstanding Receivable</th><th>Actions</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach($employeeLedger as $row): $outstanding = $row['sales_generated'] - $row['collected']; ?>
                            <tr>
                                <td><strong><?php echo sanitize($row['name']); ?></strong></td>
                                <td><?php echo sanitize($row['role']); ?></td>
                                <td>Rs. <?php echo number_format($row['salary'], 2); ?></td>
                                <td>Rs. <?php echo number_format($row['paid_to_date'], 2); ?></td>
                                <td class="status-positive">Rs. <?php echo number_format($row['sales_generated'], 2); ?></td>
                                <td>Rs. <?php echo number_format($row['collected'], 2); ?></td>
                                <td class="<?php echo $outstanding > 1 ? 'status-negative' : ''; ?>">Rs. <?php echo number_format($outstanding, 2); ?></td>
                                <td><a href="detailed-report.php?type=employee&id=<?php echo $row['id']; ?>" class="btn-icon edit" title="Full Statement"><i class="fas fa-file-alt"></i></a></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- PRODUCTS -->
                <div id="products" class="tab-content">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                        <h2>Product Profitability Analysis</h2>
                        <button onclick="window.print()" class="btn btn-outline btn-sm"><i class="fas fa-file-pdf"></i> Generate PDF</button>
                    </div>
                    <table class="data-table">
                        <thead>
                            <tr><th>Product</th><th>Sold Qty</th><th>Revenue</th><th>Cost</th><th>Net Profit</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach($productPerformance as $row): ?>
                            <tr>
                                <td><strong><?php echo sanitize($row['name']); ?></strong></td>
                                <td><?php echo number_format($row['total_sold'], 2); ?></td>
                                <td>Rs. <?php echo number_format($row['revenue'], 2); ?></td>
                                <td>Rs. <?php echo number_format($row['cost'], 2); ?></td>
                                <td class="<?php echo $row['profit'] >= 0 ? 'status-positive' : 'status-negative'; ?>">
                                    Rs. <?php echo number_format($row['profit'], 2); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- EXPORTS -->
                <div id="exports" class="tab-content">
                    <h2 style="margin-bottom: 6px;">Detailed Ledger Exports</h2>
                    <p style="color:#666; font-size:0.85rem; margin-bottom:24px;">Each report downloads as a detailed CSV (opens in Excel). Optional year filter applies where relevant.</p>

                    <div style="display:flex; gap:12px; align-items:center; margin-bottom:26px; flex-wrap:wrap;">
                        <label class="form-label" style="margin:0;">Year filter (optional):</label>
                        <select id="exportYear" class="form-select" style="max-width:160px;">
                            <option value="">All years</option>
                            <?php for ($y = intval(date('Y')); $y >= intval(date('Y')) - 6; $y--): ?>
                            <option value="<?php echo $y; ?>" <?php echo $y == intval(date('Y')) ? 'selected' : ''; ?>><?php echo $y; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <?php
                    $exportGroups = [
                        'Profit &amp; Loss' => [
                            ['monthly_pl', 'Monthly Profit / Loss', 'fa-chart-line', true],
                            ['yearly_pl', 'Yearly Profit / Loss', 'fa-calendar', false],
                            ['area_pl', 'Profit / Loss by Area', 'fa-map-location-dot', true],
                        ],
                        'Cost &amp; Sales' => [
                            ['cost', 'Cost of Goods (COGS) by Product', 'fa-tags', true],
                            ['products_sold', 'Products Sold (line detail)', 'fa-cart-shopping', true],
                            ['bulk_bought', 'Products Bought in Bulk', 'fa-truck-ramp-box', true],
                            ['buy_sell_comparison', 'Bought vs Sold Comparison', 'fa-scale-balanced', true],
                        ],
                        'Money Out' => [
                            ['expenses', 'Expenses (total &amp; individual)', 'fa-money-bill-wave', true],
                            ['salaries', 'Salaries (total &amp; individual)', 'fa-users', true],
                        ],
                        'Receivables &amp; Stock' => [
                            ['client_ledger', 'Client Ledger (per-client balances)', 'fa-user-group', false],
                            ['officer_receivables', 'Sales Officer Receivables', 'fa-user-tie', false],
                            ['stock', 'Stock Report (batch, expiry, vendor)', 'fa-boxes-stacked', false],
                        ],
                    ];
                    foreach ($exportGroups as $group => $reports): ?>
                    <h3 style="font-size:0.95rem; color:var(--primary-green); margin:18px 0 12px;"><?php echo $group; ?></h3>
                    <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(280px, 1fr)); gap:12px; margin-bottom:20px;">
                        <?php foreach ($reports as $rep): ?>
                        <a href="#" class="export-link" data-report="<?php echo $rep[0]; ?>" data-year="<?php echo $rep[3] ? '1' : '0'; ?>"
                           style="display:flex; align-items:center; gap:14px; padding:16px; border:1px solid #e0e6dd; border-radius:10px; background:#fff; text-decoration:none; color:#333; transition:0.2s;">
                            <i class="fas <?php echo $rep[2]; ?>" style="color:var(--primary-green); font-size:1.2rem; width:24px; text-align:center;"></i>
                            <span style="flex:1;"><?php echo $rep[1]; ?></span>
                            <i class="fas fa-download" style="color:#999;"></i>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    <?php endforeach; ?>
                </div>

            </div>
        </main>
    </div>

    <script>
        function openTab(evt, tabName) {
            var i, tabcontent, tablinks;
            tabcontent = document.getElementsByClassName("tab-content");
            for (i = 0; i < tabcontent.length; i++) { tabcontent[i].style.display = "none"; }
            tablinks = document.getElementsByClassName("tab-btn");
            for (i = 0; i < tablinks.length; i++) { tablinks[i].className = tablinks[i].className.replace(" active", ""); }
            document.getElementById(tabName).style.display = "block";
            evt.currentTarget.className += " active";
        }

        // Export links -> ledger-export.php with the selected year
        document.querySelectorAll('.export-link').forEach(function (link) {
            link.addEventListener('click', function (e) {
                e.preventDefault();
                var report = link.dataset.report;
                var url = 'ledger-export.php?report=' + encodeURIComponent(report);
                if (link.dataset.year === '1') {
                    var yr = document.getElementById('exportYear').value;
                    if (yr) url += '&year=' + encodeURIComponent(yr);
                }
                window.location.href = url;
            });
            link.addEventListener('mouseenter', function () { link.style.borderColor = 'var(--primary-green)'; link.style.boxShadow = '0 4px 12px rgba(0,0,0,0.06)'; });
            link.addEventListener('mouseleave', function () { link.style.borderColor = '#e0e6dd'; link.style.boxShadow = 'none'; });
        });
    </script>
</body>
</html>
