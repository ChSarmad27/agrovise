<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
requireAdminLogin();

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$conn = getDBConnection();

if ($action === 'search_clients') {
    $term = trim($_GET['term'] ?? '');
    $stmt = $conn->prepare("SELECT * FROM clients WHERE name LIKE ? OR phone LIKE ? OR cnic LIKE ? LIMIT 10");
    $stmt->execute(["%$term%", "%$term%", "%$term%"]);
    echo json_encode($stmt->fetchAll());
} 
elseif ($action === 'get_client') {
    $id = intval($_GET['id'] ?? 0);
    $stmt = $conn->prepare("SELECT * FROM clients WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode($stmt->fetch() ?: []);
} 
elseif ($action === 'get_product') {
    $id = intval($_GET['id'] ?? 0);
    $stmt = $conn->prepare("SELECT id, name, packing_type, avg_packs_per_carton FROM products WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode($stmt->fetch() ?: []);
}
?>
