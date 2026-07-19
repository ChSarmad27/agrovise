<?php
require_once '../includes/db.php';
$conn = getDBConnection();

try {
    // Check if column exists
    $stmt = $conn->query("SHOW COLUMNS FROM transactions LIKE 'client_id'");
    $exists = $stmt->fetch();
    
    if (!$exists) {
        $conn->exec("ALTER TABLE transactions ADD COLUMN client_id INT DEFAULT NULL AFTER employee_id");
        $conn->exec("ALTER TABLE transactions ADD CONSTRAINT fk_transactions_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL");
        echo "Column 'client_id' added successfully to transactions table.\n";
    } else {
        echo "Column 'client_id' already exists.\n";
    }
} catch (PDOException $e) {
    die("Migration Error: " . $e->getMessage());
}
