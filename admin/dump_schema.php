<?php
/**
 * AGROVISE - Schema inspector (admin-only diagnostic)
 * Prints CREATE TABLE for every table in the live database.
 * (Replaces the old unauthenticated dump_schema.php + schema_info.php pair.)
 */
require_once '../includes/db.php';
require_once '../includes/functions.php';
requireAdminLogin();
if (!isSuperAdmin()) { die('Admin role required.'); }

header('Content-Type: text/plain; charset=utf-8');
$conn = getDBConnection();
foreach ($conn->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN) as $t) {
    $res = $conn->query("SHOW CREATE TABLE `$t`")->fetch();
    echo "--- TABLE $t ---\n" . $res['Create Table'] . "\n\n";
}
