<?php
require 'F:/wamp64/www/agrovise/includes/db.php';
$conn = getDBConnection();
$tables = ['clients', 'products', 'purchasing', 'invoices', 'invoice_items', 'packing_operations', 'transactions'];

foreach($tables as $t) {
    echo "--- TABLE $t ---\n";
    try {
        $res = $conn->query("SHOW CREATE TABLE $t")->fetch(PDO::FETCH_ASSOC);
        echo $res['Create Table'] . "\n\n";
    } catch(Exception $e) {}
}
