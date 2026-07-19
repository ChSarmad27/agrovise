<?php
/**
 * AGROVISE - Ledger CSV exports (accounts/admin only)
 * ?report=<type>[&year=YYYY]  streams a detailed CSV download.
 */
require_once '../includes/db.php';
require_once '../includes/functions.php';
requirePermission('ledgers');

$conn = getDBConnection();
$report = $_GET['report'] ?? '';
$year = isset($_GET['year']) && ctype_digit((string)$_GET['year']) ? intval($_GET['year']) : null;

/** Stream an array of rows as a CSV download and stop. */
function csvOut($filename, array $headers, array $rows) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fprintf($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
    // Explicit delimiter/enclosure/escape (escape '' = RFC-4180; avoids PHP 8.4 deprecation)
    fputcsv($out, $headers, ',', '"', '');
    foreach ($rows as $r) fputcsv($out, $r, ',', '"', '');
    fclose($out);
    exit;
}

// Year filter fragments (each query names its own date column)
$yA = $year ? " AND YEAR(i.date) = " . $year : "";          // invoices i
$yT = $year ? " AND YEAR(transaction_date) = " . $year : ""; // transactions
$yP = $year ? " AND YEAR(date_added) = " . $year : "";       // purchasing
$stamp = date('Ymd');
$suffix = ($year ? "_$year" : "") . "_$stamp.csv";

switch ($report) {

case 'monthly_pl':
    // Revenue & COGS from invoice lines, operating expenses from transactions, per month
    $rev = [];
    foreach ($conn->query("
        SELECT DATE_FORMAT(i.date, '%Y-%m') ym,
               SUM(ii.quantity * ii.price) revenue,
               SUM(ii.quantity * pur.purchase_price) cogs
        FROM invoices i
        JOIN invoice_items ii ON ii.invoice_id = i.id
        JOIN purchasing pur ON ii.purchasing_id = pur.id
        WHERE 1=1 $yA GROUP BY ym")->fetchAll() as $r) {
        $rev[$r['ym']] = ['revenue' => floatval($r['revenue']), 'cogs' => floatval($r['cogs']), 'exp' => 0];
    }
    foreach ($conn->query("
        SELECT DATE_FORMAT(transaction_date, '%Y-%m') ym, SUM(amount) exp
        FROM transactions WHERE type='WITHDRAWAL'
          AND category IN ('Salaries','Office Expenses','Custom','Employee Expenses') $yT
        GROUP BY ym")->fetchAll() as $r) {
        if (!isset($rev[$r['ym']])) $rev[$r['ym']] = ['revenue' => 0, 'cogs' => 0, 'exp' => 0];
        $rev[$r['ym']]['exp'] = floatval($r['exp']);
    }
    ksort($rev);
    $rows = [];
    foreach ($rev as $ym => $v) {
        $profit = $v['revenue'] - $v['cogs'] - $v['exp'];
        $rows[] = [date('F Y', strtotime($ym . '-01')), number_format($v['revenue'], 2, '.', ''),
                   number_format($v['cogs'], 2, '.', ''), number_format($v['exp'], 2, '.', ''),
                   number_format($profit, 2, '.', ''), $profit >= 0 ? 'PROFIT' : 'LOSS'];
    }
    csvOut("monthly_profit_loss$suffix", ['Month', 'Revenue', 'COGS', 'Operating Expenses', 'Net Profit/Loss', 'Result'], $rows);

case 'yearly_pl':
    $rev = [];
    foreach ($conn->query("
        SELECT YEAR(i.date) yr, SUM(ii.quantity*ii.price) revenue, SUM(ii.quantity*pur.purchase_price) cogs
        FROM invoices i JOIN invoice_items ii ON ii.invoice_id=i.id JOIN purchasing pur ON ii.purchasing_id=pur.id
        GROUP BY yr")->fetchAll() as $r) {
        $rev[$r['yr']] = ['revenue' => floatval($r['revenue']), 'cogs' => floatval($r['cogs']), 'exp' => 0];
    }
    foreach ($conn->query("
        SELECT YEAR(transaction_date) yr, SUM(amount) exp FROM transactions
        WHERE type='WITHDRAWAL' AND category IN ('Salaries','Office Expenses','Custom','Employee Expenses') GROUP BY yr")->fetchAll() as $r) {
        if (!isset($rev[$r['yr']])) $rev[$r['yr']] = ['revenue' => 0, 'cogs' => 0, 'exp' => 0];
        $rev[$r['yr']]['exp'] = floatval($r['exp']);
    }
    ksort($rev);
    $rows = [];
    foreach ($rev as $yr => $v) {
        $profit = $v['revenue'] - $v['cogs'] - $v['exp'];
        $rows[] = [$yr, number_format($v['revenue'], 2, '.', ''), number_format($v['cogs'], 2, '.', ''),
                   number_format($v['exp'], 2, '.', ''), number_format($profit, 2, '.', ''), $profit >= 0 ? 'PROFIT' : 'LOSS'];
    }
    csvOut("yearly_profit_loss_$stamp.csv", ['Year', 'Revenue', 'COGS', 'Operating Expenses', 'Net Profit/Loss', 'Result'], $rows);

case 'area_pl':
    $rows = [];
    foreach ($conn->query("
        SELECT COALESCE(NULLIF(c.area,''),'Unspecified') area,
               SUM(ii.quantity*ii.price) revenue, SUM(ii.quantity*pur.purchase_price) cogs
        FROM invoices i
        JOIN clients c ON i.client_id=c.id
        JOIN invoice_items ii ON ii.invoice_id=i.id
        JOIN purchasing pur ON ii.purchasing_id=pur.id
        WHERE 1=1 $yA GROUP BY area ORDER BY revenue DESC")->fetchAll() as $r) {
        $profit = floatval($r['revenue']) - floatval($r['cogs']);
        $rows[] = [$r['area'], number_format($r['revenue'], 2, '.', ''), number_format($r['cogs'], 2, '.', ''),
                   number_format($profit, 2, '.', ''), $profit >= 0 ? 'PROFIT' : 'LOSS'];
    }
    csvOut("profit_loss_by_area$suffix", ['Area', 'Revenue', 'COGS', 'Net Profit/Loss', 'Result'], $rows);

case 'cost':
    $rows = [];
    foreach ($conn->query("
        SELECT p.name, SUM(ii.quantity) qty, SUM(ii.quantity*pur.purchase_price) total_cost
        FROM invoice_items ii
        JOIN products p ON ii.product_id=p.id
        JOIN purchasing pur ON ii.purchasing_id=pur.id
        JOIN invoices i ON ii.invoice_id=i.id
        WHERE 1=1 $yA GROUP BY p.id ORDER BY total_cost DESC")->fetchAll() as $r) {
        $qty = floatval($r['qty']); $tc = floatval($r['total_cost']);
        $rows[] = [$r['name'], number_format($qty, 2, '.', ''), number_format($qty ? $tc / $qty : 0, 2, '.', ''), number_format($tc, 2, '.', '')];
    }
    csvOut("cost_of_goods$suffix", ['Product', 'Qty Sold', 'Avg Unit Cost', 'Total COGS'], $rows);

case 'products_sold':
    $rows = [];
    foreach ($conn->query("
        SELECT i.invoice_no, i.date, c.name client, p.name product, pur.batch_number,
               ii.quantity, ii.price, ii.sales_tax
        FROM invoice_items ii
        JOIN invoices i ON ii.invoice_id=i.id
        JOIN clients c ON i.client_id=c.id
        JOIN products p ON ii.product_id=p.id
        JOIN purchasing pur ON ii.purchasing_id=pur.id
        WHERE 1=1 $yA ORDER BY i.date, i.invoice_no")->fetchAll() as $r) {
        $line = $r['quantity'] * $r['price'] * (1 + floatval($r['sales_tax']) / 100);
        $rows[] = [$r['invoice_no'], $r['date'], $r['client'], $r['product'], $r['batch_number'],
                   number_format($r['quantity'], 2, '.', ''), number_format($r['price'], 2, '.', ''),
                   number_format($r['sales_tax'], 2, '.', ''), number_format($line, 2, '.', '')];
    }
    csvOut("products_sold$suffix", ['Invoice', 'Date', 'Client', 'Product', 'Batch', 'Qty', 'Unit Price', 'Tax %', 'Line Total'], $rows);

case 'bulk_bought':
    $rows = [];
    foreach ($conn->query("
        SELECT pur.batch_number, p.name product, v.name vendor, pur.quantity, pur.purchase_price,
               pur.total_price, pur.date_added, pur.expiry_date
        FROM purchasing pur
        JOIN products p ON pur.product_id=p.id
        LEFT JOIN vendors v ON pur.vendor_id=v.id
        WHERE pur.type='BULK' " . ($year ? " AND YEAR(pur.date_added)=$year" : "") . "
        ORDER BY pur.date_added DESC")->fetchAll() as $r) {
        $rows[] = [$r['batch_number'], $r['product'], $r['vendor'] ?? '—', number_format($r['quantity'], 2, '.', ''),
                   number_format($r['purchase_price'], 2, '.', ''), number_format($r['total_price'], 2, '.', ''),
                   $r['date_added'] ? date('Y-m-d', strtotime($r['date_added'])) : '', $r['expiry_date'] ?? ''];
    }
    csvOut("bulk_purchases$suffix", ['Batch', 'Product', 'Vendor', 'Qty', 'Unit Price', 'Total', 'Purchased On', 'Expiry'], $rows);

case 'buy_sell_comparison':
    // Per product: bought (all lots) vs sold (invoice lines)
    $bought = [];
    foreach ($conn->query("SELECT product_id, SUM(quantity) q, SUM(total_price) t FROM purchasing WHERE type!='FINISHED' GROUP BY product_id")->fetchAll() as $r) {
        $bought[$r['product_id']] = ['q' => floatval($r['q']), 't' => floatval($r['t'])];
    }
    $rows = [];
    foreach ($conn->query("
        SELECT p.id, p.name,
               IFNULL(SUM(ii.quantity),0) sold_q,
               IFNULL(SUM(ii.quantity*ii.price),0) revenue,
               IFNULL(SUM(ii.quantity*pur.purchase_price),0) cogs
        FROM products p
        LEFT JOIN invoice_items ii ON ii.product_id=p.id
        LEFT JOIN purchasing pur ON ii.purchasing_id=pur.id
        GROUP BY p.id ORDER BY p.name")->fetchAll() as $r) {
        $b = $bought[$r['id']] ?? ['q' => 0, 't' => 0];
        $profit = floatval($r['revenue']) - floatval($r['cogs']);
        $rows[] = [$r['name'], number_format($b['q'], 2, '.', ''), number_format($b['t'], 2, '.', ''),
                   number_format($r['sold_q'], 2, '.', ''), number_format($r['revenue'], 2, '.', ''),
                   number_format($r['cogs'], 2, '.', ''), number_format($profit, 2, '.', '')];
    }
    csvOut("bought_vs_sold_$stamp.csv", ['Product', 'Qty Bought', 'Total Buy Cost', 'Qty Sold', 'Sales Revenue', 'COGS', 'Gross Profit'], $rows);

case 'expenses':
    $rows = [];
    $total = 0;
    foreach ($conn->query("
        SELECT transaction_date, category, description, amount
        FROM transactions
        WHERE type='WITHDRAWAL' AND category IN ('Office Expenses','Custom','Employee Expenses') $yT
        ORDER BY transaction_date")->fetchAll() as $r) {
        $total += floatval($r['amount']);
        $rows[] = [$r['transaction_date'], $r['category'], $r['description'], number_format($r['amount'], 2, '.', '')];
    }
    $rows[] = ['', '', 'TOTAL EXPENSES', number_format($total, 2, '.', '')];
    csvOut("expenses$suffix", ['Date', 'Category', 'Description', 'Amount'], $rows);

case 'salaries':
    $rows = [];
    $total = 0;
    foreach ($conn->query("
        SELECT t.transaction_date, e.name, e.role, t.amount, t.description
        FROM transactions t
        LEFT JOIN employees e ON t.employee_id=e.id
        WHERE t.type='WITHDRAWAL' AND t.category='Salaries' $yT
        ORDER BY e.name, t.transaction_date")->fetchAll() as $r) {
        $total += floatval($r['amount']);
        $rows[] = [$r['transaction_date'], $r['name'] ?? '—', $r['role'] ?? '', number_format($r['amount'], 2, '.', ''), $r['description']];
    }
    $rows[] = ['', 'TOTAL SALARIES', '', number_format($total, 2, '.', ''), ''];
    csvOut("salaries$suffix", ['Date', 'Employee', 'Role', 'Amount', 'Description'], $rows);

case 'client_ledger':
    $rows = [];
    foreach ($conn->query("
        SELECT c.name, c.area, e.name officer,
               IFNULL((SELECT SUM(total_amount) FROM invoices WHERE client_id=c.id),0) invoiced,
               IFNULL((SELECT SUM(amount_received) FROM payment_receipts WHERE client_id=c.id),0) paid
        FROM clients c LEFT JOIN employees e ON c.employee_id=e.id
        ORDER BY c.area, c.name")->fetchAll() as $r) {
        $bal = floatval($r['invoiced']) - floatval($r['paid']);
        $rows[] = [$r['name'], $r['area'], $r['officer'] ?? '—', number_format($r['invoiced'], 2, '.', ''),
                   number_format($r['paid'], 2, '.', ''), number_format($bal, 2, '.', '')];
    }
    csvOut("client_ledger_$stamp.csv", ['Client', 'Area', 'Sales Officer', 'Total Invoiced', 'Total Paid', 'Balance Due'], $rows);

case 'officer_receivables':
    $rows = [];
    foreach ($conn->query("
        SELECT e.name, e.role, e.salary,
               IFNULL((SELECT SUM(total_amount) FROM invoices WHERE employee_id=e.id),0) invoiced,
               IFNULL((SELECT SUM(amount_received) FROM payment_receipts WHERE employee_id=e.id),0) collected,
               IFNULL((SELECT SUM(amount) FROM transactions WHERE employee_id=e.id AND category='Salaries' AND type='WITHDRAWAL'),0) salary_paid
        FROM employees e ORDER BY e.name")->fetchAll() as $r) {
        $out = floatval($r['invoiced']) - floatval($r['collected']);
        $rows[] = [$r['name'], $r['role'], number_format($r['salary'], 2, '.', ''),
                   number_format($r['invoiced'], 2, '.', ''), number_format($r['collected'], 2, '.', ''),
                   number_format($out, 2, '.', ''), number_format($r['salary_paid'], 2, '.', '')];
    }
    csvOut("officer_receivables_$stamp.csv", ['Officer', 'Role', 'Monthly Salary', 'Sales Invoiced', 'Collected', 'Outstanding', 'Salary Paid'], $rows);

case 'stock':
    $rows = [];
    foreach ($conn->query("
        SELECT p.name product, pur.batch_number, pur.type, v.name vendor,
               pur.quantity, pur.purchase_price, (pur.quantity*pur.purchase_price) value,
               pur.expiry_date, pur.date_added
        FROM purchasing pur
        JOIN products p ON pur.product_id=p.id
        LEFT JOIN vendors v ON pur.vendor_id=v.id
        WHERE pur.quantity > 0
        ORDER BY pur.expiry_date IS NULL, pur.expiry_date ASC, p.name")->fetchAll() as $r) {
        $rows[] = [$r['product'], $r['batch_number'], $r['type'], $r['vendor'] ?? '—',
                   number_format($r['quantity'], 2, '.', ''), number_format($r['purchase_price'], 2, '.', ''),
                   number_format($r['value'], 2, '.', ''), $r['expiry_date'] ?? '—',
                   $r['date_added'] ? date('Y-m-d', strtotime($r['date_added'])) : ''];
    }
    csvOut("stock_report_$stamp.csv", ['Product', 'Batch', 'Type', 'Bought From (Vendor)', 'Qty In Stock', 'Unit Cost', 'Stock Value', 'Expiry Date', 'Added On'], $rows);

default:
    header('Location: ledger.php');
    exit;
}
