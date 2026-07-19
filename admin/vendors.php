<?php
/**
 * AGROVISE - Vendors Management
 */
require_once '../includes/db.php';
require_once '../includes/functions.php';
requirePermission('vendors');

$conn = getDBConnection();

// Handle Delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = intval($_GET['delete']);
    try {
        $stmt = $conn->prepare("DELETE FROM vendors WHERE id = ?");
        $stmt->execute([$id]);
        setFlashMessage('success', 'Vendor deleted successfully!');
    } catch (PDOException $e) {
        setFlashMessage('error', 'Cannot delete this vendor (it is linked to purchasing records).');
    }
    header('Location: vendors.php');
    exit;
}

// Search
$search = trim($_GET['search'] ?? '');
$query = "SELECT * FROM vendors WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (name LIKE ? OR contact_person LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " ORDER BY name ASC";
$stmt = $conn->prepare($query);
$stmt->execute($params);
$vendors = $stmt->fetchAll();

$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vendors - AGROVISE Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=ed3">
    <style>
        .filters-bar { display: flex; gap: 15px; margin-bottom: 20px; align-items: center; }
    </style>
</head>
<body class="admin-body">
    <div class="admin-wrapper">
        <?php require __DIR__ . '/../includes/admin-sidebar.php'; ?>
        
        <main class="admin-main">
            <div class="admin-header">
                <h1><i class="fas fa-truck"></i> Vendor Management</h1>
            </div>
            
            <?php if ($flash): ?>
            <div class="alert alert-<?php echo $flash['type']; ?>">
                <?php echo $flash['message']; ?>
            </div>
            <?php endif; ?>
            
            <div class="data-card">
                <div class="data-card-header">
                    <h2><i class="fas fa-list"></i> Vendors / Suppliers List</h2>
                    <a href="add-vendor.php" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus"></i> Add Vendor
                    </a>
                </div>
                
                <div style="padding: 20px;">
                    <form method="GET" class="filters-bar">
                        <input type="text" name="search" class="form-input" placeholder="Search by name or contact..." value="<?php echo sanitize($search); ?>" style="max-width: 300px;">
                        <button type="submit" class="btn btn-secondary btn-sm"><i class="fas fa-search"></i> Search</button>
                        <a href="vendors.php" class="btn btn-outline btn-sm"><i class="fas fa-redo"></i> Reset</a>
                    </form>
                    
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Vendor Name</th>
                                <th>Contact Person</th>
                                <th>Phone</th>
                                <th>Address</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($vendors)): ?>
                            <tr><td colspan="5" style="text-align: center;">No vendors found.</td></tr>
                            <?php endif; ?>
                            
                            <?php foreach ($vendors as $v): ?>
                            <tr>
                                <td><strong><?php echo sanitize($v['name']); ?></strong></td>
                                <td><?php echo sanitize($v['contact_person'] ?: '-'); ?></td>
                                <td><?php echo sanitize($v['phone']); ?></td>
                                <td><?php echo sanitize($v['address']); ?></td>
                                <td class="action-btns">
                                    <div style="display: flex; gap: 5px;">
                                        <a href="detailed-report.php?type=vendor&id=<?php echo $v['id']; ?>" class="btn-icon edit" title="Statement"><i class="fas fa-file-invoice"></i></a>
                                        <a href="edit-vendor.php?id=<?php echo $v['id']; ?>" class="btn-icon edit" title="Edit"><i class="fas fa-edit"></i></a>
                                        <a href="?delete=<?php echo $v['id']; ?>" class="btn-icon delete" title="Delete" onclick="return confirm('Delete this vendor?');"><i class="fas fa-trash"></i></a>
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
