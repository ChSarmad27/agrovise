<?php
/**
 * AGROVISE Database Configuration
 * MySQL connection setup for the agriculture products website.
 *
 * Fresh install: import database/schema.sql — it creates every table plus
 * the default admin account (admin/admin123; change it after first login).
 */

// Server-specific credentials go in includes/config.php (never committed;
// copy config.example.php). Without it, the local WAMP defaults below apply.
if (file_exists(__DIR__ . '/config.php')) {
    require __DIR__ . '/config.php';
}
defined('DB_HOST') || define('DB_HOST', 'localhost');
defined('DB_USER') || define('DB_USER', 'root');
defined('DB_PASS') || define('DB_PASS', '');
defined('DB_NAME') || define('DB_NAME', 'agrovise_db');

/**
 * Create database connection
 * @return PDO
 */
function getDBConnection() {
    try {
        $conn = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS
        );
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $conn;
    } catch(PDOException $e) {
        error_log('[AGROVISE DB CONNECT] ' . $e->getMessage());
        http_response_code(500);
        die("The service is temporarily unavailable. Please try again shortly.");
    }
}
?>
