<?php
/**
 * AGROVISE Database Configuration
 * MySQL connection setup for the agriculture products website.
 *
 * Fresh install: import agrovise_db.sql (repo root) — it is the schema
 * source of truth and includes the default admin account (admin/admin123).
 */

// Database credentials
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'agrovise_db');

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
