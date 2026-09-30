<?php
/**
 * AGROVISE - Website Traffic
 * Who visits the public site, what they browse, and which products they open.
 * Data comes from site_visits + product_views (written by includes/tracking.php).
 */
require_once '../includes/db.php';
require_once '../includes/functions.php';
requirePermission('traffic');

$conn = getDBConnection();

// --- Date range (defaults to the last 30 days) ---
$to   = trim($_GET['to'] ?? '') ?: date('Y-m-d');
$from = trim($_GET['from'] ?? '') ?: date('Y-m-d', strtotime('-29 days'));
if (strtotime($from) === false) $from = date('Y-m-d', strtotime('-29 days'));
if (strtotime($to) === false)   $to   = date('Y-m-d');
if (strtotime($from) > strtotime($to)) { [$from, $to] = [$to, $from]; }

// Inclusive of the whole end day
$rangeSql    = " created_at >= ? AND created_at < DATE_ADD(?, INTERVAL 1 DAY) ";
$rangeParams = [$from, $to];

$q = function ($sql, $params = []) use ($conn) {
    $st = $conn->prepare($sql);
    $st->execute($params);
    return $st;
};

// --- One visitor's journey (drill-down) ---
$visitorId = trim($_GET['visitor'] ?? '');
$journey = [];
$journeyMeta = null;
if ($visitorId !== '') {
    $journey = $q("
        SELECT created_at, 'PAGE' AS kind, page_type AS label, category, page_url AS detail
        FROM site_visits WHERE visitor_id = ?
        UNION ALL
        SELECT pv.created_at, 'PRODUCT' AS kind, p.name AS label, pv.category, pv.source AS detail
        FROM product_views pv LEFT JOIN products p ON p.id = pv.product_id
        WHERE pv.visitor_id = ?
        ORDER BY created_at DESC
        LIMIT 200", [$visitorId, $visitorId])->fetchAll();

    $journeyMeta = $q("
        SELECT MIN(created_at) AS first_seen, MAX(created_at) AS last_seen,
               COUNT(*) AS views, COUNT(DISTINCT session_id) AS sessions,
               MAX(device) AS device, MAX(browser) AS browser, MAX(ip_address) AS ip
        FROM site_visits WHERE visitor_id = ?", [$visitorId])->fetch();
}

// --- KPIs ---
$pageViews    = intval($q("SELECT COUNT(*) FROM site_visits WHERE $rangeSql", $rangeParams)->fetchColumn());
$uniqueVisits = intval($q("SELECT COUNT(DISTINCT visitor_id) FROM site_visits WHERE $rangeSql", $rangeParams)->fetchColumn());
$sessions     = intval($q("SELECT COUNT(DISTINCT session_id) FROM site_visits WHERE $rangeSql", $rangeParams)->fetchColumn());
$productViews = intval($q("SELECT COUNT(*) FROM product_views WHERE $rangeSql", $rangeParams)->fetchColumn());
$today        = intval($q("SELECT COUNT(*) FROM site_visits WHERE DATE(created_at) = CURDATE()")->fetchColumn());
$pagesPerVisit = $sessions > 0 ? round($pageViews / $sessions, 1) : 0;

// --- Daily series for the chart (zero-filled so gaps show as gaps) ---
$rows = $q("SELECT DATE(created_at) AS d, COUNT(*) AS views, COUNT(DISTINCT visitor_id) AS visitors
            FROM site_visits WHERE $rangeSql GROUP BY DATE(created_at)", $rangeParams)->fetchAll();
$byDay = [];
foreach ($rows as $r) $byDay[$r['d']] = $r;

$chartLabels = $chartViews = $chartVisitors = [];
for ($d = strtotime($from); $d <= strtotime($to); $d = strtotime('+1 day', $d)) {
    $key = date('Y-m-d', $d);
    $chartLabels[]   = date('d M', $d);
    $chartViews[]    = intval($byDay[$key]['views'] ?? 0);
    $chartVisitors[] = intval($byDay[$key]['visitors'] ?? 0);
}

// --- What they browse ---
$topCategories = $q("
    SELECT category, COUNT(*) AS views, COUNT(DISTINCT visitor_id) AS visitors
    FROM site_visits WHERE $rangeSql AND category IS NOT NULL AND category <> ''
    GROUP BY category ORDER BY views DESC", $rangeParams)->fetchAll();

$topProducts = $q("
    SELECT p.id, p.name, p.category, p.image,
           COUNT(*) AS views, COUNT(DISTINCT pv.visitor_id) AS visitors
    FROM product_views pv
    JOIN products p ON p.id = pv.product_id
    WHERE pv.created_at >= ? AND pv.created_at < DATE_ADD(?, INTERVAL 1 DAY)
    GROUP BY p.id, p.name, p.category, p.image
    ORDER BY views DESC LIMIT 12", $rangeParams)->fetchAll();

// Product interest per category — "what type of product he visits"
$productTypeMix = $q("
    SELECT category, COUNT(*) AS views
    FROM product_views
    WHERE created_at >= ? AND created_at < DATE_ADD(?, INTERVAL 1 DAY) AND category IS NOT NULL
    GROUP BY category ORDER BY views DESC", $rangeParams)->fetchAll();

$devices  = $q("SELECT device, COUNT(*) AS views FROM site_visits WHERE $rangeSql GROUP BY device ORDER BY views DESC", $rangeParams)->fetchAll();
$browsers = $q("SELECT browser, COUNT(*) AS views FROM site_visits WHERE $rangeSql GROUP BY browser ORDER BY views DESC LIMIT 6", $rangeParams)->fetchAll();
$pages    = $q("SELECT page_type, COUNT(*) AS views FROM site_visits WHERE $rangeSql GROUP BY page_type ORDER BY views DESC", $rangeParams)->fetchAll();

$referrers = $q("
    SELECT referrer, COUNT(*) AS views FROM site_visits
    WHERE $rangeSql AND referrer IS NOT NULL AND referrer <> ''
    GROUP BY referrer ORDER BY views DESC LIMIT 8", $rangeParams)->fetchAll();

// --- Visitor list (each row drills into that visitor's journey) ---
$visitors = $q("
    SELECT sv.visitor_id,
           COUNT(*) AS views,
           COUNT(DISTINCT sv.session_id) AS sessions,
           MIN(sv.created_at) AS first_seen,
           MAX(sv.created_at) AS last_seen,
           MAX(sv.device) AS device,
           MAX(sv.browser) AS browser,
           MAX(sv.ip_address) AS ip,
           GROUP_CONCAT(DISTINCT sv.category ORDER BY sv.category SEPARATOR ', ') AS categories,
           (SELECT COUNT(*) FROM product_views pv WHERE pv.visitor_id = sv.visitor_id) AS product_views
    FROM site_visits sv
    WHERE $rangeSql
    GROUP BY sv.visitor_id
    ORDER BY last_seen DESC
    LIMIT 100", $rangeParams)->fetchAll();

$maxCatViews = 0;
foreach ($topCategories as $c) $maxCatViews = max($maxCatViews, intval($c['views']));
$maxProdViews = 0;
foreach ($topProducts as $p) $maxProdViews = max($maxProdViews, intval($p['views']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Website Traffic - AGROVISE Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=ed3">
    <script src="../assets/js/chart.umd.min.js"></script>
    <style>
        .tf-kpis {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            border: 1px solid rgba(15, 26, 14, 0.14); background: #fff; margin-bottom: 24px;
        }
        .tf-kpi {
            padding: 24px; display: flex; align-items: center; gap: 15px;
            border-right: 1px solid rgba(15, 26, 14, 0.14);
        }
        .tf-kpi:last-child { border-right: 0; }
        .tf-kpi .icon {
            width: 48px; height: 48px; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center;
            background: #0f1a0e; color: #c2a04f; font-size: 1rem;
        }
        .tf-kpi h3 {
            font-family: 'Cormorant Garamond', Georgia, serif;
            font-weight: 600; font-size: 1.6rem; margin: 0; color: #23291f;
            font-variant-numeric: tabular-nums;
        }
        .tf-kpi p {
            margin: 3px 0 0; color: #8a8f83; font-size: 0.6rem;
            font-weight: 500; text-transform: uppercase; letter-spacing: 0.24em;
        }
        .tf-panel { background: #fff; border: 1px solid rgba(15, 26, 14, 0.14); padding: 24px; margin-bottom: 24px; }
        .tf-panel h2 {
            font-size: 0.7rem; font-weight: 500; letter-spacing: 0.3em; text-transform: uppercase;
            margin: 0 0 20px; color: #23291f; display: flex; align-items: center; gap: 12px;
        }
        .tf-panel h2 i { color: #c2a04f; font-size: 0.8rem; }
        .tf-grid { display: grid; grid-template-columns: 1.5fr 1fr; gap: 24px; }
        .tf-grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 24px; }
        @media (max-width: 1100px) { .tf-grid, .tf-grid-3 { grid-template-columns: 1fr; } }
        .chart-wrap { position: relative; height: 300px; }
        .chart-wrap.sm { height: 240px; }

        /* Ranked bars — category / product interest */
        .tf-bars { list-style: none; margin: 0; padding: 0; }
        .tf-bars li { padding: 11px 0; border-bottom: 1px solid rgba(15, 26, 14, 0.1); }
        .tf-bars li:last-child { border-bottom: 0; }
        .tf-bar-top { display: flex; justify-content: space-between; align-items: baseline; gap: 12px; margin-bottom: 7px; }
        .tf-bar-top strong { font-size: 0.88rem; color: #23291f; font-weight: 600; }
        .tf-bar-top .muted { color: #8a8f83; font-size: 0.72rem; white-space: nowrap; }
        .tf-bar-track { height: 5px; background: rgba(15, 26, 14, 0.08); }
        .tf-bar-fill { height: 100%; background: #c2a04f; }

        .tf-empty { text-align: center; color: #8a8f83; padding: 34px 10px; font-size: 0.86rem; }
        .filters-bar { display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap; }
        .filters-bar .form-group { margin: 0; }
        .quick-ranges { display: flex; gap: 8px; margin-left: auto; flex-wrap: wrap; }
        .badge-soft {
            display: inline-block; padding: 3px 9px; font-size: 0.66rem; font-weight: 600;
            letter-spacing: 0.08em; background: rgba(194, 160, 79, 0.16); color: #7a6320;
        }
        .vid-chip { font-family: 'Courier New', monospace; font-size: 0.74rem; color: #5c6356; }
        .journey-list { list-style: none; margin: 0; padding: 0; }
        .journey-list li {
            display: flex; gap: 14px; align-items: flex-start;
            padding: 12px 0; border-bottom: 1px solid rgba(15, 26, 14, 0.1);
        }
        .journey-list li:last-child { border-bottom: 0; }
        .journey-list .j-icon {
            width: 30px; height: 30px; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.7rem; background: #f2efe6; color: #0f1a0e;
        }
        .journey-list .j-icon.prod { background: #0f1a0e; color: #c2a04f; }
        .journey-list .j-when { margin-left: auto; color: #8a8f83; font-size: 0.74rem; white-space: nowrap; }
        .journey-list .j-what strong { display: block; font-size: 0.88rem; color: #23291f; }
        .journey-list .j-what small { color: #8a8f83; }
    </style>
</head>
<body class="admin-body">
    <div class="admin-wrapper">
        <?php require __DIR__ . '/../includes/admin-sidebar.php'; ?>

        <main class="admin-main">
            <div class="admin-header">
                <h1><i class="fas fa-chart-area"></i> Website Traffic</h1>
            </div>

            <!-- Range filter -->
            <div class="tf-panel" style="padding: 20px 24px;">
                <form method="GET" class="filters-bar">
                    <div class="form-group">
                        <label class="form-label">From</label>
                        <input type="date" name="from" class="form-input" value="<?php echo sanitize($from); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">To</label>
                        <input type="date" name="to" class="form-input" value="<?php echo sanitize($to); ?>">
                    </div>
                    <button type="submit" class="btn btn-secondary btn-sm"><i class="fas fa-filter"></i> Apply</button>
                    <div class="quick-ranges">
                        <a href="?from=<?php echo date('Y-m-d'); ?>&to=<?php echo date('Y-m-d'); ?>" class="btn btn-outline btn-sm">Today</a>
                        <a href="?from=<?php echo date('Y-m-d', strtotime('-6 days')); ?>&to=<?php echo date('Y-m-d'); ?>" class="btn btn-outline btn-sm">7 Days</a>
                        <a href="?from=<?php echo date('Y-m-d', strtotime('-29 days')); ?>&to=<?php echo date('Y-m-d'); ?>" class="btn btn-outline btn-sm">30 Days</a>
                        <a href="?from=<?php echo date('Y-m-d', strtotime('-364 days')); ?>&to=<?php echo date('Y-m-d'); ?>" class="btn btn-outline btn-sm">1 Year</a>
                    </div>
                </form>
            </div>

            <!-- KPIs -->
            <div class="tf-kpis">
                <div class="tf-kpi">
                    <div class="icon"><i class="fas fa-eye"></i></div>
                    <div><h3><?php echo number_format($pageViews); ?></h3><p>Page Views</p></div>
                </div>
                <div class="tf-kpi">
                    <div class="icon"><i class="fas fa-user-group"></i></div>
                    <div><h3><?php echo number_format($uniqueVisits); ?></h3><p>Unique Visitors</p></div>
                </div>
                <div class="tf-kpi">
                    <div class="icon"><i class="fas fa-box-open"></i></div>
                    <div><h3><?php echo number_format($productViews); ?></h3><p>Product Views</p></div>
                </div>
                <div class="tf-kpi">
                    <div class="icon"><i class="fas fa-layer-group"></i></div>
                    <div><h3><?php echo $pagesPerVisit; ?></h3><p>Pages / Visit</p></div>
                </div>
                <div class="tf-kpi">
                    <div class="icon"><i class="fas fa-calendar-day"></i></div>
                    <div><h3><?php echo number_format($today); ?></h3><p>Views Today</p></div>
                </div>
            </div>

            <?php if ($journeyMeta && $journeyMeta['views']): ?>
            <!-- Single-visitor drill-down -->
            <div class="tf-panel">
                <h2 style="justify-content: space-between;">
                    <span><i class="fas fa-route"></i> Visitor Journey &nbsp;<span class="vid-chip"><?php echo sanitize(substr($visitorId, 0, 12)); ?>&hellip;</span></span>
                    <a href="traffic.php?from=<?php echo sanitize($from); ?>&to=<?php echo sanitize($to); ?>" class="btn btn-outline btn-sm"><i class="fas fa-times"></i> Close</a>
                </h2>
                <p style="color:#5c6356; font-size:0.86rem; margin: -8px 0 18px;">
                    <?php echo number_format($journeyMeta['views']); ?> page views across
                    <?php echo number_format($journeyMeta['sessions']); ?> session(s) &middot;
                    <?php echo sanitize($journeyMeta['device']); ?> / <?php echo sanitize($journeyMeta['browser']); ?> &middot;
                    IP <?php echo sanitize($journeyMeta['ip']); ?> &middot;
                    first seen <?php echo date('d M Y, h:i A', strtotime($journeyMeta['first_seen'])); ?>
                </p>
                <ul class="journey-list">
                    <?php foreach ($journey as $j): ?>
                    <li>
                        <div class="j-icon <?php echo $j['kind'] === 'PRODUCT' ? 'prod' : ''; ?>">
                            <i class="fas <?php echo $j['kind'] === 'PRODUCT' ? 'fa-box' : 'fa-file'; ?>"></i>
                        </div>
                        <div class="j-what">
                            <?php if ($j['kind'] === 'PRODUCT'): ?>
                                <strong>Opened product: <?php echo sanitize($j['label'] ?: 'Deleted product'); ?></strong>
                                <small><?php echo sanitize(getCategoryName($j['category'])); ?></small>
                            <?php else: ?>
                                <strong><?php echo $j['label'] === 'home' ? 'Home page' : sanitize(getCategoryName($j['category'] ?: $j['label'])) . ' page'; ?></strong>
                                <small><?php echo sanitize($j['detail']); ?></small>
                            <?php endif; ?>
                        </div>
                        <span class="j-when"><?php echo date('d M Y, h:i A', strtotime($j['created_at'])); ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <!-- Traffic over time -->
            <div class="tf-panel">
                <h2><i class="fas fa-chart-line"></i> Traffic Over Time</h2>
                <?php if ($pageViews === 0): ?>
                    <div class="tf-empty">No visits recorded in this range yet.</div>
                <?php else: ?>
                    <div class="chart-wrap"><canvas id="trafficChart"></canvas></div>
                <?php endif; ?>
            </div>

            <div class="tf-grid">
                <!-- What they browse -->
                <div class="tf-panel">
                    <h2><i class="fas fa-tags"></i> Categories Browsed</h2>
                    <?php if (empty($topCategories)): ?>
                        <div class="tf-empty">No category pages visited yet.</div>
                    <?php else: ?>
                    <ul class="tf-bars">
                        <?php foreach ($topCategories as $c): ?>
                        <li>
                            <div class="tf-bar-top">
                                <strong><?php echo sanitize(getCategoryName($c['category'])); ?></strong>
                                <span class="muted"><?php echo number_format($c['views']); ?> views &middot; <?php echo number_format($c['visitors']); ?> visitors</span>
                            </div>
                            <div class="tf-bar-track">
                                <div class="tf-bar-fill" style="width: <?php echo $maxCatViews ? round($c['views'] / $maxCatViews * 100) : 0; ?>%;"></div>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </div>

                <!-- Product type mix -->
                <div class="tf-panel">
                    <h2><i class="fas fa-chart-pie"></i> Product Interest by Type</h2>
                    <?php if (empty($productTypeMix)): ?>
                        <div class="tf-empty">No products opened yet.</div>
                    <?php else: ?>
                        <div class="chart-wrap sm"><canvas id="typeChart"></canvas></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Most viewed products -->
            <div class="tf-panel">
                <h2><i class="fas fa-fire"></i> Most Viewed Products</h2>
                <?php if (empty($topProducts)): ?>
                    <div class="tf-empty">No product views recorded yet. Visitors record a view when they open a product card on a category page.</div>
                <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Product</th>
                            <th>Type</th>
                            <th>Views</th>
                            <th>Unique Visitors</th>
                            <th style="width: 28%;">Share</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topProducts as $i => $p): ?>
                        <tr>
                            <td><?php echo $i + 1; ?></td>
                            <td><strong><?php echo sanitize($p['name']); ?></strong></td>
                            <td><span class="badge-soft"><?php echo sanitize(getCategoryName($p['category'])); ?></span></td>
                            <td><?php echo number_format($p['views']); ?></td>
                            <td><?php echo number_format($p['visitors']); ?></td>
                            <td>
                                <div class="tf-bar-track">
                                    <div class="tf-bar-fill" style="width: <?php echo $maxProdViews ? round($p['views'] / $maxProdViews * 100) : 0; ?>%;"></div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>

            <div class="tf-grid-3">
                <div class="tf-panel">
                    <h2><i class="fas fa-mobile-screen"></i> Devices</h2>
                    <?php if (empty($devices)): ?><div class="tf-empty">No data.</div><?php else: ?>
                    <table class="data-table">
                        <tbody>
                            <?php foreach ($devices as $d): ?>
                            <tr>
                                <td><i class="fas <?php echo $d['device'] === 'Mobile' ? 'fa-mobile-screen' : ($d['device'] === 'Tablet' ? 'fa-tablet-screen-button' : 'fa-desktop'); ?>" style="color:#c2a04f;"></i> <?php echo sanitize($d['device']); ?></td>
                                <td style="text-align:right;"><strong><?php echo number_format($d['views']); ?></strong>
                                    <small style="color:#8a8f83;">(<?php echo $pageViews ? round($d['views'] / $pageViews * 100) : 0; ?>%)</small>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>

                <div class="tf-panel">
                    <h2><i class="fas fa-window-maximize"></i> Browsers</h2>
                    <?php if (empty($browsers)): ?><div class="tf-empty">No data.</div><?php else: ?>
                    <table class="data-table">
                        <tbody>
                            <?php foreach ($browsers as $b): ?>
                            <tr>
                                <td><?php echo sanitize($b['browser']); ?></td>
                                <td style="text-align:right;"><strong><?php echo number_format($b['views']); ?></strong></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>

                <div class="tf-panel">
                    <h2><i class="fas fa-share-nodes"></i> Top Referrers</h2>
                    <?php if (empty($referrers)): ?>
                        <div class="tf-empty">All visits are direct (no referrer).</div>
                    <?php else: ?>
                    <table class="data-table">
                        <tbody>
                            <?php foreach ($referrers as $r): ?>
                            <tr>
                                <td style="word-break: break-all; font-size: 0.8rem;"><?php echo sanitize(parse_url($r['referrer'], PHP_URL_HOST) ?: $r['referrer']); ?></td>
                                <td style="text-align:right;"><strong><?php echo number_format($r['views']); ?></strong></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Visitors -->
            <div class="tf-panel">
                <h2><i class="fas fa-users-viewfinder"></i> Visitors <span style="color:#8a8f83; letter-spacing:0; text-transform:none; font-size:0.76rem;">(click a row to see everything that visitor looked at)</span></h2>
                <?php if (empty($visitors)): ?>
                    <div class="tf-empty">No visitors in this range.</div>
                <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Visitor</th>
                            <th>Device / Browser</th>
                            <th>Categories Browsed</th>
                            <th>Pages</th>
                            <th>Products</th>
                            <th>Last Seen</th>
                            <th>Journey</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($visitors as $v): ?>
                        <tr>
                            <td>
                                <span class="vid-chip"><?php echo sanitize(substr($v['visitor_id'], 0, 10)); ?></span><br>
                                <small style="color:#8a8f83;"><?php echo sanitize($v['ip']); ?></small>
                            </td>
                            <td><?php echo sanitize($v['device']); ?> <small style="color:#8a8f83;">/ <?php echo sanitize($v['browser']); ?></small></td>
                            <td>
                                <?php
                                $cats = array_filter(array_map('trim', explode(',', (string) $v['categories'])));
                                if (empty($cats)) {
                                    echo '<small style="color:#8a8f83;">Home only</small>';
                                } else {
                                    foreach ($cats as $cat) {
                                        echo '<span class="badge-soft" style="margin:2px 3px 2px 0;">' . sanitize(getCategoryName($cat)) . '</span>';
                                    }
                                }
                                ?>
                            </td>
                            <td><?php echo number_format($v['views']); ?></td>
                            <td><?php echo number_format($v['product_views']); ?></td>
                            <td><small><?php echo date('d M Y, h:i A', strtotime($v['last_seen'])); ?></small></td>
                            <td>
                                <a href="?from=<?php echo sanitize($from); ?>&to=<?php echo sanitize($to); ?>&visitor=<?php echo urlencode($v['visitor_id']); ?>"
                                   class="btn-icon edit" title="View journey"><i class="fas fa-route"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
    const ink = '#0f1a0e', gold = '#c2a04f';

    <?php if ($pageViews > 0): ?>
    new Chart(document.getElementById('trafficChart'), {
        type: 'line',
        data: {
            labels: <?php echo json_encode($chartLabels); ?>,
            datasets: [
                {
                    label: 'Page Views',
                    data: <?php echo json_encode($chartViews); ?>,
                    borderColor: ink,
                    backgroundColor: 'rgba(15, 26, 14, 0.06)',
                    fill: true,
                    tension: 0.35,
                    pointRadius: 2
                },
                {
                    label: 'Unique Visitors',
                    data: <?php echo json_encode($chartVisitors); ?>,
                    borderColor: gold,
                    backgroundColor: 'rgba(194, 160, 79, 0.12)',
                    fill: true,
                    tension: 0.35,
                    pointRadius: 2
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' } },
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
        }
    });
    <?php endif; ?>

    <?php if (!empty($productTypeMix)): ?>
    new Chart(document.getElementById('typeChart'), {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode(array_map(fn($r) => getCategoryName($r['category']), $productTypeMix)); ?>,
            datasets: [{
                data: <?php echo json_encode(array_map(fn($r) => intval($r['views']), $productTypeMix)); ?>,
                backgroundColor: ['#0f1a0e', '#c2a04f', '#5c6356', '#a8853c', '#8a8f83', '#3c4436'],
                borderColor: '#fff',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } }
        }
    });
    <?php endif; ?>
    </script>
</body>
</html>
