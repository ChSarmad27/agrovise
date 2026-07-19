<?php
require 'F:/wamp64/www/agrovise/includes/db.php';
$conn = getDBConnection();

function addColumnIfMissing($conn, $table, $column, $definition) {
    try {
        $check = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'")->fetch();
        if (!$check) {
            $conn->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
            echo "Added $column to $table.\n";
        } else {
            echo "$column already exists in $table.\n";
        }
    } catch (Exception $e) {
        echo "Error adding $column to $table: " . $e->getMessage() . "\n";
    }
}

// 1. Create Vendors table
$conn->exec("CREATE TABLE IF NOT EXISTS vendors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    contact_person VARCHAR(255),
    phone VARCHAR(20),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
echo "Vendors table ready.\n";

// 2. Add columns
addColumnIfMissing($conn, 'purchasing', 'vendor_id', 'INT');
addColumnIfMissing($conn, 'invoices', 'employee_id', 'INT');
addColumnIfMissing($conn, 'transactions', 'vendor_id', 'INT');
addColumnIfMissing($conn, 'transactions', 'employee_id', 'INT');

// Sample vendor
$count = $conn->query("SELECT COUNT(*) FROM vendors")->fetchColumn();
if ($count == 0) {
    $conn->prepare("INSERT INTO vendors (name, contact_person, phone) VALUES (?, ?, ?)")
         ->execute(['Global Agro Chemicals', 'Ahmad Khan', '0300-1234567']);
    echo "Sample vendor added.\n";
}

echo "Migration complete.\n";
