<?php
/**
 * AGROVISE - Clients Management
 */

require_once '../includes/db.php';
require_once '../includes/functions.php';

requirePermission('clients');
$conn = getDBConnection();

// Handle delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM clients WHERE id = ?");
    $stmt->execute([$id]);
    setFlashMessage('success', 'Client deleted successfully!');
    header('Location: clients.php');
    exit;
}

// Search and Filter
$search = trim($_GET['search'] ?? '');
$areaFilter = trim($_GET['area'] ?? '');

// Get distinct areas for filter dropdown
$areas = $conn->query("SELECT DISTINCT area FROM clients WHERE area != '' ORDER BY area ASC")->fetchAll(PDO::FETCH_COLUMN);

// Build query
$query = "SELECT * FROM clients WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND name LIKE ?";
    $params[] = "%$search%";
}

if (!empty($areaFilter)) {
    $query .= " AND area = ?";
    $params[] = $areaFilter;
}

$query .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$clients = $stmt->fetchAll();

$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clients - AGROVISE Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=ed3">
    <style>
        @media print {
            .admin-sidebar, .admin-header, .filters-bar, .action-btns, .btn {
                display: none !important;
            }
            .admin-main { margin-left: 0 !important; width: 100% !important; }
            body { background: #fff; }
            .data-card { box-shadow: none; border: none; }
        }
        .filters-bar {
            display: flex; gap: 15px; margin-bottom: 20px; align-items: center;
        }
    </style>
</head>
<body class="admin-body">
    <div class="admin-wrapper">
        <?php require __DIR__ . '/../includes/admin-sidebar.php'; ?>
        
        <main class="admin-main">
            <div class="admin-header">
                <h1><i class="fas fa-users"></i> Client Management</h1>
                <div class="admin-user">
                    <span>Welcome, <?php echo sanitize($_SESSION['admin_username']); ?></span>
                    <i class="fas fa-user-circle" style="font-size: 1.5rem; color: var(--primary-green);"></i>
                </div>
            </div>
            
            <?php if ($flash): ?>
            <div class="alert alert-<?php echo $flash['type']; ?>">
                <?php echo $flash['message']; ?>
            </div>
            <?php endif; ?>
            
            <div class="data-card">
                <div class="data-card-header">
                    <h2><i class="fas fa-list"></i> Clients List</h2>
                    <div style="display: flex; gap: 10px;">
                        <a href="add-client.php" class="btn btn-primary btn-sm">
                            <i class="fas fa-plus"></i> Add Client
                        </a>
                        <button onclick="window.print()" class="btn btn-outline btn-sm">
                            <i class="fas fa-file-pdf"></i> Export PDF
                        </button>
                    </div>
                </div>
                
                <div style="padding: 20px;">
                    <form method="GET" class="filters-bar">
                        <input type="text" name="search" class="form-input" placeholder="Search by name..." value="<?php echo sanitize($search); ?>" style="max-width: 250px;">
                        <select name="area" class="form-select" style="max-width: 200px;">
                            <option value="">All Areas</option>
                            <?php foreach($areas as $area): ?>
                            <option value="<?php echo sanitize($area); ?>" <?php echo $areaFilter === $area ? 'selected' : ''; ?>><?php echo sanitize($area); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-secondary btn-sm"><i class="fas fa-filter"></i> Filter</button>
                        <a href="clients.php" class="btn btn-outline btn-sm"><i class="fas fa-redo"></i> Reset</a>
                    </form>
                    
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Address</th>
                                <th>CNIC</th>
                                <th>Phone</th>
                                <th>Govt Reg No</th>
                                <th>Area</th>
                                <th class="action-btns">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($clients)): ?>
                            <tr><td colspan="7" style="text-align: center;">No clients found.</td></tr>
                            <?php endif; ?>
                            
                            <?php foreach ($clients as $client): ?>
                            <tr>
                                <td><strong><?php echo sanitize($client['name']); ?></strong></td>
                                <td><?php echo sanitize($client['address']); ?></td>
                                <td><?php echo sanitize($client['cnic']); ?></td>
                                <td><?php echo sanitize($client['phone']); ?></td>
                                <td><?php echo sanitize($client['govt_reg_no'] ?: '-'); ?></td>
                                <td><span class="category-badge insecticides"><?php echo sanitize($client['area']); ?></span></td>
                                <td class="action-btns">
                                    <div style="display: flex; gap: 5px;">
                                        <a href="edit-client.php?id=<?php echo $client['id']; ?>" class="btn-icon edit" title="Edit"><i class="fas fa-edit"></i></a>
                                        <a href="?delete=<?php echo $client['id']; ?>" class="btn-icon delete" title="Delete" onclick="return confirm('Are you sure you want to delete this client?');"><i class="fas fa-trash"></i></a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
