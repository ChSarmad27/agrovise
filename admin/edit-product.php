<?php
/**
 * AGROVISE - Edit Product Page
 */

require_once '../includes/db.php';
require_once '../includes/functions.php';

// Check if admin is logged in
requirePermission('products');

// Get product ID
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$product = getProductById($id);

if (!$product) {
    setFlashMessage('error', 'Product not found!');
    header('Location: dashboard.php');
    exit;
}

$errors = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $packing_type = trim($_POST['packing_type'] ?? 'None');
    $avg_packs_per_carton = !empty($_POST['avg_packs_per_carton']) ? intval($_POST['avg_packs_per_carton']) : 0;
    
    // Validation
    if (empty($name)) {
        $errors[] = 'Product name is required.';
    }
    
    if (empty($category)) {
        $errors[] = 'Category is required.';
    }
    
    $imagePath = $product['image'];
    
    // Handle image upload if new image provided
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $uploadResult = uploadProductImage($_FILES['image']);
        if ($uploadResult) {
            // Delete old image
            deleteProductImage($product['image']);
            $imagePath = $uploadResult;
        } else {
            $errors[] = 'Failed to upload image. Please use JPG, PNG, GIF, or WebP (max 5MB).';
        }
    }
    
    // Update product if no errors
    if (empty($errors)) {
        try {
            $conn = getDBConnection();
            $stmt = $conn->prepare("UPDATE products SET name = ?, category = ?, packing_type = ?, avg_packs_per_carton = ?, image = ? WHERE id = ?");
            $stmt->execute([$name, $category, $packing_type, $avg_packs_per_carton, $imagePath, $id]);
            
            setFlashMessage('success', 'Product updated successfully!');
            header('Location: dashboard.php');
            exit;
        } catch (PDOException $e) {
            $errors[] = dbError($e);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Product - AGROVISE Admin</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=ed3">
</head>
<body class="admin-body">
    <div class="admin-wrapper">
        <!-- Sidebar -->
        <?php require __DIR__ . '/../includes/admin-sidebar.php'; ?>
        
        <!-- Main Content -->
        <main class="admin-main">
            <div class="admin-header">
                <h1><i class="fas fa-edit"></i> Edit Product</h1>
                <div class="admin-user">
                    <span>Welcome, <?php echo sanitize($_SESSION['admin_username']); ?></span>
                    <i class="fas fa-user-circle" style="font-size: 1.5rem; color: var(--primary-green);"></i>
                </div>
            </div>
            
            <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <ul style="margin: 0; padding-left: 20px;">
                    <?php foreach ($errors as $error): ?>
                    <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            
            <div class="data-card">
                <div class="data-card-header">
                    <h2>Edit: <?php echo sanitize($product['name']); ?></h2>
                    <a href="dashboard.php" class="btn btn-secondary btn-sm">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
                
                <div style="padding: 30px;">
                    <form method="POST" action="" enctype="multipart/form-data">
                        <?php echo csrfField(); ?>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px;">
                            <div class="form-group">
                                <label class="form-label" for="name">Product Name *</label>
                                <input type="text" id="name" name="name" class="form-input" placeholder="Enter product name" required value="<?php echo sanitize($product['name']); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="category">Category *</label>
                                <select id="category" name="category" class="form-select" required>
                                    <option value="">Select Category</option>
                                    <option value="insecticides" <?php echo $product['category'] === 'insecticides' ? 'selected' : ''; ?>>Insecticides</option>
                                    <option value="weedicides" <?php echo $product['category'] === 'weedicides' ? 'selected' : ''; ?>>Weedicides</option>
                                    <option value="fungicides" <?php echo $product['category'] === 'fungicides' ? 'selected' : ''; ?>>Fungicides</option>
                                    <option value="granulars" <?php echo $product['category'] === 'granulars' ? 'selected' : ''; ?>>Granulars</option>
                                    <option value="micronutrients" <?php echo $product['category'] === 'micronutrients' ? 'selected' : ''; ?>>Micronutrients & Fertilizers</option>
                                </select>
                            </div>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px; margin-bottom: 20px;">
                            <div class="form-group">
                                <label class="form-label" for="packing_type">Packing Type *</label>
                                <select id="packing_type" name="packing_type" class="form-select" required>
                                    <option value="None" <?php echo $product['packing_type'] === 'None' ? 'selected' : ''; ?>>None</option>
                                    <option value="Bottle" <?php echo $product['packing_type'] === 'Bottle' ? 'selected' : ''; ?>>Bottle</option>
                                    <option value="Bag" <?php echo $product['packing_type'] === 'Bag' ? 'selected' : ''; ?>>Bag</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="avg_packs_per_carton">Average Packs per Carton *</label>
                                <input type="number" id="avg_packs_per_carton" name="avg_packs_per_carton" class="form-input" placeholder="0" required min="0" value="<?php echo sanitize($product['avg_packs_per_carton'] ?? 0); ?>">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Product Image</label>
                            <div style="display: flex; gap: 20px; align-items: flex-start;">
                                <div>
                                    <p style="font-size: 0.9rem; color: var(--medium-gray); margin-bottom: 10px;">Current Image:</p>
                                    <img src="<?php echo getProductImagePath($product['image'], '..'); ?>" alt="Current" style="max-width: 150px; max-height: 150px; border-radius: 8px; border: 1px solid var(--light-gray);">
                                </div>
                                <div style="flex: 1;">
                                    <div class="form-file">
                                        <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/gif,image/webp">
                                        <label for="image" class="file-label">
                                            <i class="fas fa-cloud-upload-alt"></i>
                                            <span>Click to upload new image (optional)</span>
                                        </label>
                                    </div>
                                    <div id="image-preview" style="margin-top: 15px; display: none;">
                                        <img src="" alt="Preview" style="max-width: 150px; max-height: 150px; border-radius: 8px;">
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div style="display: flex; gap: 15px; margin-top: 30px;">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update Product
                            </button>
                            <a href="dashboard.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        document.getElementById('image').addEventListener('change', function(e) {
            const preview = document.getElementById('image-preview');
            const img = preview.querySelector('img');
            
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    img.src = e.target.result;
                    preview.style.display = 'block';
                };
                reader.readAsDataURL(this.files[0]);
            }
        });
    </script>
</body>
</html>
