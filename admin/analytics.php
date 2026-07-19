<?php
/**
 * AGROVISE - Business Analytics Dashboard
 */
require_once '../includes/db.php';
require_once '../includes/functions.php';
requirePermission('ledgers');

$conn = getDBConnection();

// 1. DATA FOR AREA PROFITABILITY
$areaStmt = $conn->query("
    SELECT c.area, 
           SUM(ii.quantity * (ii.price - pur.purchase_price)) as net_profit
    FROM clients c
    JOIN invoices i ON c.id = i.client_id
    JOIN invoice_items ii ON i.id = ii.invoice_id
    JOIN purchasing pur ON ii.purchasing_id = pur.id
    GROUP BY c.area
    ORDER BY net_profit DESC
");
$areaProfitData = $areaStmt->fetchAll();

// 2. TOP 5 PROFITABLE PRODUCTS
$topProfitStmt = $conn->query("
    SELECT p.name, 
           SUM(ii.quantity * (ii.price - pur.purchase_price)) as profit
    FROM products p
    JOIN invoice_items ii ON p.id = ii.product_id
    JOIN purchasing pur ON ii.purchasing_id = pur.id
    GROUP BY p.id
    ORDER BY profit DESC LIMIT 5
");
$topProfitable = $topProfitStmt->fetchAll();

// 3. TOP 5 LOSING PRODUCTS (Lowest Profit)
$topLossStmt = $conn->query("
    SELECT p.name, 
           SUM(ii.quantity * (ii.price - pur.purchase_price)) as profit
    FROM products p
    JOIN invoice_items ii ON p.id = ii.product_id
    JOIN purchasing pur ON ii.purchasing_id = pur.id
    GROUP BY p.id
    ORDER BY profit ASC LIMIT 5
");
$topLosing = $topLossStmt->fetchAll();

// 4. MONTHLY TRENDS (Last 6 Months)
$trendsStmt = $conn->query("
    SELECT DATE_FORMAT(date, '%b %Y') as month,
           SUM(total_amount) as sales,
           (SELECT SUM(total_price) FROM purchasing WHERE DATE_FORMAT(date_added, '%b %Y') = DATE_FORMAT(i.date, '%b %Y')) as purchases
    FROM invoices i
    GROUP BY month
    ORDER BY i.date ASC
    LIMIT 6
");
$monthlyTrends = $trendsStmt->fetchAll();

// 5. VENDOR OUTSTANDING
$vendorHealthStmt = $conn->query("
    SELECT v.name,
           (IFNULL((SELECT SUM(total_price) FROM purchasing WHERE vendor_id = v.id), 0) - 
            IFNULL((SELECT SUM(amount) FROM transactions WHERE vendor_id = v.id AND category = 'Purchasing' AND type = 'WITHDRAWAL'), 0)) as outstanding
    FROM vendors v
    HAVING outstanding > 0
");
$vendorHealth = $vendorHealthStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Analytics Dashboard - AGROVISE</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=ed3">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .charts-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-top: 20px; }
        .chart-card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.05); }
        .chart-card h2 { margin-top: 0; margin-bottom: 20px; font-size: 1.1em; color: #555; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        .full-width { grid-column: span 2; }
    </style>
</head>
<body class="admin-body">
    <div class="admin-wrapper">
        <?php require __DIR__ . '/../includes/admin-sidebar.php'; ?>
        
        <main class="admin-main">
            <div class="admin-header"><h1><i class="fas fa-chart-pie"></i> Business Analytics</h1></div>

            <div class="charts-grid">
                <!-- 1. MONTHLY TRENDS -->
                <div class="chart-card full-width">
                    <h2>Monthly Sales vs Purchases</h2>
                    <canvas id="trendsChart" height="100"></canvas>
                </div>

                <!-- 2. AREA PROFITABILITY -->
                <div class="chart-card">
                    <h2>Net Profit by Area</h2>
                    <canvas id="areaChart"></canvas>
                </div>

                <!-- 3. VENDOR PAYABLES -->
                <div class="chart-card">
                    <h2>Outstanding Vendor Payments</h2>
                    <canvas id="vendorChart"></canvas>
                </div>

                <!-- 4. TOP WINNERS -->
                <div class="chart-card">
                    <h2>Top 5 Profitable Products</h2>
                    <canvas id="winChart"></canvas>
                </div>

                <!-- 5. TOP LOSERS -->
                <div class="chart-card">
                    <h2>Top 5 Losing Products</h2>
                    <canvas id="lossChart"></canvas>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Data Preparation
        const areaLabels = <?php echo json_encode(array_column($areaProfitData, 'area')); ?>;
        const areaData = <?php echo json_encode(array_column($areaProfitData, 'net_profit')); ?>;

        const trendMonths = <?php echo json_encode(array_column($monthlyTrends, 'month')); ?>;
        const trendSales = <?php echo json_encode(array_column($monthlyTrends, 'sales')); ?>;
        const trendPurchases = <?php echo json_encode(array_column($monthlyTrends, 'purchases')); ?>;

        const vendorLabels = <?php echo json_encode(array_column($vendorHealth, 'name')); ?>;
        const vendorData = <?php echo json_encode(array_column($vendorHealth, 'outstanding')); ?>;

        const winLabels = <?php echo json_encode(array_column($topProfitable, 'name')); ?>;
        const winData = <?php echo json_encode(array_column($topProfitable, 'profit')); ?>;

        const lossLabels = <?php echo json_encode(array_column($topLosing, 'name')); ?>;
        const lossData = <?php echo json_encode(array_column($topLosing, 'profit')); ?>;

        // Charts Configuration
        const commonOptions = { responsive: true, plugins: { legend: { position: 'bottom' } } };

        // 1. Trends
        new Chart(document.getElementById('trendsChart'), {
            type: 'line',
            data: {
                labels: trendMonths,
                datasets: [
                    { label: 'Sales', data: trendSales, borderColor: '#2e7d32', backgroundColor: 'rgba(46, 125, 50, 0.1)', fill: true, tension: 0.4 },
                    { label: 'Purchases', data: trendPurchases, borderColor: '#f44336', backgroundColor: 'rgba(244, 67, 54, 0.1)', fill: true, tension: 0.4 }
                ]
            },
            options: commonOptions
        });

        // 2. Area
        new Chart(document.getElementById('areaChart'), {
            type: 'bar',
            data: {
                labels: areaLabels,
                datasets: [{ label: 'Net Profit (Rs.)', data: areaData, backgroundColor: '#4caf50' }]
            },
            options: commonOptions
        });

        // 3. Vendor
        new Chart(document.getElementById('vendorChart'), {
            type: 'doughnut',
            data: {
                labels: vendorLabels,
                datasets: [{ data: vendorData, backgroundColor: ['#ff9800', '#2196f3', '#9c27b0', '#f44336', '#4caf50'] }]
            },
            options: commonOptions
        });

        // 4. Winners
        new Chart(document.getElementById('winChart'), {
            type: 'polarArea',
            data: {
                labels: winLabels,
                datasets: [{ data: winData, backgroundColor: 'rgba(46, 125, 50, 0.5)' }]
            },
            options: commonOptions
        });

        // 5. Losers
        new Chart(document.getElementById('lossChart'), {
            type: 'bar',
            data: {
                labels: lossLabels,
                datasets: [{ label: 'Profit/Loss (Rs.)', data: lossData, backgroundColor: '#e91e63' }]
            },
            options: { ...commonOptions, indexAxis: 'y' }
        });
    </script>
</body>
</html>
