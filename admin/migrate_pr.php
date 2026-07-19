<?php
require 'F:/wamp64/www/agrovise/includes/db.php';
$conn = getDBConnection();

try {
    // 1. Check if bank_id column exists
    $check = $conn->query("SHOW COLUMNS FROM payment_receipts LIKE 'bank_id'")->fetch();
    
    if (!$check) {
        $conn->exec("ALTER TABLE payment_receipts ADD COLUMN bank_id INT NULL");
        echo "Column 'bank_id' added to 'payment_receipts' successfully.\n";
    } else {
        echo "Column 'bank_id' already exists.\n";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
