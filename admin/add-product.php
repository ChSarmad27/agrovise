<?php
/**
 * AGROVISE - Add Product Page
 */

require_once '../includes/db.php';
require_once '../includes/functions.php';

// Check if admin is logged in
requirePermission('products');

$errors = [];
$success = false;

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
    
    // Handle image upload
    $imagePath = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $uploadResult = uploadProductImage($_FILES['image']);
        if ($uploadResult) {
            $imagePath = $uploadResult;
        } else {
            $errors[] = 'Failed to upload image. Please use JPG, PNG, GIF, or WebP (max 5MB).';
        }
    } else {
        // Use default image
        $imagePath = 'placeholder-product.jpg';
    }
    
    // Insert product if no errors
    if (empty($errors)) {
        try {
            $conn = getDBConnection();
            $stmt = $conn->prepare("INSERT INTO products (name, category, packing_type, avg_packs_per_carton, image) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$name, $category, $packing_type, $avg_packs_per_carton, $imagePath]);
            
            setFlashMessage('success', 'Product added successfully!');
            header('Location: dashboard.php');
            exit;
        } catch (PDOException $e) {
            $errors[] = dbError($e);
        }
    }
}

$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Product - AGROVISE Admin</title>
    
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
                <h1><i class="fas fa-plus-circle"></i> Add New Product</h1>
                <div class="admin-user">
                    <span>Welcome, <?php echo sanitize($_SESSION['admin_username']); ?></span>
                    <i class="fas fa-user-circle" style="font-size: 1.5rem; color: var(--primary-green);"></i>
                </div>
            </div>
            
            <?php if ($flash): ?>
            <div class="alert alert-<?php echo $flash['type']; ?>">
                <i class="fas fa-<?php echo $flash['type'] === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo $flash['message']; ?>
            </div>
            <?php endif; ?>
            
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
                    <h2>Product Information</h2>
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
                                <input type="text" id="name" name="name" class="form-input" placeholder="Enter product name" required value="<?php echo isset($_POST['name']) ? sanitize($_POST['name']) : ''; ?>">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="category">Category *</label>
                                <select id="category" name="category" class="form-select" required>
                                    <option value="">Select Category</option>
                                    <option value="insecticides" <?php echo (isset($_POST['category']) && $_POST['category'] === 'insecticides') ? 'selected' : ''; ?>>Insecticides</option>
                                    <option value="weedicides" <?php echo (isset($_POST['category']) && $_POST['category'] === 'weedicides') ? 'selected' : ''; ?>>Weedicides</option>
                                    <option value="fungicides" <?php echo (isset($_POST['category']) && $_POST['category'] === 'fungicides') ? 'selected' : ''; ?>>Fungicides</option>
                                    <option value="granulars" <?php echo (isset($_POST['category']) && $_POST['category'] === 'granulars') ? 'selected' : ''; ?>>Granulars</option>
                                    <option value="micronutrients" <?php echo (isset($_POST['category']) && $_POST['category'] === 'micronutrients') ? 'selected' : ''; ?>>Micronutrients & Fertilizers</option>
                                </select>
                            </div>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px; margin-bottom: 20px;">
                            <div class="form-group">
                                <label class="form-label" for="packing_type">Packing Type *</label>
                                <select id="packing_type" name="packing_type" class="form-select" required>
                                    <option value="None" <?php echo (isset($_POST['packing_type']) && $_POST['packing_type'] === 'None') ? 'selected' : ''; ?>>None</option>
                                    <option value="Bottle" <?php echo (isset($_POST['packing_type']) && $_POST['packing_type'] === 'Bottle') ? 'selected' : ''; ?>>Bottle</option>
                                    <option value="Bag" <?php echo (isset($_POST['packing_type']) && $_POST['packing_type'] === 'Bag') ? 'selected' : ''; ?>>Bag</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="avg_packs_per_carton">Average Packs per Carton *</label>
                                <input type="number" id="avg_packs_per_carton" name="avg_packs_per_carton" class="form-input" placeholder="0" required min="0" value="<?php echo isset($_POST['avg_packs_per_carton']) ? sanitize($_POST['avg_packs_per_carton']) : '0'; ?>">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Product Image</label>
                            <div class="form-file">
                                <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/gif,image/webp">
                                <label for="image" class="file-label">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <span>Click to upload image (JPG, PNG, GIF, WebP - Max 5MB)</span>
                                </label>
                            </div>
                            <div id="image-preview" style="margin-top: 15px; display: none;">
                                <img src="" alt="Preview" style="max-width: 200px; max-height: 200px; border-radius: 8px;">
                            </div>
                        </div>
                        
                        <div style="display: flex; gap: 15px; margin-top: 30px;">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Add Product
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
        // Image preview
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
