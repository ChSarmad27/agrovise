<?php
/**
 * AGROVISE - Publish Products
 */

require_once '../includes/db.php';
require_once '../includes/functions.php';

requirePermission('products');

$conn = getDBConnection();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // First set all to 0
        $conn->exec("UPDATE products SET is_published = 0");
        
        // Then set selected to 1
        if (!empty($_POST['published_products']) && is_array($_POST['published_products'])) {
            $ids = array_map('intval', $_POST['published_products']);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $conn->prepare("UPDATE products SET is_published = 1 WHERE id IN ($placeholders)");
            $stmt->execute($ids);
        }
        
        setFlashMessage('success', 'Products published status updated successfully!');
        header('Location: publish-products.php');
        exit;
    } catch (PDOException $e) {
        $errors[] = dbError($e);
    }
}

// Fetch all products
$stmt = $conn->query("SELECT * FROM products ORDER BY category, name");
$products = $stmt->fetchAll();

$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Publish Products - AGROVISE Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=ed3">
    <style>
        .product-checkbox {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }
        .publish-row {
            display: flex;
            align-items: center;
            padding: 10px;
            border-bottom: 1px solid var(--light-gray);
        }
        .publish-row:hover {
            background: #f9f9f9;
        }
        .publish-row img {
            width: 40px;
            height: 40px;
            border-radius: 4px;
            margin: 0 15px;
            object-fit: cover;
        }
    </style>
</head>
<body class="admin-body">
    <div class="admin-wrapper">
        <!-- Sidebar -->
        <?php require __DIR__ . '/../includes/admin-sidebar.php'; ?>
        
        <!-- Main Content -->
        <main class="admin-main">
            <div class="admin-header">
                <h1><i class="fas fa-globe"></i> Publish Products to Website</h1>
            </div>
            
            <?php if ($flash): ?>
            <div class="alert alert-<?php echo $flash['type']; ?>">
                <i class="fas fa-<?php echo $flash['type'] === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo $flash['message']; ?>
            </div>
            <?php endif; ?>
            
            <div class="data-card" style="padding: 20px;">
                <form method="POST" action="">
                        <?php echo csrfField(); ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h3>Select products to display on the public website</h3>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Published Status</button>
                    </div>
                    
                    <div style="background: white; border: 1px solid var(--light-gray); border-radius: 8px;">
                        <div class="publish-row" style="background: var(--light-gray); font-weight: bold;">
                            <div style="width: 50px; text-align: center;">
                                <input type="checkbox" id="selectAll" class="product-checkbox">
                            </div>
                            <div style="flex: 1;">Product Name</div>
                            <div style="width: 200px;">Category</div>
                            <div style="width: 150px;">Packing Type</div>
                        </div>
                        
                        <?php foreach ($products as $product): ?>
                        <div class="publish-row">
                            <div style="width: 50px; text-align: center;">
                                <input type="checkbox" name="published_products[]" value="<?php echo $product['id']; ?>" class="product-checkbox product-item" <?php echo $product['is_published'] ? 'checked' : ''; ?>>
                            </div>
                            <img src="<?php echo getProductImagePath($product['image'], '..'); ?>" alt="">
                            <div style="flex: 1; font-weight: 500;"><?php echo sanitize($product['name']); ?></div>
                            <div style="width: 200px; color: var(--medium-gray);"><?php echo getCategoryName($product['category']); ?></div>
                            <div style="width: 150px;">
                                <span class="badge" style="background: var(--light-green); color: var(--primary-green);"><?php echo sanitize($product['packing_type']); ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </form>
            </div>
        </main>
    </div>
    <script>
        document.getElementById('selectAll').addEventListener('change', function() {
            var checkboxes = document.querySelectorAll('.product-item');
            for (var checkbox of checkboxes) {
                checkbox.checked = this.checked;
            }
        });
    </script>
</body>
</html>
