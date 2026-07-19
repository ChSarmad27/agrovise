<?php
$file = 'F:/wamp64/www/agrovise/admin/purchasing.php';
$content = file_get_contents($file);

// 1. Update SQL
$oldSql = 'SELECT p.*, pr.name as product_name
    FROM purchasing p
    JOIN products pr ON p.product_id = pr.id
    ORDER BY p.date_added DESC';
$newSql = 'SELECT p.*, pr.name as product_name, v.name as vendor_name
    FROM purchasing p
    JOIN products pr ON p.product_id = pr.id
    LEFT JOIN vendors v ON p.vendor_id = v.id
    ORDER BY p.date_added DESC';

$content = str_replace($oldSql, $newSql, $content);

// 2. Update thead
$oldHead = '<th>Product</th>
                                <th>Batch / Type</th>';
$newHead = '<th>Product</th>
                                <th>Vendor</th>
                                <th>Batch / Type</th>';
$content = str_replace($oldHead, $newHead, $content);

// 3. Update tbody
$oldBody = '<td style="font-weight: 500; font-size: 1.1rem;"><?php echo sanitize($p[\'product_name\']); ?></td>
                                    <td>';
$newBody = '<td style="font-weight: 500; font-size: 1.1rem;"><?php echo sanitize($p[\'product_name\']); ?></td>
                                    <td><strong><?php echo sanitize($p[\'vendor_name\'] ?? \'Unknown\'); ?></strong></td>
                                    <td>';
$content = str_replace($oldBody, $newBody, $content);

file_put_contents($file, $content);
echo "purchasing.php updated successfully!\n";
