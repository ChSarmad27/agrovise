<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireAdminLogin();

$conn = getDBConnection();

try {
    // 1. Add client_id to transactions
    $conn->exec("ALTER TABLE transactions ADD COLUMN client_id INT DEFAULT NULL AFTER employee_id");
    
    // 2. Add foreign key for client_id
    $conn->exec("ALTER TABLE transactions ADD CONSTRAINT fk_transactions_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL");
    
    echo "Migration successful: Added client_id to transactions table.";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Migration already applied or column exists.";
    } else {
        die("Migration failed: " . $e->getMessage());
    }
}
?>
